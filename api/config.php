<?php
require_once __DIR__ . '/../private/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../private');
$dotenv->load();