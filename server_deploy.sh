#!/bin/sh
set -e

echo "Deploying application ..."

# Enter maintenance mode
(php artisan down --message 'The app is being (quickly!) updated. Please try again in a minute.') || true
    # Update codebase
    git checkout -f
    git pull origin main

    # Install dependencies based on lock file
    composer install --no-interaction --prefer-dist --optimize-autoloader
    composer dump-autoload

    chgrp -R www-data storage bootstrap/cache
    chmod -R ug+rwx storage bootstrap/cache

    # Install dependencies based on lock file
    npm ci
    npm run prod

    # Migrate database
    php artisan migrate --force

    # Note: If you're using queue workers, this is the place to restart them.
    # ...

    # Clear cache
    php artisan optimize

# Exit maintenance mode
php artisan up

echo "Application deployed!"
