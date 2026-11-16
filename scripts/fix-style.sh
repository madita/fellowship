#!/bin/bash
# Auto-fix code style issues with StyleCI

set -e

echo "🔧 Fixing code style with StyleCI..."
echo ""

# Check if styleci is installed
if ! command -v styleci &> /dev/null; then
    echo "❌ StyleCI CLI not found!"
    echo "Install it with: composer global require styleci/cli"
    echo "Add to PATH: export PATH=\"\$PATH:\$HOME/.composer/vendor/bin\""
    exit 1
fi

# Run StyleCI fixer
styleci fix --verbose

echo ""
echo "✅ Code style fixes applied!"
echo ""
echo "📝 Review changes with: git diff"
echo "📦 Stage changes with: git add ."
echo "💾 Commit changes with: git commit -m 'style: Apply StyleCI fixes'"
