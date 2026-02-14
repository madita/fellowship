# StyleCI Quick Reference Card

## 🚀 Installation (One-time)

```bash
composer global require styleci/cli
export PATH="$PATH:$HOME/.composer/vendor/bin"
```

## 📝 Daily Usage

| Command | Description |
|---------|-------------|
| `styleci analyse` | Check all files for style issues |
| `styleci fix` | Auto-fix all style issues |
| `styleci fix --dry-run` | See what would be fixed (no changes) |
| `styleci analyse app/Models` | Check specific directory |
| `styleci fix app/Http/Controllers/UserController.php` | Fix specific file |

## 🛠️ Helper Scripts

```bash
# Check code style
./scripts/check-style.sh

# Auto-fix all issues
./scripts/fix-style.sh

# Install pre-commit hook
cp scripts/pre-commit-hook.sh .git/hooks/pre-commit
chmod +x .git/hooks/pre-commit
```

## 🔧 Composer Shortcuts

Add to `composer.json`:

```json
{
  "scripts": {
    "style:check": "styleci analyse --verbose",
    "style:fix": "styleci fix --verbose"
  }
}
```

Then use:

```bash
composer style:check
composer style:fix
```

## ✅ Before Committing

```bash
# 1. Fix style issues
styleci fix

# 2. Review changes
git diff

# 3. Stage and commit
git add .
git commit -m "style: Apply StyleCI fixes"
```

## 🎯 Common Fixes

- ✅ Use single quotes `'hello'` instead of `"hello"`
- ✅ Short array syntax `[]` instead of `array()`
- ✅ Remove unused imports
- ✅ Alphabetize use statements
- ✅ Proper spacing around operators
- ✅ Align PHPDoc blocks
- ✅ Trailing commas in multi-line arrays
- ✅ No blank lines after class opening `{`

## 🚫 Excluded Paths

StyleCI won't touch:
- `vendor/`
- `node_modules/`
- `storage/`
- `public/`
- `bootstrap/cache/`
- `database/migrations/`
- `*.blade.php` (Blade templates)

## 📊 Check Status

```bash
# Verbose output
styleci analyse --verbose

# Exit codes:
# 0 = No issues
# 1 = Issues found
```

## 🔍 Tips

- Run `styleci fix` before creating PR
- Use pre-commit hook for automatic checks
- Review changes before committing
- Don't mix style fixes with feature changes
- CI will check style on PRs automatically

## 📚 Learn More

Read full guide: `STYLECI_SETUP.md`

---

**Quick fix workflow**: `styleci fix` → `git diff` → `git add .` → `git commit -m "style: fixes"`
