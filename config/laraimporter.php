<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Database Connection Mode
    |--------------------------------------------------------------------------
    |
    | 'default'    - Uses the app's default DB connection. Skips Step 1 (no connection form).
    | 'external'   - Shows Step 1 so user can enter any external DB credentials.
    | 'connection_name' - Uses a specific connection from config/database.php. Skips Step 1.
    |
    */
    'connection' => 'default',

    /*
    |--------------------------------------------------------------------------
    | Route Prefix
    |--------------------------------------------------------------------------
    |
    | URL prefix for all import routes.
    | e.g., 'admin/import' → /admin/import, /admin/import/history, etc.
    |
    */
    'route_prefix' => 'admin/import',

    /*
    |--------------------------------------------------------------------------
    | Middleware
    |--------------------------------------------------------------------------
    |
    | Middleware applied to all import routes.
    | Adjust based on your auth/permission setup.
    |
    */
    'middleware' => ['web', 'auth'],

    /*
    |--------------------------------------------------------------------------
    | Layout
    |--------------------------------------------------------------------------
    |
    | The Blade layout/component the import views extend.
    | Set to your admin layout. Views use <x-dynamic-component :component="layout">
    |
    | Examples:
    |   'admin-layout'          → <x-admin-layout>
    |   'layouts.admin'         → @extends('layouts.admin')
    |
    */
    'layout' => 'admin-layout',

    /*
    |--------------------------------------------------------------------------
    | Layout Type
    |--------------------------------------------------------------------------
    |
    | 'component' - Uses <x-dynamic-component> (Blade components)
    | 'extends'   - Uses @extends (traditional Blade layouts)
    |
    */
    'layout_type' => 'component',

    /*
    |--------------------------------------------------------------------------
    | Title Slot
    |--------------------------------------------------------------------------
    |
    | The slot name used for page titles in your layout.
    | Common values: 'title', 'header', 'page_title'
    |
    */
    'title_slot' => 'title',

    /*
    |--------------------------------------------------------------------------
    | Queue Threshold
    |--------------------------------------------------------------------------
    |
    | Number of rows above which the wizard suggests background queue processing.
    | Set to 0 to always offer queue, or a very high number to disable.
    |
    */
    'queue_threshold' => 1000,

    /*
    |--------------------------------------------------------------------------
    | Max Upload Size (KB)
    |--------------------------------------------------------------------------
    |
    | Maximum file upload size in kilobytes. Default: 50MB (51200 KB).
    | Also limited by PHP's upload_max_filesize and post_max_size.
    |
    */
    'max_upload_size' => 51200,

    /*
    |--------------------------------------------------------------------------
    | Supported Database Drivers
    |--------------------------------------------------------------------------
    |
    | Which database drivers to show in the connection form (Step 1).
    | Only relevant when connection = 'external'.
    |
    */
    'drivers' => ['mysql', 'pgsql', 'mongodb'],

    /*
    |--------------------------------------------------------------------------
    | Permissions
    |--------------------------------------------------------------------------
    |
    | Permission names for access control. Set to null to disable permission checks.
    | Works with Spatie, Bouncer, or any Gate-based system.
    |
    */
    'permissions' => [
        'view'    => null,  // e.g., 'view imports'
        'create'  => null,  // e.g., 'create imports'
        'execute' => null,  // e.g., 'execute imports'
    ],

];
