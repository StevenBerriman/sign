<?php
/**
 * Minimal Submit Signature API - uses only basic columns
 * Save as: /sign/api/submit-signature.php
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
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
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST method allowed');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $token = $input['token'] ?? null;
    $signatureData = $input['signatureData'] ?? null;
    $signatureType = $input['signatureType'] ?? 'typed';
    $agreesToTerms = $input['agreesToTerms'] ?? false;
    
    if (!$token) {
        throw new Exception('Token is required');
    }
    
    if (!$signatureData) {
        throw new Exception('Signature data is required');
    }
    
    if (!$agreesToTerms) {
        throw new Exception('You must agree to the terms and conditions');
    }
    
    // Validate token
    $tokenData = validateClientToken($token);
    
    if (!$tokenData) {
        throw new Exception('Invalid or expired token');
    }
    
    $contractId = $tokenData['contract_id'];
    $clientEmail = $tokenData['client_email'];
    
    // Verify contract exists and belongs to client
    $stmt = $db->prepare('SELECT * FROM ' . DB_PREFIX . 'contracts WHERE id = ? AND client_email = ?');
    $stmt->execute([$contractId, $clientEmail]);
    $contract = $stmt->fetch();
    
    if (!$contract) {
        throw new Exception('Contract not found or access denied');
    }
    
    // Check if already signed
    $stmt = $db->prepare('SELECT id FROM ' . DB_PREFIX . 'signatures WHERE contract_id = ?');
    $stmt->execute([$contractId]);
    $existingSignature = $stmt->fetch();
    
    if ($existingSignature) {
        throw new Exception('Contract has already been signed');
    }
    
    // Prepare signature data with type prefix
    $finalSignatureData = ($signatureType === 'typed' ? 'TYPED: ' : 'DRAWN: ') . $signatureData;
    
    // Try different column combinations until one works
    $signatureId = null;
    $insertSuccess = false;
    
    // Try the most common column combinations
    $insertAttempts = [
        // Attempt 1: Most common columns
        [
            'query' => 'INSERT INTO ' . DB_PREFIX . 'signatures (contract_id, signature_data, signed_at) VALUES (?, ?, NOW())',
            'params' => [$contractId, $finalSignatureData]
        ],
        // Attempt 2: With IP address
        [
            'query' => 'INSERT INTO ' . DB_PREFIX . 'signatures (contract_id, signature_data, ip_address, signed_at) VALUES (?, ?, ?, NOW())',
            'params' => [$contractId, $finalSignatureData, $_SERVER['REMOTE_ADDR'] ?? 'unknown']
        ],
        // Attempt 3: With email
        [
            'query' => 'INSERT INTO ' . DB_PREFIX . 'signatures (contract_id, signature_data, client_email, signed_at) VALUES (?, ?, ?, NOW())',
            'params' => [$contractId, $finalSignatureData, $clientEmail]
        ],
        // Attempt 4: With email and IP
        [
            'query' => 'INSERT INTO ' . DB_PREFIX . 'signatures (contract_id, signature_data, client_email, ip_address, signed_at) VALUES (?, ?, ?, ?, NOW())',
            'params' => [$contractId, $finalSignatureData, $clientEmail, $_SERVER['REMOTE_ADDR'] ?? 'unknown']
        ]
    ];
    
    foreach ($insertAttempts as $attempt) {
        try {
            $stmt = $db->prepare($attempt['query']);
            $stmt->execute($attempt['params']);
            $signatureId = $db->lastInsertId();
            $insertSuccess = true;
            break;
        } catch (Exception $e) {
            // Continue to next attempt
            continue;
        }
    }
    
    if (!$insertSuccess) {
        throw new Exception('Unable to save signature - database column mismatch. Please contact support.');
    }
    
    // Update contract status to signed
    $stmt = $db->prepare('UPDATE ' . DB_PREFIX . 'contracts SET status = ?, updated_at = NOW() WHERE id = ?');
    $stmt->execute(['signed', $contractId]);
    
    // Try to log the activity (optional - won't fail if it doesn't work)
    try {
        $stmt = $db->prepare('INSERT INTO ' . DB_PREFIX . 'activity_log (contract_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())');
        $stmt->execute([
            $contractId,
            'contract_signed',
            json_encode([
                'signature_id' => $signatureId,
                'signature_type' => $signatureType,
                'client_email' => $clientEmail,
                'signed_at' => date('Y-m-d H:i:s')
            ]),
            $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
    } catch (Exception $e) {
        // Activity logging failed, but signature was saved - continue
        error_log('Activity logging failed: ' . $e->getMessage());
    }
    
    // Send confirmation email
    sendSignatureConfirmationEmail($contract, $clientEmail, $signatureId);
    
    echo json_encode([
        'success' => true,
        'message' => 'Contract signed successfully',
        'signatureId' => $signatureId,
        'signedAt' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    error_log('Submit Signature API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug' => [
            'file' => basename(__FILE__),
            'line' => $e->getLine()
        ]
    ]);
}

/**
 * Validate client access token
 */
