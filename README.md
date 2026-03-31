# LaraImporter

Universal database import wizard for Laravel. Upload CSV, Excel, or JSON files and import data with column mapping, related table resolution, many-to-many pivot support, and background queue processing.

## Features

- **Multi-format**: CSV, TXT, JSON, XLS, XLSX, ODS
- **Column mapping**: Auto-suggest with manual override
- **Related tables**: Auto-detect FK relationships, find-or-create linked records
- **Many-to-many**: Pivot table detection, comma-separated value splitting
- **Default values**: Set defaults for required unmapped columns
- **Duplicate handling**: Skip, update, or always create
- **Background queue**: Large imports processed via Laravel queue with real-time progress
- **MongoDB support**: Optional, works when ext-mongodb is installed
- **Multi-language**: English and German included, extendable
- **Permission-ready**: Configurable permission gates
- **Flexible layout**: Works with any admin panel layout (component or extends)

## Requirements

- PHP 8.2+
- Laravel 11 or 12
- Alpine.js & Tailwind CSS (see [Frontend Dependencies](#frontend-dependencies) if not installed)

## Installation

### Via Packagist (recommended)

```bash
composer require mg/laraimporter
```

### Via GitHub (without Packagist)

Add the repository to your project's `composer.json`:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/YOUR_USERNAME/laraimporter"
        }
    ]
}
```

Then install:

```bash
composer require mg/laraimporter:dev-main
```

### Via Local Path (for development)

If the package is on your local machine:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../laraimporter"
        }
    ]
}
```

```bash
composer require mg/laraimporter:*
```

### After Installation

```bash
# Publish configuration
php artisan vendor:publish --tag=laraimporter-config

# Run migration
php artisan migrate
```

### Optional Dependencies

```bash
# For Excel/ODS file support (XLS, XLSX, ODS)
composer require phpoffice/phpspreadsheet

# For MongoDB support
composer require mongodb/laravel-mongodb
```

## Frontend Dependencies

LaraImporter requires **Alpine.js** and **Tailwind CSS**. If your project doesn't have them:

### Option 1: CDN (quickest, no build step)

Add these to your layout's `<head>`:

```html
<!-- Tailwind CSS -->
<script src="https://cdn.tailwindcss.com"></script>

<!-- Alpine.js -->
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
```

### Option 2: NPM (recommended for production)

```bash
npm install alpinejs tailwindcss @tailwindcss/forms autoprefixer postcss
```

**resources/js/app.js:**
```js
import Alpine from 'alpinejs';
window.Alpine = Alpine;
Alpine.start();
```

**resources/css/app.css:**
```css
@tailwind base;
@tailwind components;
@tailwind utilities;
```

**tailwind.config.js:**
```js
export default {
    content: [
        './resources/**/*.blade.php',
        './vendor/mg/laraimporter/resources/views/**/*.blade.php',  // Include package views
    ],
    theme: { extend: {} },
    plugins: [require('@tailwindcss/forms')],
};
```

> **Important:** If using Tailwind with a build step, add the package views path to your `content` array so Tailwind picks up the classes used in the wizard.

### Axios (for AJAX requests)

The wizard uses Axios for API calls. If not already installed:

```bash
npm install axios
```

```js
// resources/js/bootstrap.js
import axios from 'axios';
window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// CSRF token (required for POST requests)
let token = document.head.querySelector('meta[name="csrf-token"]');
if (token) {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token.content;
}
```

Make sure your layout has the CSRF meta tag:
```html
<meta name="csrf-token" content="{{ csrf_token() }}">
```

## Configuration

Edit `config/laraimporter.php`:

```php
return [
    // Database connection mode
    // 'default'  → uses app's DB, skips connection step
    // 'external' → shows DB connection form (Step 1)
    // 'mysql2'   → uses a specific connection from config/database.php
    'connection' => 'default',

    // URL prefix for routes
    'route_prefix' => 'admin/import',

    // Middleware for all import routes
    'middleware' => ['web', 'auth'],

    // Your admin layout blade component
    'layout' => 'admin-layout',
    'layout_type' => 'component',  // 'component' or 'extends'
    'title_slot' => 'title',

    // Rows above this suggest background queue
    'queue_threshold' => 1000,

    // Max upload size in KB (50MB)
    'max_upload_size' => 51200,

    // Drivers shown in connection form (external mode only)
    'drivers' => ['mysql', 'pgsql', 'mongodb'],

    // Permission names (null = no check)
    'permissions' => [
        'view'    => null,  // e.g., 'view imports'
        'create'  => null,  // e.g., 'create imports'
        'execute' => null,  // e.g., 'execute imports'
    ],
];
```

