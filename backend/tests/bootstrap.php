<?php

use Illuminate\Filesystem\Filesystem;

require_once __DIR__.'/../vendor/autoload.php';

// Each runner owns its runtime files, independently of Docker/application storage.
$runtime = sys_get_temp_dir().'/bentador-tests-'.bin2hex(random_bytes(12));
foreach (['app/private', 'app/public', 'framework/cache/data', 'framework/sessions', 'framework/views', 'logs', 'bootstrap'] as $directory) {
    if (! mkdir($runtime.'/'.$directory, 0700, true) && ! is_dir($runtime.'/'.$directory)) {
        throw new RuntimeException('Cannot create test runtime directory: '.$directory);
    }
}

foreach ([
    'LARAVEL_STORAGE_PATH' => $runtime,
    'APP_SERVICES_CACHE' => $runtime.'/bootstrap/services.php',
    'APP_PACKAGES_CACHE' => $runtime.'/bootstrap/packages.php',
    'APP_CONFIG_CACHE' => $runtime.'/bootstrap/config.php',
    'APP_ROUTES_CACHE' => $runtime.'/bootstrap/routes.php',
    'APP_EVENTS_CACHE' => $runtime.'/bootstrap/events.php',
    'VIEW_COMPILED_PATH' => $runtime.'/framework/views',
] as $name => $value) {
    // Export for checkout subprocesses as well as Laravel's environment repository.
    putenv($name.'='.$value);
    $_ENV[$name] = $_SERVER[$name] = $value;
}

register_shutdown_function(static function () use ($runtime): void {
    (new Filesystem)->deleteDirectory($runtime);
});
