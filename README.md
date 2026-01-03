# 🔍 CodeLens

[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue.svg)](https://php.net)
[![Laravel](https://img.shields.io/badge/Laravel-9%2B-red.svg)](https://laravel.com)
[![Symfony](https://img.shields.io/badge/Symfony-6%2B-black.svg)](https://symfony.com)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

**A framework-aware static code intelligence tool for Laravel and Symfony applications.**

> "Help developers understand their codebase before they change it."

---

## 🎯 What is CodeLens?

CodeLens is a **code understanding system** that provides visibility into your codebase's structure, complexity, and potential risk areas. It's designed to help developers make informed decisions before making changes.

### What CodeLens IS:
- ✅ A code understanding system
- ✅ A risk & complexity visibility layer
- ✅ A developer trust–first dashboard

### What CodeLens is NOT:
- ❌ An auto-refactoring engine
- ❌ An AI decision maker
- ❌ A performance profiler

---

## ⚡ Quick Start

### Installation

```bash
composer require zaeem2396/codelens --dev
```

### Laravel Setup

```bash
php artisan vendor:publish --tag=codelens-config
```

### Symfony Setup

```php
// config/bundles.php
return [
    // ...
    CodeLens\Adapters\Symfony\CodeLensBundle::class => ['dev' => true],
];
```

Then create `config/packages/codelens.yaml` with your configuration.

---

## 🛡️ Design Principles

CodeLens follows strict design principles to maintain developer trust:

| Principle | Description |
|-----------|-------------|
| **No automatic code modification** | The tool never changes your code |
| **No absolute statements** | Uses "possible", "probable", "worth reviewing" |
| **Static analysis first** | Runtime data is optional and future-only |
| **UI over CLI dominance** | Humans should see the system |
| **Trust beats cleverness** | False positives are worse than missing insights |

---

## 📊 Development Phases

CodeLens is being developed in independent, releasable phases:

| Phase | Name | Status |
|-------|------|--------|
| 0 | Foundation & Identity | ✅ Complete |
| 1 | Code Scanning & Indexing | ✅ Complete |
| 2 | Metrics (Facts, Not Judgments) | ✅ Complete |
| 3 | Heuristic Flags (Soft Signals) | ✅ Complete |
| 4 | Usage Probability (Static Only) | 🔲 Planned |
| 5 | Risk Scoring (Explainable) | 🔲 Planned |
| 6 | UI Maturity & Navigation | 🔲 Planned |
| 7 | Framework Parity | 🔲 Planned |

---

## 🔧 Configuration

### Environment Restrictions

By default, CodeLens only activates in development environments:

```php
// Laravel - config/codelens.php
'enabled_environments' => ['local', 'development', 'staging'],
```

```yaml
# Symfony - config/packages/codelens.yaml
codelens:
    enabled_environments: ['dev', 'staging']
```

### Scan Configuration

```php
'scan_paths' => ['app'],           // Directories to analyze
'exclude_paths' => ['vendor'],     // Directories to skip
'file_extensions' => ['php'],      // File types to include
'storage_driver' => 'json',        // 'json' or 'sqlite'
```

---

## 🔍 Scanning Your Codebase

Scan your codebase to build the symbol index:

```bash
# Laravel
php artisan codelens:scan              # Full scan
php artisan codelens:scan --fresh      # Clear cache and rescan
php artisan codelens:scan --path=app   # Scan specific path

# Symfony
php bin/console codelens:scan
php bin/console codelens:scan --fresh
php bin/console codelens:scan --path=src
```

---

## 📊 Viewing Metrics

Display codebase metrics (lines of code, classes, methods, nesting depth):

```bash
# Laravel
php artisan codelens:metrics           # Full analysis
php artisan codelens:metrics --path=app  # Specific path
php artisan codelens:metrics --json    # JSON output

# Symfony
php bin/console codelens:metrics
php bin/console codelens:metrics --path=src
php bin/console codelens:metrics --json
```

---

## 🚩 Heuristic Analysis

Analyze codebase with soft heuristic rules:

```bash
# Laravel
php artisan codelens:analyze           # Full analysis
php artisan codelens:analyze --level=attention  # Only attention-level flags
php artisan codelens:analyze --rule=long_method  # Specific rule
php artisan codelens:analyze --json    # JSON output

# Symfony
php bin/console codelens:analyze
php bin/console codelens:analyze --level=attention
php bin/console codelens:analyze --rule=long_method
php bin/console codelens:analyze --json
```

### Available Rules

| Rule ID | Description |
|---------|-------------|
| `long_method` | Identifies methods with many lines |
| `deep_nesting` | Detects deeply nested control structures |
| `many_parameters` | Methods with many parameters |
| `high_conditionals` | Many conditional statements |
| `large_file` | Files with many lines |
| `multiple_classes` | Multiple classes in one file |
| `many_returns` | Many return statements |

---

## 🏗️ Architecture

```
src/
├── Core/
│   ├── CodeLens.php               # Main entry point
│   ├── Config/                    # Configuration handling
│   ├── Scanner/                   # File discovery & AST parsing
│   ├── Index/                     # Symbol registry & file index
│   ├── Metrics/                   # Code metrics calculation
│   ├── Heuristics/                # Heuristic flags & rules
│   ├── Storage/                   # JSON & SQLite backends
│   ├── Contracts/                 # Interfaces
│   └── Exceptions/                # Exception classes
├── Adapters/
│   ├── Laravel/                   # Laravel integration
│   │   ├── CodeLensServiceProvider.php
│   │   ├── LaravelAdapter.php
│   │   ├── Commands/              # Artisan commands
│   │   └── config/
│   └── Symfony/                   # Symfony integration
│       ├── CodeLensBundle.php
│       ├── SymfonyAdapter.php
│       ├── Command/               # Console commands
│       └── DependencyInjection/
└── UI/                            # Web interface (future)
```

---

## 📖 Documentation

- [Usage Guide](docs/USAGE.md) - Detailed installation and configuration

---

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

---

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

---

## 👤 Author

**zaeem2396** - [GitHub](https://github.com/zaeem2396)

---

<p align="center">
  <i>CodeLens — Understanding code, one insight at a time.</i>
</p>

