#!/bin/bash
# Quick code style check with StyleCI

set -e

echo "🔍 Checking code style with StyleCI..."
echo ""

# Check if styleci is installed
if ! command -v styleci &> /dev/null; then
    echo "❌ StyleCI CLI not found!"
    echo "Install it with: composer global require styleci/cli"
    echo "Add to PATH: export PATH=\"\$PATH:\$HOME/.composer/vendor/bin\""
    exit 1
fi

# Run StyleCI
if styleci analyse --verbose; then
    echo ""
    echo "✅ Code style looks good!"
    exit 0
else
    echo ""
    echo "❌ Code style issues found!"
    echo "Run './scripts/fix-style.sh' or 'styleci fix' to auto-fix."
    exit 1
fi
