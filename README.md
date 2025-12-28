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
| 1 | Code Scanning & Indexing | 🔲 Planned |
| 2 | Metrics (Facts, Not Judgments) | 🔲 Planned |
| 3 | Heuristic Flags (Soft Signals) | 🔲 Planned |
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
```

---

## 🏗️ Architecture

```
src/
├── Core/                          # Framework-agnostic core
│   ├── CodeLens.php               # Main entry point
│   ├── Config/                    # Configuration handling
│   ├── Contracts/                 # Interfaces
│   └── Exceptions/                # Exception classes
├── Adapters/
│   ├── Laravel/                   # Laravel integration
│   │   ├── CodeLensServiceProvider.php
│   │   ├── LaravelAdapter.php
│   │   └── config/
│   └── Symfony/                   # Symfony integration
│       ├── CodeLensBundle.php
│       ├── SymfonyAdapter.php
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

