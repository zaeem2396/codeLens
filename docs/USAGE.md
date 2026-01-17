# CodeLens Usage Guide

CodeLens is a framework-aware static code intelligence tool for Laravel and Symfony applications. It helps developers understand their codebase before making changes.

---

## 📦 Installation

### Requirements

- PHP 8.1 or higher
- Laravel 9+ or Symfony 6+

### Via Composer

```bash
composer require zaeem2396/codelens --dev
```

> **Note:** We recommend installing as a dev dependency since CodeLens is designed for development and staging environments only.

---

## 🔧 Configuration

### Laravel

1. **Publish the configuration file:**

```bash
php artisan vendor:publish --tag=codelens-config
```

2. **Configure in `config/codelens.php`:**

```php
<?php

return [
    // Environments where CodeLens is active
    'enabled_environments' => ['local', 'development', 'staging'],

    // Directories to scan (relative to base path)
    'scan_paths' => ['app'],

    // Directories to exclude
    'exclude_paths' => [
        'vendor',
        'node_modules',
        'storage',
        'bootstrap/cache',
        'public',
    ],

    // File extensions to analyze
    'file_extensions' => ['php'],

    // Storage driver: 'json' or 'sqlite'
    'storage_driver' => 'json',

    // Cache directory (relative to storage/framework)
    'cache_directory' => 'codelens',

    // Web UI settings
    'ui_enabled' => true,
    'ui_route_prefix' => 'codelens',
    'ui_middleware' => ['web'],
];
```

### Symfony

1. **Register the bundle in `config/bundles.php`:**

```php
<?php

return [
    // ... other bundles
    CodeLens\Adapters\Symfony\CodeLensBundle::class => ['dev' => true, 'staging' => true],
];
```

2. **Create configuration file at `config/packages/codelens.yaml`:**

```yaml
codelens:
    enabled_environments:
        - dev
        - staging
    
    scan_paths:
        - src
    
    exclude_paths:
        - vendor
        - var
        - node_modules
        - public
        - tests
    
    file_extensions:
        - php
    
    storage_driver: json
    cache_directory: codelens
    
    ui_enabled: true
    ui_route_prefix: codelens
    ui_middleware: []
```

---

## 🌍 Environment Restrictions

CodeLens is designed to run **only in non-production environments** by default. This is a safety feature.

### Default Enabled Environments

- `local` (Laravel)
- `development` / `dev` (both)
- `staging` (both)

### Enabling in Other Environments

If you need to enable CodeLens in other environments (not recommended for production), modify your configuration:

```php
// Laravel - config/codelens.php
'enabled_environments' => ['local', 'development', 'staging', 'your-environment'],
```

```yaml
# Symfony - config/packages/codelens.yaml
codelens:
    enabled_environments:
        - dev
        - staging
        - your-environment
```

> ⚠️ **Warning:** Enabling CodeLens in production is not recommended. The tool is designed for code understanding during development.

---

## 📋 Current Features

### Phase 0: Foundation
- ✅ Installs cleanly in both Laravel and Symfony
- ✅ Detects the framework automatically
- ✅ Publishes configuration files
- ✅ Respects environment restrictions

### Phase 1: Code Scanning & Indexing
- ✅ AST-based parsing of PHP files
- ✅ File discovery and indexing
- ✅ Symbol extraction (classes, interfaces, traits, enums, methods, properties)
- ✅ Incremental scanning with checksum-based change detection
- ✅ JSON and SQLite storage backends
- ✅ CLI commands for both frameworks

### Phase 2: Metrics
- ✅ Lines of code (file-level, with/without comments)
- ✅ Class, interface, trait, enum counts
- ✅ Method-level metrics (line count, nesting depth, conditionals, loops)
- ✅ CLI commands: `codelens:metrics`
- ✅ Configurable output with `--limit` and `--all` flags

### Phase 3: Heuristic Flags
- ✅ Configurable heuristic rules with thresholds
- ✅ Soft language (never "bad" or "problem")
- ✅ Rules: long method, deep nesting, many parameters, high conditionals
- ✅ Rules: large file, multiple classes per file, many returns
- ✅ CLI command: `codelens:analyze`

