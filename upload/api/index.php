<?php
// Version
define('VERSION', '3.0.3.2');

// Configuration
if (is_file('../config.php')) {
	require_once('../config.php');
}

// Check Installed
if (!defined('DIR_APPLICATION')) {
	exit('You are not authorized to view this page!');
}

// Startup
require_once('api_startup.php');

start('catalog');
