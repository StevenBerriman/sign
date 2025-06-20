<?php
/**
 * Client Token Authentication API
 * Save as: /sign/api/client-auth.php
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit();
}

require_once '../config/config.php';

try {
    $db = getDB();
    if (!$db) {
        throw new Exception('Database connection failed');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $token = $_GET['token'] ?? '';
        $contractId = $_GET['contract'] ?? '';
        
        if (!$token || !$contractId) {
            throw new Exception('Token and contract ID are required');
        }
        
        // Validate token format and extract data
        $tokenValid = validateClientToken($token, $contractId);
        
        if (!$tokenValid) {
            throw new Exception('Invalid or expired token');
        }
        
        // Get contract details for this client
        $stmt = $db->prepare('SELECT * FROM ' . DB_PREFIX . 'contracts WHERE id = ?');
        $stmt->execute([$contractId]);
        $contract = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$contract) {
            throw new Exception('Contract not found');
        }
        
        // Get line items
        $stmt = $db->prepare('SELECT * FROM ' . DB_PREFIX . 'line_items WHERE contract_id = ? ORDER BY sort_order, id');
        $stmt->execute([$contractId]);
        $lineItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Convert line items to expected format
        $contract['lineItems'] = array_map(function($item) {
            return [
                'id' => $item['id'],
                'description' => $item['description'],
                'quantity' => floatval($item['quantity']),
                'unitPrice' => floatval($item['unit_price']),
                'totalPrice' => floatval($item['total_price'] ?? ($item['quantity'] * $item['unit_price']))
            ];
        }, $lineItems);
        
        // Get payment schedule
        $stmt = $db->prepare('SELECT * FROM ' . DB_PREFIX . 'payment_schedule WHERE contract_id = ? ORDER BY sort_order, id');
        $stmt->execute([$contractId]);
        $paymentSchedule = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Convert payment schedule to expected format
        $contract['paymentSchedule'] = array_map(function($payment) {
            return [
                'id' => $payment['id'],
                'stage' => $payment['stage_description'],
                'percentage' => floatval($payment['percentage']),
                'amount' => $payment['amount'] ? floatval($payment['amount']) : null,
                'paid' => (bool)$payment['paid']
            ];
        }, $paymentSchedule);
        
        // Convert to camelCase for React app
        $clientData = [
            'id' => $contract['id'],
            'clientName' => $contract['client_name'],
            'clientEmail' => $contract['client_email'],
            'clientAddress' => $contract['client_address'],
            'clientPhone' => $contract['client_phone'],
            'projectType' => $contract['project_type'],
            'installationDate' => $contract['installation_date'],
            'quoteNumber' => $contract['quote_number'],
            'scopeOfWork' => $contract['scope_of_work'],
            'status' => $contract['status'],
            'createdAt' => $contract['created_at'],
            'lineItems' => $contract['lineItems'],
            'paymentSchedule' => $contract['paymentSchedule'],
            'termsAccepted' => false,
            'signature' => null
        ];
        
        // Calculate total amount
        $totalAmount = 0;
        foreach ($contract['lineItems'] as $item) {
            $totalAmount += $item['quantity'] * $item['unitPrice'];
        }
        $clientData['totalAmount'] = $totalAmount;
        
        echo json_encode([
            'success' => true,
            'isValidToken' => true,
            'contract' => $clientData,
            'clientMode' => true,
            'message' => 'Token validated successfully'
        ]);
        
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    }
    
} catch (Exception $e) {
    error_log('Client Auth Error: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'isValidToken' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Validate client access token
 */
function validateClientToken($token, $contractId) {
    try {
        $secret = 'your_secret_key_here'; // Same secret used in email generation
        $decoded = base64_decode($token);
        
        if (!$decoded) {
            return false;
        }
        
        $parts = explode('|', $decoded);
        if (count($parts) !== 4) {
            return false;
        }
        
        list($email, $tokenContractId, $timestamp, $signature) = $parts;
        
        // Check if contract ID matches
        if ($tokenContractId != $contractId) {
            return false;
        }
        
        // Check if token is not too old (24 hours)
        $tokenAge = time() - intval($timestamp);
        if ($tokenAge > (24 * 60 * 60)) {
            return false; // Token expired
        }
        
        // Verify signature
        $data = $email . '|' . $tokenContractId . '|' . $timestamp;
        $expectedSignature = hash_hmac('sha256', $data, $secret);
        
        return hash_equals($expectedSignature, $signature);
        
    } catch (Exception $e) {
        error_log('Token validation error: ' . $e->getMessage());
        return false;
    }
}
?>