function validateClientToken($token) {
    try {
        $decoded = base64_decode($token);
        if (!$decoded) {
            return false;
        }
        
        $parts = explode('|', $decoded);
        
        if (count($parts) !== 4) {
            return false;
        }
        
        $clientEmail = $parts[0];
        $contractId = $parts[1];
        $timestamp = $parts[2];
        $hash = $parts[3];
        
        // Check if token is expired (24 hours)
        if (time() - $timestamp > 86400) {
            return false;
        }
        
        // Verify hash
        $data = $clientEmail . '|' . $contractId . '|' . $timestamp;
        $expectedHash = hash('sha256', $data . 'your_secret_key_here');
        
        if ($hash !== $expectedHash) {
            return false;
        }
        
        return [
            'client_email' => $clientEmail,
            'contract_id' => $contractId,
            'timestamp' => $timestamp
        ];
        
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Send signature confirmation email
 */
function sendSignatureConfirmationEmail($contract, $clientEmail, $signatureId) {
    $subject = 'Contract Signed Successfully - Kitchen & Bathroom (NE) Ltd';
    
    $message = "
    <html>
    <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
        <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
            <div style='background-color: #007cba; color: white; padding: 20px; text-align: center;'>
                <h1 style='margin: 0;'>Contract Signed Successfully</h1>
            </div>
            
            <div style='padding: 20px;'>
                <h2>Thank you, " . htmlspecialchars($contract['client_name'] ?? 'Valued Customer') . "!</h2>
                
                <p>Your contract has been successfully signed and submitted.</p>
                
                <p><strong>Contract Details:</strong></p>
                <ul>
                    <li><strong>Contract ID:</strong> " . htmlspecialchars($contract['id']) . "</li>
                    <li><strong>Quote Number:</strong> " . htmlspecialchars($contract['quote_number'] ?? 'N/A') . "</li>
                    <li><strong>Signed Date:</strong> " . date('d/m/Y H:i') . "</li>
                    <li><strong>Status:</strong> Digitally Signed</li>
                </ul>
                
                <p>We will be in touch soon to confirm your installation date and next steps.</p>
                
                <p>Thank you for choosing Kitchen & Bathroom (NE) Ltd.</p>
                
                <p>Best regards,<br>
                <strong>Kitchen & Bathroom (NE) Ltd</strong><br>
                Phone: 01234 567890<br>
                Email: info@kitchen-bathroom.co.uk</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    $headers = array(
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=UTF-8',
        'From: Kitchen & Bathroom (NE) Ltd <info@kitchen-bathroom.co.uk>',
        'Reply-To: info@kitchen-bathroom.co.uk'
    );
    
    return mail($clientEmail, $subject, $message, implode("\r\n", $headers));
}
?>