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
- Alpine.js (in your frontend)
- Tailwind CSS (in your frontend)

## Installation

```bash
composer require mg/laraimporter
```

Publish config:

```bash
php artisan vendor:publish --tag=laraimporter-config
```

Run migration:

```bash
php artisan migrate
```

### Optional

For Excel/ODS support:
```bash
composer require phpoffice/phpspreadsheet
```

For MongoDB support:
```bash
composer require mongodb/laravel-mongodb
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
```

## How It Works

### Step 1: Database Connection (external mode only)
Enter credentials for MySQL, PostgreSQL, or MongoDB.

### Step 2: Upload File
Drag & drop or browse for CSV, JSON, or Excel files. Preview first 10 rows.

### Step 3: Select Target Table
Pick the primary table. System auto-detects:
- **Linked tables** (via foreign keys)
- **Pivot tables** (many-to-many, detected by FK or naming convention)

### Step 4: Map Columns
- Map file columns → database columns (auto-suggested)
- Set duplicate handling (skip/update/create)
- Set default values for required unmapped columns
- Map comma-separated columns to many-to-many relationships

### Step 5: Preview & Import
- Dry-run preview of first 10 rows
- Choose direct or background queue processing
- Real-time progress for queued imports

## Customization

### Publish views
```bash
php artisan vendor:publish --tag=laraimporter-views
```

Views will be in `resources/views/vendor/laraimporter/`.

### Publish translations
```bash
php artisan vendor:publish --tag=laraimporter-lang
```

### Layout examples

**Blade component layout:**
```php
'layout' => 'admin-layout',
'layout_type' => 'component',
'title_slot' => 'title',
```

**Traditional @extends layout:**
```php
'layout' => 'layouts.admin',
'layout_type' => 'extends',
'title_slot' => 'title',
```

## License

MIT
