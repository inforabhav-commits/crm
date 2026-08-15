<?php

function getAppSetting($key, $default = '') {
    global $pdo;
    if (!isset($pdo) || !$pdo instanceof PDO) {
        return $default;
    }

    try {
        $stmt = $pdo->prepare('SELECT setting_value FROM app_settings WHERE setting_key = ? LIMIT 1');
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        return $row ? $row['setting_value'] : $default;
    } catch (PDOException $exception) {
        return $default;
    }
}

function setAppSetting($key, $value) {
    global $pdo;
    $stmt = $pdo->prepare('INSERT INTO app_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
    $stmt->execute([$key, $value]);
}
