<?php
require_once __DIR__ . '/../config/env.php';

$host     = env('DB_HOST', 'localhost');
$username = env('DB_USER', 'root');
$password = env('DB_PASS', '');
$database = env('DB_NAME', 'rotary');

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    die("Connection failed. Please try again later.");
}

$conn->set_charset("utf8mb4");
