<?php
if (file_exists('htaccess.txt')) {
    rename('htaccess.txt', '.htaccess');
}

$htaccess = file_get_contents('.htaccess');
// Uncomment RewriteBase /discharge-app/
$htaccess = str_replace('# RewriteBase /discharge-app/', 'RewriteBase /discharge-app/', $htaccess);
file_put_contents('.htaccess', $htaccess);

// Clean up sensitive files
$cleanup = [
    'do_install.php',
    'do_install2.php',
    'build_clinical_schema.php',
    'create_dashboard.php',
    'seed_samples.php',
    'strip_sql.php',
    'download_composer.php',
    'composer-setup.php'
];

foreach ($cleanup as $file) {
    if (file_exists($file)) unlink($file);
}

echo "Final system polishing complete. .htaccess configured and cleanup finished.\n";