## Usage

### Access the wizard

Navigate to your configured route prefix (default: `/admin/import`).

### Routes

| Route | Name | Description |
|-------|------|-------------|
| `GET /admin/import` | `laraimporter.index` | Import wizard |
| `GET /admin/import/history` | `laraimporter.history` | Import history |

### Add to your sidebar

```blade
<a href="{{ route('laraimporter.index') }}">New Import</a>
<a href="{{ route('laraimporter.history') }}">Import History</a>
```

### With permissions (Spatie example)

```php
// config/laraimporter.php
'permissions' => [
    'view'    => 'view imports',
    'create'  => 'create imports',
    'execute' => 'execute imports',
],
```

```blade
@can('create imports')
    <a href="{{ route('laraimporter.index') }}">New Import</a>
@endcan
@can('view imports')
    <a href="{{ route('laraimporter.history') }}">Import History</a>
@endcan
```

## How It Works

### Step 1: Database Connection (external mode only)
Enter credentials for MySQL, PostgreSQL, or MongoDB. Skipped when `connection` is set to `'default'` or a specific connection name.

### Step 2: Upload File
Drag & drop or browse for CSV, JSON, or Excel files. Preview first 10 rows. Large files (above queue threshold) will suggest background processing.

### Step 3: Select Target Table
Pick the primary table. System auto-detects:
- **Linked tables** (via foreign keys)
- **Pivot tables** (many-to-many, detected by FK constraints or naming convention like `product_has_categories`)

### Step 4: Map Columns
- Map file columns → database columns (auto-suggested by name matching)
- Choose target from primary table, linked tables, or any table in the database
- Set duplicate handling (skip/update/create)
- Set default values for required unmapped columns
- Map comma-separated columns to many-to-many pivot relationships

### Step 5: Preview & Import
- Dry-run preview of first 10 rows showing how data will be inserted
- Choose direct or background queue processing
- Real-time progress bar for queued imports with live stats

## Customization

### Publish views

```bash
php artisan vendor:publish --tag=laraimporter-views
```

Views will be copied to `resources/views/vendor/laraimporter/` where you can customize them.

### Publish translations

```bash
php artisan vendor:publish --tag=laraimporter-lang
```

### Add your own language

Create `resources/lang/vendor/laraimporter/fr/messages.php` (or any locale) by copying from the English file and translating.

### Layout examples

**Blade component layout** (Laravel Breeze, Jetstream, etc.):
```php
'layout' => 'app-layout',        // <x-app-layout>
'layout_type' => 'component',
'title_slot' => 'header',        // depends on your layout
```

**Traditional @extends layout** (AdminLTE, custom layouts):
```php
'layout' => 'layouts.admin',     // @extends('layouts.admin')
'layout_type' => 'extends',
'title_slot' => 'title',         // @section('title')
```

## Background Queue Setup

For large file imports, LaraImporter can process data in the background using Laravel's queue system.

1. Configure a queue driver in `.env`:
```
QUEUE_CONNECTION=database
```

2. Create the queue tables (if using database driver):
```bash
php artisan queue:table
php artisan migrate
```

3. Run the queue worker:
```bash
php artisan queue:work
```

The wizard will automatically suggest background processing for files above the `queue_threshold` (default: 1000 rows). Users can also manually choose queue processing for any import.

## Troubleshooting

### "Class not found" after installation
```bash
composer dump-autoload
php artisan package:discover
```

### Views not updating after publish
```bash
php artisan view:clear
```

### Permission cache issues (Spatie)
```bash
php artisan permission:cache-reset
```

### Excel files not supported
```bash
composer require phpoffice/phpspreadsheet
```

## License

MIT
