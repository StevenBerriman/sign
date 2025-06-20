<?php
/**
 * Simple check of kb_terms table structure
 * Save as: /sign/api/simple-terms-check.php
 */

header('Content-Type: application/json');
require_once '../config/config.php';

try {
    $db = getDB();
    
    // Check table structure
    $stmt = $db->query('DESCRIBE kb_terms');
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get all data from table
    $stmt = $db->query('SELECT * FROM kb_terms ORDER BY id DESC LIMIT 5');
    $sampleData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Count total rows
    $stmt = $db->query('SELECT COUNT(*) as total FROM kb_terms');
    $count = $stmt->fetch();
    
    echo json_encode([
        'success' => true,
        'columns' => $columns,
        'sample_data' => $sampleData,
        'total_rows' => $count['total']
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>