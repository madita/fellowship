#!/bin/bash

# Exit on any error
set -e

echo "Deploying application ..."

# Put application in maintenance mode
php artisan down --message="Updating application" --retry=60 || echo "Application already down"

# Pull latest changes from git
echo "Pulling latest changes..."
git checkout develop
git pull origin develop

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

echo "✅ Deployment completed successfully!"
