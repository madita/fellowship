<?php

// Display all loaded PHP extensions
echo "Loaded extensions:\n";
print_r(get_loaded_extensions());

// Check specifically for PDO and SQLite
echo "\n\nChecking for PDO and SQLite:\n";
echo 'PDO installed: '.(extension_loaded('pdo') ? 'Yes' : 'No')."\n";
echo 'PDO SQLite installed: '.(in_array('sqlite', PDO::getAvailableDrivers()) ? 'Yes' : 'No')."\n";
echo 'SQLite3 installed: '.(extension_loaded('sqlite3') ? 'Yes' : 'No')."\n";

// Display PHP info
phpinfo();
