<?php

// Database settings.
$databases['default']['default'] = array (
  'database' => getenv('DRUPAL_DATABASE_NAME'),
  'username' => getenv('DRUPAL_DATABASE_USERNAME'),
  'password' => getenv('DRUPAL_DATABASE_PASSWORD'),
  'prefix' => getenv('DRUPAL_DATABASE_PREFIX'),
  'host' => getenv('DRUPAL_DATABASE_HOST'),
  'port' => getenv('DRUPAL_DATABASE_PORT'),
  'namespace' => 'Drupal\\Core\\Database\\Driver\\mysql',
  'driver' => 'mysql',
);

// Location of the site configuration files, relative to the site root.
$config_directories['sync'] = '../config/' . $site_path . '/sync';

// Files folders.
$settings['file_private_path'] = '../files/' . $site_path . '/files/private';
$settings['file_public_path'] = $app_root . '/' . $site_path . '/files/public';
$settings['file_temp_path'] = '../files/' . $site_path . '/files/temp';
$conf['l10n_update_download_store'] = '../files/' . $site_path . '/files/temp';


$settings['hash_salt'] = getenv('DRUPAL_HASH_SALT') !== FALSE ? getenv('DRUPAL_HASH_SALT') : '0irtUmg80fCmL52y5x1Xj4N0JUBphTQmYFvnFwpEraZIu6_ZyQISb8EF9Fdyu3b8zIZR6kkNVg';

$settings['trusted_host_patterns'] = [
  '^webgate\.ec\.europa\.eu$',
  '^webgate\.acceptance\.ec\.europa\.eu$',
];

// Load environment development override configuration, if available.
// Keep this code block at the end of this file to take full effect.
if (file_exists($app_root . '/' . $site_path . '/settings.override.php')) {
  include $app_root . '/' . $site_path . '/settings.override.php';
}
