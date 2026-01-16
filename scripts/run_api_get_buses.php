<?php
// php scripts/run_api_get_buses.php
ini_set('display_errors', 1);
error_reporting(E_ALL);
$_GET['action'] = 'get_buses';
require __DIR__ . '/../public/api.php';