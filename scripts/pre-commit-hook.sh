#!/bin/bash
# Git pre-commit hook for StyleCI
# Copy this to .git/hooks/pre-commit and make it executable

echo "🎨 Running StyleCI pre-commit hook..."

# Check if styleci is installed
if ! command -v styleci &> /dev/null; then
    echo "⚠️  StyleCI CLI not found, skipping style check"
    echo "Install it with: composer global require styleci/cli"
    exit 0
fi

# Get list of staged PHP files
FILES=$(git diff --cached --name-only --diff-filter=ACM | grep '\.php$' | grep -v 'vendor/' | grep -v 'bootstrap/cache/' || true)

if [ -z "$FILES" ]; then
    echo "ℹ️  No PHP files to check"
    exit 0
fi

echo "📝 Checking ${FILES[@]}"

# Run StyleCI on staged files
HAS_ERRORS=0
for FILE in $FILES; do
    if [ -f "$FILE" ]; then
        if ! styleci fix "$FILE" > /dev/null 2>&1; then
            HAS_ERRORS=1
        fi
    fi
done

# Re-stage fixed files
if [ "$HAS_ERRORS" -eq 0 ]; then
    for FILE in $FILES; do
        if [ -f "$FILE" ]; then
            git add "$FILE"
        fi
    done
    echo "✅ StyleCI fixes applied and staged"
else
    echo "⚠️  Some files couldn't be auto-fixed, check manually"
fi

exit 0
