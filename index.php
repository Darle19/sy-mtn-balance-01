<?php
require __DIR__ . '/vendor/autoload.php';

$config = require __DIR__ . '/config.php';
$module = new \FBReport\ReportModule($config);
$module->run();
?>