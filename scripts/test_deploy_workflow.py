"""Exercise the actual deployment script with isolated SSH/Docker/HTTP stubs."""

import os
from pathlib import Path
import subprocess
import tempfile
import textwrap
import unittest

WORKFLOW = Path(__file__).resolve().parents[1] / '.github/workflows/deploy.yml'

DOCKER = r'''#!/bin/bash
set -eu
printf 'docker %s\n' "$*" >> "$MOCK_LOG"
[[ " $* " == *" exec "* ]] || exit 0
# Compose exec forwards stdin even when -T disables the terminal.
input_file="$(mktemp)"
cat > "$input_file"
case "$*" in
  *pg_dump*) printf 'database snapshot\n' ;;
  *pg_restore*) grep -Fxq 'database snapshot' "$input_file" ;;
  *'-xzf'*) tar -tzf "$input_file" > /dev/null ;;
  *'-czf'*) tar -czf - --files-from /dev/null ;;
  *psql*) printf '1|2|3|4|5\n' ;;
  *'artisan migrate'*) [[ "${FAIL_MIGRATION:-0}" != 1 ]] ;;
  *'wget '*) [[ "${FAIL_INTERNAL_HTTP:-0}" != 1 ]] ;;
esac
'''
SSH = '#!/bin/bash\nshift\nexec "$@"\n'
GIT = r'''#!/bin/bash
set -eu
printf 'git %s\n' "$*" >> "$MOCK_LOG"
if [[ " $* " == *" checkout "* ]]; then
  mkdir -p bootstrap
  printf '<?php // checkout fixture\n' > bootstrap/app.php
fi
printf 'v-test\n'
'''
CURL = '#!/bin/bash\nprintf "curl %s\\n" "$*" >> "$MOCK_LOG"\n[[ "${FAIL_PUBLIC_HTTP:-0}" != 1 ]]\n'


class DeploymentWorkflowTest(unittest.TestCase):
    def run_deployment(self, *, swallow_input=False, **failures):
        # The deployment is the last run block in this workflow. Keep the test
        # independent of YAML packages and execute the real embedded Bash.
        script = textwrap.dedent(WORKFLOW.read_text().rsplit('        run: |\n', 1)[1])
        script = script.replace('${{ secrets.VPS_USER }}@${{ secrets.VPS_HOST }}', 'test@server')
        script = script.replace('cd /opt/bentador', 'cd "$MOCK_SERVER_DIR"')
        if swallow_input:
            script = script.replace(' < /dev/null > "$backup"', ' > "$backup"', 1)
        with tempfile.TemporaryDirectory(prefix='bentador-deploy-test-') as temporary:
            root = Path(temporary)
            binaries = root / 'bin'
            binaries.mkdir()
            server = root / 'server'
            server.mkdir()
            for name, content in {'docker': DOCKER, 'ssh': SSH, 'git': GIT,
                                  'curl': CURL, 'sleep': '#!/bin/bash\nexit 0\n'}.items():
                executable = binaries / name
                executable.write_text(content)
                executable.chmod(0o755)
            environment = dict(os.environ, PATH=f'{binaries}:{os.environ["PATH"]}',
                               TMPDIR=temporary, RELEASE_TAG='v-test',
                               MOCK_SERVER_DIR=str(server), MOCK_LOG=str(root / 'calls'))
            environment.update({key: str(value) for key, value in failures.items()})
            result = subprocess.run(['bash'], input=script, text=True, capture_output=True,
                                    env=environment, timeout=10, cwd=server)
            source = server / 'bootstrap/app.php'
            result.source_modes = (source.stat().st_mode & 0o777,
                                   source.parent.stat().st_mode & 0o777) if source.exists() else None
            result.backup_modes = [path.stat().st_mode & 0o777
                                   for path in (server / 'backups').iterdir()]
            calls = (root / 'calls').read_text()
            return result, calls

    def test_script_finishes_backups_migration_restart_and_http_checks(self):
        result, calls = self.run_deployment()
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertIn('BENTADOR_DEPLOYMENT_COMPLETE', result.stdout)
        phases = ['stop nginx', 'pg_dump', 'pg_restore', '-czf', 'git fetch',
                  'build', '-xzf', 'artisan migrate', 'tenancy:verify',
                  'permission:cache-reset', 'up -d nginx', 'wget ', 'curl ']
        positions = [calls.index(phase) for phase in phases]
        self.assertEqual(positions, sorted(positions))
        self.assertEqual(calls.count('curl '), 3)

    def test_checkout_is_readable_and_backups_remain_private(self):
        result, _ = self.run_deployment()
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertEqual(result.source_modes, (0o644, 0o755))
        self.assertTrue(result.backup_modes)
        self.assertTrue(all(mode == 0o600 for mode in result.backup_modes))

    def test_stdin_consumption_cannot_report_a_successful_deployment(self):
        result, calls = self.run_deployment(swallow_input=True)
        self.assertNotEqual(result.returncode, 0)
        self.assertIn('stop nginx', calls)
        self.assertIn('pg_dump', calls)
        self.assertNotIn('artisan migrate', calls)
        self.assertNotIn('curl ', calls)
        self.assertNotIn('BENTADOR_DEPLOYMENT_COMPLETE', result.stdout)

    def test_failed_migration_keeps_ingress_stopped_and_fails_job(self):
        result, calls = self.run_deployment(FAIL_MIGRATION=1)
        self.assertNotEqual(result.returncode, 0)
        self.assertNotIn('up -d nginx', calls)
        self.assertNotIn('BENTADOR_DEPLOYMENT_COMPLETE', result.stdout)

    def test_internal_http_failure_fails_job_and_collects_logs(self):
        result, calls = self.run_deployment(FAIL_INTERNAL_HTTP=1)
        self.assertNotEqual(result.returncode, 0)
        self.assertIn('logs --tail=80 nginx backend frontend', calls)
        self.assertNotIn('curl ', calls)

    def test_public_proxy_failure_fails_job_even_when_containers_work(self):
        result, calls = self.run_deployment(FAIL_PUBLIC_HTTP=1)
        self.assertNotEqual(result.returncode, 0)
        self.assertIn('BENTADOR_DEPLOYMENT_COMPLETE', result.stdout)
        self.assertIn('curl ', calls)


if __name__ == '__main__':
    unittest.main(verbosity=2)
