<?php
require __DIR__ . '/auth.php';
session_destroy();

// Resolve /ops safely
$scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
$autoBase  = rtrim(preg_replace('#/pages$#', '', $scriptDir), '');
$base      = $autoBase ?: '/ops';

header('Location: ' . $base . '/login.php');
exit;
