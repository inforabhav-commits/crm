<?php
$host = 'localhost';
// $port = '3307';
$dbname = 'u558618626_just';
$username = 'u558618626_just';
$password = 'Just@3001';

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $username, $password, $options);
} catch (PDOException $e) {
    try {
        $bootstrap = new PDO("mysql:host=$host;port=$port;charset=utf8mb4", $username, $password, $options);
        $bootstrap->exec("CREATE DATABASE IF NOT EXISTS `$dbname`");
        $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $username, $password, $options);
    } catch (PDOException $bootstrapException) {
        die('Database connection failed: ' . $bootstrapException->getMessage());
    }
}
