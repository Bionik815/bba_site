<?php
// Edit ONLY the DB creds. Leave base_path as '/ops' since we're deployed in /public_html/ops
return [
  'db' => [
    'host' => 'mysql.hostinger.com',
    'name' => 'u632291655_barebones_ops',
    'user' => 'u632291655_bba_admin',
    'pass' => '815_B!oniK_89',
    'port' => 3306,
    'charset' => 'utf8mb4'
  ],
  'app' => [
    'base_path' => '/ops',
    'logo_url'  => '/wp-content/uploads/2025/09/BArebones_White.png',
  ]
];
