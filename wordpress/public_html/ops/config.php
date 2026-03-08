<?php

$config = [
  'db' => [
    'host' => getenv('OPS_DB_HOST') ?: '127.0.0.1',
    'name' => getenv('OPS_DB_NAME') ?: 'barebones_ops',
    'user' => getenv('OPS_DB_USER') ?: 'ops_user',
    'pass' => getenv('OPS_DB_PASS') ?: 'ops_password',
    'port' => (int)(getenv('OPS_DB_PORT') ?: 3306),
    'charset' => getenv('OPS_DB_CHARSET') ?: 'utf8mb4',
  ],
  'app' => [
    'base_path' => getenv('OPS_BASE_PATH') ?: '/ops',
    'logo_url'  => getenv('OPS_LOGO_URL') ?: '/wp-content/uploads/2025/09/BArebones_White.png',
  ],
];

$localPath = __DIR__ . '/config.local.php';
if (file_exists($localPath)) {
  $local = require $localPath;
  if (is_array($local)) {
    $config = array_replace_recursive($config, $local);
  }
}

return $config;
