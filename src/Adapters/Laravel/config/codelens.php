<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Enabled Environments
    |--------------------------------------------------------------------------
    |
    | CodeLens will only be active in these environments. This is a safety
    | measure to prevent accidental exposure in production environments.
    | By default, CodeLens is enabled only in local and staging environments.
    |
    */
    'enabled_environments' => ['local', 'development', 'staging'],

    /*
    |--------------------------------------------------------------------------
    | Scan Paths
    |--------------------------------------------------------------------------
    |
    | Directories to scan for PHP files, relative to your application's
    | base path. Typically, you'll want to scan your 'app' directory.
    |
    */
    'scan_paths' => ['app'],

    /*
    |--------------------------------------------------------------------------
    | Exclude Paths
    |--------------------------------------------------------------------------
    |
    | Directories to exclude from scanning. These paths are relative to
    | your application's base path.
    |
    */
    'exclude_paths' => [
        'vendor',
        'node_modules',
        'storage',
        'bootstrap/cache',
        'public',
    ],

    /*
    |--------------------------------------------------------------------------
    | File Extensions
    |--------------------------------------------------------------------------
    |
    | Only files with these extensions will be analyzed.
    |
    */
    'file_extensions' => ['php'],

    /*
    |--------------------------------------------------------------------------
    | Storage Driver
    |--------------------------------------------------------------------------
    |
    | The driver used to store scan results and metadata.
    | Supported: "json", "sqlite"
    |
    */
    'storage_driver' => 'json',

    /*
    |--------------------------------------------------------------------------
    | Cache Directory
    |--------------------------------------------------------------------------
    |
    | The directory name where CodeLens stores its cache and data files.
    | This is relative to the storage/framework directory.
    |
    */
    'cache_directory' => 'codelens',

    /*
    |--------------------------------------------------------------------------
    | UI Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for the CodeLens web interface.
    |
    */
    'ui_enabled' => true,
    'ui_route_prefix' => 'codelens',
    'ui_middleware' => ['web'],
];
