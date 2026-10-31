---
name: commit-pr-ci
description: Use when asked to commit reviewed changes, create or update a pull request, or verify remote CI. Enforces non-main branches and follows GitHub Actions failures through resolution.
---

# Commit, pull request, and remote CI

Use this workflow when asked to finalize repository changes or check remote CI. A request to commit authorizes a local commit. A request to create a PR authorizes pushing the task branch and opening the PR. Never push directly to `main`, merge, or deploy unless explicitly asked.

## Branch and scope

1. Inspect status, branch, staged and unstaged diffs, and untracked files. Read repository instructions and identify the requested scope.
2. Never edit or commit on `main`. Create a task branch before changing files if currently on `main`, preserving user changes.
3. Include only files relevant to the request. Do not silently include unrelated changes; inspect staged files before committing.
4. Review the staged diff and run `git diff --cached --check`. Run appropriate checks required by the task and repository guidance; report only checks actually run.

## Commit and PR

1. Use a concise, behavior-focused commit subject consistent with repository history.
2. Record the full commit SHA and subject. Update existing planning or release tracking with commit, review result, validation, and PR state when applicable.
3. When a PR is requested, push the branch and open a PR against the intended base. Describe behavior, validation, migrations or operational risks, and limitations. Do not merge it.
4. If no PR was requested, do not create one; record that no PR was created when tracking calls for it.

## Remote CI

1. Use `gh` to identify the run for the exact commit or PR head. Never infer success from an older or unrelated run.
2. Wait for running jobs to finish when practical. Report run URL and ID, commit/ref, and each relevant job result.
3. Inspect failed logs and annotations. Distinguish code failures from setup, infrastructure, and missing-secret failures. Summarize the cause and fix in-scope issues on the same non-main branch.
4. After a fix, run appropriate local checks, commit it, push when the task authorizes publishing, and inspect the new CI run. Continue until required checks pass or an external blocker remains.
5. Do not report CI verified while a required job is pending, failed, unexpectedly skipped, or absent. Explain expected skips.

## Report

State branch, commit SHA, PR link or that none was created, local checks performed, and remote CI status. Link relevant runs and identify anything pending or blocked. Do not claim a merge or deployment unless it happened.
