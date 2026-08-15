<?php
require_once __DIR__ . '/../config/database.php';

$sql = <<<SQL
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin','sales_agent') NOT NULL,
    justcall_user_id VARCHAR(100) NULL,
    justcall_primary_number VARCHAR(50) NULL,
    justcall_secondary_number VARCHAR(50) NULL,
    justcall_third_number VARCHAR(50) NULL,
    status ENUM('available','on_call','busy','offline') NOT NULL DEFAULT 'offline',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NULL,
    phone_no VARCHAR(50) NOT NULL,
    date_of_entry DATE NULL,
    amount DECIMAL(12,2) DEFAULT 0,
    plan VARCHAR(100) NULL,
    software VARCHAR(100) NULL,
    license_number VARCHAR(100) NULL,
    product_number VARCHAR(100) NULL,
    file_password VARCHAR(255) NULL,
    issue TEXT NULL,
    payment_type VARCHAR(50) NULL,
    last4 VARCHAR(4) NULL,
    assigned_agent_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS call_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NULL,
    agent_id INT NULL,
    call_sid VARCHAR(100) NULL,
    direction VARCHAR(20) NULL,
    customer_number VARCHAR(50) NULL,
    justcall_number VARCHAR(50) NULL,
    justcall_agent_id VARCHAR(100) NULL,
    justcall_agent_name VARCHAR(100) NULL,
    start_time DATETIME NULL,
    end_time DATETIME NULL,
    status VARCHAR(50) NULL,
    duration INT NULL,
    recording_url TEXT NULL,
    disposition VARCHAR(100) NULL,
    payload TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS app_settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
SQL;

$pdo->exec($sql);
$pdo->exec("ALTER TABLE customers MODIFY file_password VARCHAR(255) NULL");
$customerColumns = $pdo->query("SHOW COLUMNS FROM customers")->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('email', $customerColumns, true)) {
    $pdo->exec("ALTER TABLE customers ADD COLUMN email VARCHAR(100) NULL AFTER name");
}
$columns = $pdo->query("SHOW COLUMNS FROM call_logs")->fetchAll(PDO::FETCH_COLUMN);
$addColumn = function ($name, $definition) use ($pdo, $columns) {
    if (!in_array($name, $columns, true)) {
        $pdo->exec("ALTER TABLE call_logs ADD COLUMN $name $definition");
    }
};
$addColumn('customer_number', 'VARCHAR(50) NULL');
$addColumn('justcall_number', 'VARCHAR(50) NULL');
$addColumn('justcall_agent_id', 'VARCHAR(100) NULL');
$addColumn('justcall_agent_name', 'VARCHAR(100) NULL');
$addColumn('start_time', 'DATETIME NULL');
$addColumn('end_time', 'DATETIME NULL');

$stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
$stmt->execute(['admin@example.com']);
if (!$stmt->fetch()) {
    $pdo->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)')->execute([
        'Administrator',
        'admin@example.com',
        password_hash('admin123', PASSWORD_BCRYPT),
        'admin'
    ]);
}

echo 'Installation complete';
