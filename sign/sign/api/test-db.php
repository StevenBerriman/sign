<?php
header('Content-Type: application/json');
require_once '../config/config.php';

try {
    $db = getDB();
    if ($db) {
        echo json_encode([
            'success' => true,
            'message' => 'Database connected successfully',
            'host' => DB_HOST,
            'database' => DB_NAME,
            'user' => DB_USER
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Database connection returned null'
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>