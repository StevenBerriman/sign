<?php
/**
 * Direct Access API Endpoint - For token-based contract access
 * Save as: /api/direct-access.php
 */

// This file is included from index.php if needed, but can also be called directly

// Handle OPTIONS request for CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    exit();
}

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Load configuration if not already loaded
if (!defined('DB_HOST')) {
    require_once dirname(__DIR__) . '/config/config.php';
}

// Get token from request
$token = $_GET['token'] ?? $_POST['token'] ?? '';

if (!$token) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Access token required']);
    exit;
}

try {
    $db = getDB();
    
    // Validate token
    $stmt = $db->prepare('
        SELECT at.*, c.* 
        FROM ' . DB_PREFIX . 'access_tokens at
        JOIN ' . DB_PREFIX . 'contracts c ON at.contract_id = c.id
        WHERE at.token = ? 
        AND at.expires_at > NOW()
        AND (at.used = 0 OR at.used = 1)
    ');
    $stmt->execute([$token]);
    $result = $stmt->fetch();
    
    if (!$result) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Invalid or expired token']);
        exit;
    }
    
    // Mark token as used if first time
    if (!$result['used']) {
        $stmt = $db->prepare('
            UPDATE ' . DB_PREFIX . 'access_tokens 
            SET used = 1, used_at = NOW() 
            WHERE token = ?
        ');
        $stmt->execute([$token]);
    }
    
    $contract_id = $result['contract_id'];
    
    // Handle different actions
    $action = $_GET['action'] ?? $_POST['action'] ?? 'view';
    
    switch ($action) {
        case 'view':
            // Get contract details
            $stmt = $db->prepare('
                SELECT c.*, u.company_name as created_by_company
                FROM ' . DB_PREFIX . 'contracts c
                LEFT JOIN ' . DB_PREFIX . 'users u ON c.created_by = u.id
                WHERE c.id = ?
            ');
            $stmt->execute([$contract_id]);
            $contract = $stmt->fetch();
            
            // Get line items
            $stmt = $db->prepare('
                SELECT * FROM ' . DB_PREFIX . 'line_items 
                WHERE contract_id = ? 
                ORDER BY sort_order
            ');
            $stmt->execute([$contract_id]);
            $contract['line_items'] = $stmt->fetchAll();
            
            // Get payment schedule
            $stmt = $db->prepare('
                SELECT * FROM ' . DB_PREFIX . 'payment_schedule 
                WHERE contract_id = ? 
                ORDER BY sort_order
            ');
            $stmt->execute([$contract_id]);
            $contract['payment_schedule'] = $stmt->fetchAll();
            
            // Get attachments
            $stmt = $db->prepare('
                SELECT id, file_type, file_name, file_size, uploaded_at 
                FROM ' . DB_PREFIX . 'attachments 
                WHERE contract_id = ?
            ');
            $stmt->execute([$contract_id]);
            $contract['attachments'] = $stmt->fetchAll();
            
            // Check if already signed
            $stmt = $db->prepare('
                SELECT id, signed_by_name, signed_at 
                FROM ' . DB_PREFIX . 'signatures 
                WHERE contract_id = ?
            ');
            $stmt->execute([$contract_id]);
            $contract['signatures'] = $stmt->fetchAll();
            
            // Check if terms accepted
            $stmt = $db->prepare('
                SELECT id, accepted_at 
                FROM ' . DB_PREFIX . 'terms_acceptance 
                WHERE contract_id = ?
            ');
            $stmt->execute([$contract_id]);
            $contract['terms_accepted'] = $stmt->fetch() ? true : false;
            
            // Get active terms content
            $stmt = $db->prepare('
                SELECT content 
                FROM ' . DB_PREFIX . 'terms_conditions 
                WHERE active = 1 
                ORDER BY created_at DESC 
                LIMIT 1
            ');
            $stmt->execute();
            $terms = $stmt->fetch();
            $contract['terms_content'] = $terms['content'] ?? 'Standard terms and conditions apply.';
            
            echo json_encode(['success' => true, 'contract' => $contract]);
            break;
            
        case 'accept-terms':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'error' => 'Method not allowed']);
                exit;
            }
            
            // Record terms acceptance
            $stmt = $db->prepare('
                INSERT INTO ' . DB_PREFIX . 'terms_acceptance 
                (contract_id, terms_version, ip_address)
                VALUES (?, ?, ?)
            ');
            $stmt->execute([
                $contract_id,
                '1.0', // You might want to track version numbers
                $_SERVER['REMOTE_ADDR']
            ]);
            
            // Log activity
            $stmt = $db->prepare('
                INSERT INTO ' . DB_PREFIX . 'activity_log 
                (contract_id, action, ip_address)
                VALUES (?, ?, ?)
            ');
            $stmt->execute([
                $contract_id,
                'terms_accepted',
                $_SERVER['REMOTE_ADDR']
            ]);
            
            echo json_encode(['success' => true, 'message' => 'Terms accepted']);
            break;
            
        case 'sign':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'error' => 'Method not allowed']);
                exit;
            }
            
            $signature_data = $_POST['signature_data'] ?? '';
            $signer_name = $_POST['signer_name'] ?? '';
            
            if (!$signature_data) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Signature data required']);
                exit;
            }
            
            // Get contract details for signer info
            $stmt = $db->prepare('SELECT client_name, client_email FROM ' . DB_PREFIX . 'contracts WHERE id = ?');
            $stmt->execute([$contract_id]);
            $contract = $stmt->fetch();
            
            // Insert signature
            $stmt = $db->prepare('
                INSERT INTO ' . DB_PREFIX . 'signatures 
                (contract_id, signature_data, signed_by_name, signed_by_email, ip_address, user_agent)
                VALUES (?, ?, ?, ?, ?, ?)
            ');
            $stmt->execute([
                $contract_id,
                $signature_data,
                $signer_name ?: $contract['client_name'],
                $contract['client_email'],
                $_SERVER['REMOTE_ADDR'],
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
            
            // Update contract status
            $stmt = $db->prepare('
                UPDATE ' . DB_PREFIX . 'contracts 
                SET status = "signed" 
                WHERE id = ?
            ');
            $stmt->execute([$contract_id]);
            
            // Log activity
            $stmt = $db->prepare('
                INSERT INTO ' . DB_PREFIX . 'activity_log 
                (contract_id, action, details, ip_address)
                VALUES (?, ?, ?, ?)
            ');
            $stmt->execute([
                $contract_id,
                'contract_signed_via_link',
                json_encode(['token' => substr($token, 0, 8) . '...']),
                $_SERVER['REMOTE_ADDR']
            ]);
            
            // Send confirmation email (optional)
            // ... email sending code ...
            
            echo json_encode(['success' => true, 'message' => 'Contract signed successfully']);
            break;
            
        case 'download':
            // Get contract for PDF generation
            $stmt = $db->prepare('
                SELECT c.*, s.signature_data, s.signed_at, s.signed_by_name
                FROM ' . DB_PREFIX . 'contracts c
                LEFT JOIN ' . DB_PREFIX . 'signatures s ON c.id = s.contract_id
                WHERE c.id = ?
            ');
            $stmt->execute([$contract_id]);
            $contract = $stmt->fetch();
            
            if (!$contract['signature_data']) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Contract not signed yet']);
                exit;
            }
            
            // In a real implementation, you would generate a PDF here
            // For now, return the data that would be used for PDF generation
            echo json_encode([
                'success' => true, 
                'message' => 'PDF generation would happen here',
                'contract_id' => $contract_id,
                'signed_at' => $contract['signed_at']
            ]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
            break;
    }
    
} catch (Exception $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
