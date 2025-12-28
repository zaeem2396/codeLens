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

## 📋 Current Features (Phase 0)

Phase 0 establishes the foundation. Currently, CodeLens:

- ✅ Installs cleanly in both Laravel and Symfony
- ✅ Detects the framework automatically
- ✅ Publishes configuration files
- ✅ Respects environment restrictions
- ✅ Provides a solid foundation for future features

### Verifying Installation

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

### Phase 1: Code Scanning & Indexing

- CLI command to scan your codebase
- Build file and symbol indexes
- No analysis, just structured metadata

```bash
# Coming soon
php artisan codelens:scan        # Laravel
php bin/console codelens:scan    # Symfony
```

### Phase 2: Metrics

- Lines of code
- Class and method counts
- Simple web UI to view raw numbers

### Phase 3: Heuristic Flags

- Gentle indicators for potential attention areas
- "Large file", "Deep nesting", etc.
- Always with explanations

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

