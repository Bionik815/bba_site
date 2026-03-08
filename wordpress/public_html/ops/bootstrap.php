<?php

// Centralized runtime error handling for the ops app.
$opsDebug = getenv('OPS_DEBUG') === '1';

error_reporting(E_ALL);
ini_set('display_errors', $opsDebug ? '1' : '0');
ini_set('log_errors', '1');

$defaultLogDir = __DIR__ . '/runtime';
if (!is_dir($defaultLogDir)) {
  @mkdir($defaultLogDir, 0755, true);
}

$logPath = getenv('OPS_ERROR_LOG') ?: ($defaultLogDir . '/php-error.log');
if (is_string($logPath) && $logPath !== '') {
  ini_set('error_log', $logPath);
}
