# StyleCI CLI Setup Guide

Complete guide for setting up and using StyleCI CLI to maintain consistent PHP code style in Fellowship.

## 📦 Installation

### Prerequisites
- PHP 7.4+ or PHP 8.x
- Composer

### Install StyleCI CLI Globally

```bash
composer global require styleci/cli
```

Make sure your composer global bin directory is in your PATH:

```bash
# Add to ~/.bashrc or ~/.zshrc
export PATH="$PATH:$HOME/.composer/vendor/bin"
```

### Verify Installation

```bash
styleci --version
```

You should see: `StyleCI CLI version X.X.X`

## ⚙️ Configuration

Fellowship already includes a `.styleci.yml` configuration file with:

- **Preset**: Laravel (PSR-2 based with Laravel-specific rules)
- **Enabled fixers**: 60+ code style fixers
- **Excluded paths**: vendor, node_modules, storage, public
- **Risky fixers**: Disabled by default

### Key Settings

```yaml
preset: laravel          # Laravel coding standards
risky: false            # Don't run risky transformations
```

**Excluded from checks:**
- `bootstrap/`
- `node_modules/`
- `public/`
- `resources/` (Blade templates)
- `storage/`
- `vendor/`
- `tests/`
- `database/migrations/` (keep as-is)

**Checked directories:**
- `app/`
- `config/`
- `database/` (factories, seeders)
- `routes/`

## 🚀 Usage

### Check Code Style Issues

Check all files:

```bash
styleci analyse
```

Check specific directory:

```bash
styleci analyse app/Http/Controllers
```

Check specific file:

```bash
styleci analyse app/Models/User.php
```

### Fix Code Style Issues

Fix all files:

```bash
styleci fix
```

Fix specific directory:

```bash
styleci fix app/Http/Controllers
```

Fix specific file:

```bash
styleci fix app/Models/User.php
```

### Dry Run (See what would be fixed)

```bash
styleci fix --dry-run
```

### Verbose Output

```bash
styleci analyse --verbose
styleci fix --verbose
```

### Using Custom Config

```bash
styleci analyse --config=.styleci.yml
styleci fix --config=.styleci.yml
```

## 📝 Common Workflows

### Before Committing

```bash
# Check for issues
styleci analyse

# Fix all issues
styleci fix

# Review changes
git diff

# Commit
git add .
git commit -m "style: Apply StyleCI fixes"
```

### Pre-commit Hook (Recommended)

Create `.git/hooks/pre-commit`:

```bash
#!/bin/bash

echo "Running StyleCI..."

# Get list of staged PHP files
FILES=$(git diff --cached --name-only --diff-filter=ACM | grep '\.php$')

if [ -z "$FILES" ]; then
    exit 0
fi

# Run StyleCI on staged files
styleci fix $FILES

# Re-stage fixed files
git add $FILES

exit 0
```

Make it executable:

```bash
chmod +x .git/hooks/pre-commit
```

### CI/CD Integration (GitHub Actions)

Create `.github/workflows/styleci.yml`:

```yaml
name: StyleCI

on:
  pull_request:
    paths:
      - '**.php'
  push:
    branches:
      - develop
      - main

jobs:
  style:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: 8.2
          tools: composer
      
      - name: Install StyleCI CLI
        run: composer global require styleci/cli
      
      - name: Run StyleCI
        run: |
          export PATH="$PATH:$HOME/.composer/vendor/bin"
          styleci analyse --verbose
```

## 🛠️ Helper Scripts

### scripts/check-style.sh

```bash
#!/bin/bash
# Quick style check

echo "🔍 Checking code style with StyleCI..."
styleci analyse --verbose

if [ $? -eq 0 ]; then
    echo "✅ Code style looks good!"
else
    echo "❌ Code style issues found. Run 'styleci fix' to auto-fix."
fi
```

### scripts/fix-style.sh

```bash
#!/bin/bash
# Auto-fix all style issues

echo "🔧 Fixing code style with StyleCI..."
styleci fix --verbose

echo "✅ Code style fixes applied!"
echo "📝 Review changes with 'git diff'"
```