---

## 🔍 Scanning Your Codebase

### Basic Scan

Run a full scan of your codebase:

```bash
# Laravel
php artisan codelens:scan

# Symfony
php bin/console codelens:scan
```

### Fresh Scan

Clear all cached data and perform a complete rescan:

```bash
# Laravel
php artisan codelens:scan --fresh

# Symfony
php bin/console codelens:scan --fresh
```

### Scan Specific Path

Scan only a specific directory or file:

```bash
# Laravel
php artisan codelens:scan --path=app/Services

# Symfony
php bin/console codelens:scan --path=src/Service
```

### Scan Output

The scanner will display:

```
🔍 CodeLens Scanner
==================

  Starting scan...

📊 Scan Results
---------------

  Files:
    • Scanned:   42
    • Unchanged: 0
    • Removed:   0
    • Total:     42

  Symbols:
    • Classes:    35
    • Interfaces: 5
    • Traits:     2
    • Enums:      0
    • Total:      42

  Duration: 1.23s

  ✅ Scan completed successfully!
```

---

## 📁 Storage

CodeLens stores scan results in your storage directory:

**Laravel:** `storage/framework/codelens/`  
**Symfony:** `var/cache/{env}/codelens/`

### Storage Drivers

Configure the storage driver in your configuration:

```php
// JSON storage (default) - human-readable, good for smaller projects
'storage_driver' => 'json',

// SQLite storage - faster lookups, better for large codebases
'storage_driver' => 'sqlite',
```

---

## 🔬 What Gets Indexed

### File Information
- Absolute and relative paths
- File size and line count
- Last modified timestamp
- SHA-256 checksum (for change detection)

### Symbols
- **Classes:** name, namespace, extends, implements, traits, methods, properties
- **Interfaces:** name, namespace, extends, methods
- **Traits:** name, namespace, methods, properties
- **Enums:** name, namespace, cases, backed type, methods
- **Methods:** visibility, static, abstract, final, parameters, return type
- **Properties:** visibility, type, static, readonly

---

## ✅ Verifying Installation

#### Laravel

```php
// In a controller or tinker
$codelens = app(\CodeLens\Core\CodeLens::class);

echo $codelens->getFrameworkName();    // "Laravel"
echo $codelens->getFrameworkVersion(); // e.g., "10.x.x"
echo $codelens->isEnabled();           // true/false based on environment
```

#### Symfony

```php
// In a controller
public function index(\CodeLens\Core\CodeLens $codelens): Response
{
    dump($codelens->getFrameworkName());    // "Symfony"
    dump($codelens->getFrameworkVersion()); // e.g., "6.x.x"
    dump($codelens->isEnabled());           // true/false
    
    // ...
}
```

---

## 🚀 Upcoming Features

### Phase 4: Usage Probability

- Static analysis of code usage
- Route, controller, job, and event references
- Confidence levels (never absolutes)

### Phase 5: Risk Scoring

- Combine metrics into explainable risk indicators
- Transparent scoring methodology
- Always showing "how this score was calculated"

### Phase 6: UI Maturity

- Full dashboard experience
- File explorer
- Detailed insights per file

### Phase 7: Framework Parity

- Full Symfony feature parity
- Same UI, same insights for both frameworks

---

## 🎯 Design Philosophy

CodeLens follows these core principles:

1. **No automatic code modification** — We never change your code
2. **No absolute statements** — We use "possible", "probable", "worth reviewing"
3. **Static analysis first** — Runtime data is optional
4. **UI over CLI** — Developers should see the system
5. **Trust beats cleverness** — False positives are worse than missing insights

---

## 🐛 Troubleshooting

### CodeLens is not enabled

Check that:
1. Your current environment is in the `enabled_environments` list
2. The configuration file is properly published/created
3. The service provider/bundle is registered

### Configuration not loading

**Laravel:**
```bash
php artisan config:clear
php artisan cache:clear
```

**Symfony:**
```bash
php bin/console cache:clear
```

---

## 📝 Contributing

Contributions are welcome! Please see the repository for guidelines.

---

## 📄 License

MIT License - see LICENSE file for details.

