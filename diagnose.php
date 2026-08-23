<?php
header('Content-Type: text/plain; charset=utf-8');
$here = __DIR__;

echo "Loading config...\n";
require_once $here . '/config/config.php';

echo "BASE_URL = [" . (defined('BASE_URL') ? BASE_URL : 'NOT DEFINED') . "]\n";
echo "BASE_PATH = [" . (defined('BASE_PATH') ? BASE_PATH : 'NOT DEFINED') . "]\n";

$envFile = $here . '/config/env.php';
if (file_exists($envFile)) {
    $env = file_get_contents($envFile);
    if (preg_match("/BASE_URL',\s*'([^']+)'/", $env, $m)) {
        echo "env.php BASE_URL value = [" . $m[1] . "]\n";
    } else {
        echo "env.php has no BASE_URL define (or bad format)\n";
    }
}