Make them executable:

```bash
chmod +x scripts/check-style.sh
chmod +x scripts/fix-style.sh
```

Usage:

```bash
./scripts/check-style.sh
./scripts/fix-style.sh
```

## 📋 Composer Scripts

Add to `composer.json`:

```json
{
  "scripts": {
    "style:check": "styleci analyse --verbose",
    "style:fix": "styleci fix --verbose",
    "pre-commit": [
      "@style:check"
    ]
  }
}
```

Then run:

```bash
composer style:check
composer style:fix
```

## 🎯 What StyleCI Fixes

### Alignment
- ✅ Align double arrows in arrays
- ✅ Consistent spacing around operators

### Imports
- ✅ Alphabetically order use statements
- ✅ Remove unused imports
- ✅ Remove leading slashes from imports

### Spacing
- ✅ Blank lines after namespace
- ✅ No blank lines after class opening
- ✅ Proper spacing around operators

### PHPDoc
- ✅ Align PHPDoc tags
- ✅ Remove empty PHPDoc blocks
- ✅ Add missing @param annotations
- ✅ Proper PHPDoc formatting

### Code Quality
- ✅ Use single quotes for strings
- ✅ Short array syntax `[]` instead of `array()`
- ✅ Remove useless else statements
- ✅ Remove unreachable code
- ✅ Proper visibility declarations

## 🚫 What StyleCI Won't Touch

- ❌ Database migrations (excluded)
- ❌ Blade templates (not PHP files)
- ❌ Vendor code (excluded)
- ❌ Generated files (Stubs, etc.)
- ❌ Test files (excluded by default)

## 🔧 Customization

### Enable More Fixers

Edit `.styleci.yml`:

```yaml
enabled:
  - your_fixer_name
```

### Disable Specific Fixers

```yaml
disabled:
  - length_ordered_imports
```

### Change Preset

Available presets:
- `laravel` (default)
- `psr2`
- `psr12`
- `symfony`
- `recommended`

```yaml
preset: psr12
```

### Exclude Additional Paths

```yaml
finder:
  exclude:
    - "custom-folder"
  not-path:
    - "app/Legacy"
```

## 🐛 Troubleshooting

### "Command not found"

Make sure composer bin is in PATH:

```bash
which styleci
# Should output: /home/user/.composer/vendor/bin/styleci
```

Add to PATH if needed:

```bash
export PATH="$PATH:$HOME/.composer/vendor/bin"
```

### "No files to check"

Verify finder configuration in `.styleci.yml` isn't excluding too much.

### "Permission denied"

Make sure you own the files:

```bash
sudo chown -R $USER:$USER .
```

### Large Codebase Performance

Use specific paths:

```bash
styleci fix app/Http/Controllers
styleci fix app/Models
```

## 📚 Resources

- **StyleCI CLI**: https://github.com/StyleCI/CLI
- **StyleCI Docs**: https://docs.styleci.io/
- **Laravel Style Guide**: https://laravel.com/docs/contributions#coding-style
- **PSR-12**: https://www.php-fig.org/psr/psr-12/

## 🎯 Best Practices

1. **Run before every commit**: Use pre-commit hook
2. **Fix in batches**: Don't mix style fixes with feature changes
3. **Review changes**: Always review what StyleCI changed
4. **Team consistency**: Everyone should use the same config
5. **CI integration**: Catch issues in PR reviews
6. **Gradual adoption**: Fix new code first, legacy code later

## 🚀 Quick Start

1. Install StyleCI CLI globally
2. Run `styleci analyse` to see issues
3. Run `styleci fix` to auto-fix
4. Review changes with `git diff`
5. Commit: `git commit -m "style: Apply StyleCI fixes"`

## ✅ Benefits

- ✨ Consistent code style across the team
- 🔍 Catch style issues before code review
- ⚡ Auto-fix most issues automatically
- 📝 Follow Laravel best practices
- 🎯 Focus on logic, not formatting debates

---

**Note**: StyleCI CLI is the command-line version. There's also a web service at styleci.io that integrates with GitHub, but the CLI gives you local control and is free to use.
