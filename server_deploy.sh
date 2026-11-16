#!/bin/bash

# Exit on any error
set -e

echo "Deploying application ..."

# A deploy that dies halfway leaves the site in maintenance mode, which
# is worse than the old version still being up: nobody can look at it
# and the page does not say why. Bring it back whatever happens.
trap 'php artisan up >/dev/null 2>&1 || true' EXIT

# This account has a disk quota, and a deploy that runs into it fails
# wherever something happens to write first - a truncated .env, a
# half-unpacked dependency, an asset that built to nothing. Printing it
# here means the log says what went wrong rather than where it landed.
echo "Space available:"
df -h . || true
quota -s 2>/dev/null || true

# Put application in maintenance mode
php artisan down --message="Updating application" --retry=60 || echo "Application already down"

# Pull latest changes from git
echo "Pulling latest changes..."
git checkout develop
git pull origin develop

# Drop what the last deploys left behind, before asking for more room.
#
# The log first: the `single` channel writes one laravel.log and never
# rotates it, so on a box that has been running a while it is usually
# the largest file in the account. config/logging.php stacks `daily`
# now, which keeps a fortnight - this clears the rotated ones it has
# finished with, and the unrotated one left over from before.
echo "Clearing out what is no longer needed..."
find storage/logs -name '*.log' -type f -mtime +14 -delete 2>/dev/null || true

# Keeping its last pages needs room for them. Where there is not even
# that, empty it instead: a log we cannot read back is better than a
# deploy that cannot get past tidying up.
if [ -f storage/logs/laravel.log ]; then
    if tail -n 2000 storage/logs/laravel.log > storage/logs/laravel.log.keep 2>/dev/null; then
        mv storage/logs/laravel.log.keep storage/logs/laravel.log
    else
        rm -f storage/logs/laravel.log.keep
        : > storage/logs/laravel.log
    fi
fi

# One file per visitor, and the bots get one each too
find storage/framework/sessions -type f -mtime +14 -delete 2>/dev/null || true

# Install/update composer dependencies
echo "Installing composer dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction

# Check if Node.js/npm is available
if ! command -v npm &> /dev/null; then
    echo "⚠️  npm not found! Installing Node.js..."

    # Install Node.js using NodeSource repository (Ubuntu/Debian)
    if command -v apt-get &> /dev/null; then
        curl -fsSL https://deb.nodesource.com/setup_lts.x | sudo -E bash -
        sudo apt-get install -y nodejs
    # Install Node.js using yum (CentOS/RHEL)
    elif command -v yum &> /dev/null; then
        curl -fsSL https://rpm.nodesource.com/setup_lts.x | sudo bash -
        sudo yum install -y nodejs
    else
        echo "❌ Could not install Node.js automatically. Please install manually."
        echo "Visit: https://nodejs.org/en/download/"
        exit 1
    fi

    echo "✅ Node.js installed: $(node --version)"
    echo "✅ npm installed: $(npm --version)"
fi

# Install/update npm dependencies
echo "Installing npm dependencies..."
npm ci --only=production

# Build frontend assets
echo "Building frontend assets..."
npm run build

# node_modules stays: tools/yjs-server.mjs runs from it, which is what
# the sandbox edits through. Only the caches go.
echo "Dropping the download caches..."
composer clear-cache --quiet 2>/dev/null || true
npm cache clean --force >/dev/null 2>&1 || true

# Run database migrations
echo "Running database migrations..."
php artisan migrate --force

# Clear application cache
echo "Clearing application cache..."
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Cache application configuration
echo "Caching application configuration..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Set proper permissions
echo "Setting proper permissions..."
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || \
    sudo chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || \
    echo "⚠️  Failed to change ownership. Please ensure proper permissions manually."

chmod -R 775 storage bootstrap/cache 2>/dev/null || \
    sudo chmod -R 775 storage bootstrap/cache 2>/dev/null || \
    echo "⚠️  Failed to change permissions. Please ensure proper permissions manually."

# Bring application back online
php artisan up

echo "Space left:"
df -h . || true

echo "✅ Deployment completed successfully!"
