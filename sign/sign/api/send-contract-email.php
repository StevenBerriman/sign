<?php
/**
 * Send Contract Email API Endpoint
 * Save as: /sign/api/send-contract-email.php
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit();
}

require_once '../config/config.php';

// Force admin mode for testing
$current_user = ['id' => 1, 'role' => 'admin'];

try {
    $db = getDB();
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $contractId = $input['contractId'] ?? null;
        $action = $input['action'] ?? 'send_link';
        
        if (!$contractId) {
            throw new Exception('Contract ID is required');
        }
        
        // Get contract details
        $stmt = $db->prepare('
            SELECT c.*, u.email as created_by_email 
            FROM ' . DB_PREFIX . 'contracts c
            LEFT JOIN ' . DB_PREFIX . 'users u ON c.created_by = u.id
            WHERE c.id = ?
        ');
        $stmt->execute([$contractId]);
        $contract = $stmt->fetch();
        
        if (!$contract) {
            throw new Exception('Contract not found');
        }
        
        // Generate client access token
        $clientToken = generateClientToken($contract['client_email'], $contractId);
        
        // FIXED: Generate correct client.php URL
        $clientLink = 'https://kitchen-bathroom.co.uk/sign/client.php?token=' . $clientToken;
        
        if ($action === 'get_link') {
            // Just return the link for copying
            echo json_encode([
                'success' => true,
                'clientLink' => $clientLink,
                'contractId' => $contractId,
                'clientEmail' => $contract['client_email']
            ]);
            exit;
        }
        
        // Send email to client
        $emailSent = sendContractEmail($contract, $clientLink);
        
        if ($emailSent) {
            // Log the email activity using correct column names
            $stmt = $db->prepare('
                INSERT INTO ' . DB_PREFIX . 'activity_log 
                (contract_id, user_id, action, details, ip_address, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())
            ');
            $stmt->execute([
                $contractId,
                $current_user['id'],
                'email_sent',
                json_encode([
                    'recipient' => $contract['client_email'],
                    'client_link' => $clientLink,
                    'sent_at' => date('Y-m-d H:i:s')
                ]),
                $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Contract link sent successfully to ' . $contract['client_email'],
                'clientLink' => $clientLink
            ]);
        } else {
            throw new Exception('Failed to send email');
        }
        
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    }
    
} catch (Exception $e) {
    error_log('Email API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Email error: ' . $e->getMessage(),
        'line' => $e->getLine(),
        'file' => basename($e->getFile())
    ]);
}

/**
 * Generate secure client access token
 */
function generateClientToken($clientEmail, $contractId) {
    $timestamp = time();
    $data = $clientEmail . '|' . $contractId . '|' . $timestamp;
    $hash = hash('sha256', $data . 'your_secret_key_here');
    $token = base64_encode($data . '|' . $hash);
    return $token;
}

/**
 * Send contract email to client
 */
function sendContractEmail($contract, $clientLink) {
    $to = $contract['client_email'];
    $subject = 'Your Contract is Ready for Review - Kitchen & Bathroom (NE) Ltd';
    
    $message = "
    <html>
    <head>
        <title>Contract Ready for Review</title>
    </head>
    <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
        <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
            <div style='background-color: #007cba; color: white; padding: 20px; text-align: center;'>
                <h1 style='margin: 0;'>Kitchen & Bathroom (NE) Ltd</h1>
                <p style='margin: 5px 0 0 0;'>Contract Ready for Review</p>
            </div>
            
            <div style='padding: 20px; background-color: #f9f9f9;'>
                <h2 style='color: #007cba;'>Hello " . htmlspecialchars($contract['client_name']) . ",</h2>
                
                <p>Your contract for <strong>" . htmlspecialchars($contract['project_type']) . "</strong> project is ready for review and signing.</p>
                
                <p><strong>Contract Details:</strong></p>
                <ul>
                    <li><strong>Quote Number:</strong> " . htmlspecialchars($contract['quote_number']) . "</li>
                    <li><strong>Installation Date:</strong> " . htmlspecialchars($contract['installation_date']) . "</li>
                    <li><strong>Project Type:</strong> " . ucfirst(htmlspecialchars($contract['project_type'])) . "</li>
                </ul>
                
                <p><strong>To review and sign your contract:</strong></p>
                
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='{$clientLink}' style='background-color: #007cba; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block;'>Review & Sign Contract</a>
                </div>
                
                <p style='font-size: 14px; color: #666;'>
                    <strong>Note:</strong> This link will take you directly to your contract. No login required - simply click the link above to review and sign your contract digitally.
                </p>
                
                <p>If you have any questions, please don't hesitate to contact us.</p>
                
                <p>Best regards,<br>
                <strong>Kitchen & Bathroom (NE) Ltd</strong><br>
                Phone: 01234 567890<br>
                Email: info@kitchen-bathroom.co.uk</p>
            </div>
            
            <div style='text-align: center; padding: 20px; background-color: #eee; font-size: 12px; color: #666;'>
                <p>This email was sent automatically. Please do not reply to this email.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    $headers = array(
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=UTF-8',
        'From: Kitchen & Bathroom (NE) Ltd <info@kitchen-bathroom.co.uk>',
        'Reply-To: info@kitchen-bathroom.co.uk',
        'X-Mailer: PHP/' . phpversion()
    );
    
    return mail($to, $subject, $message, implode("\r\n", $headers));
}
?>