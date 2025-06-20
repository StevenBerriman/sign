<?php
/**
 * Email API Endpoint
 * Save as: /api/email.php
 */

// This file is included from index.php, so all setup is already done

// Require authentication
if (!$current_user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
    exit;
}

// Admin only endpoint
if ($current_user['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$sub_endpoint = $segments[1] ?? '';

switch ($sub_endpoint) {
    case 'send-contract':
        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            exit;
        }
        
        $contractId = $input['contract_id'] ?? '';
        
        if (!$contractId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Contract ID required']);
            exit;
        }
        
        try {
            $db = getDB();
            
            // Get contract details
            $stmt = $db->prepare('
                SELECT c.*, u.company_name 
                FROM ' . DB_PREFIX . 'contracts c
                LEFT JOIN ' . DB_PREFIX . 'users u ON c.created_by = u.id
                WHERE c.id = ?
            ');
            $stmt->execute([$contractId]);
            $contract = $stmt->fetch();
            
            if (!$contract) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Contract not found']);
                exit;
            }
            
            // Generate access token
            $token = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', strtotime('+7 days'));
            
            $stmt = $db->prepare('
                INSERT INTO ' . DB_PREFIX . 'access_tokens 
                (token, contract_id, expires_at)
                VALUES (?, ?, ?)
            ');
            $stmt->execute([$token, $contractId, $expiresAt]);
            
            // Generate contract link
            $contractLink = APP_URL . '/?token=' . $token . '&contract=' . $contractId;
            
            // Get email template
            $stmt = $db->prepare('
                SELECT * FROM ' . DB_PREFIX . 'email_templates 
                WHERE template_key = ? AND active = 1
            ');
            $stmt->execute(['contract_ready']);
            $template = $stmt->fetch();
            
            if (!$template) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Email template not found']);
                exit;
            }
            
            // Replace template variables
            $variables = [
                '{{client_name}}' => $contract['client_name'],
                '{{project_type}}' => $contract['project_type'],
                '{{contract_link}}' => $contractLink,
                '{{company_name}}' => $contract['company_name'] ?? APP_NAME
            ];
            
            $subject = str_replace(array_keys($variables), array_values($variables), $template['subject']);
            $bodyHtml = str_replace(array_keys($variables), array_values($variables), $template['body_html']);
            $bodyText = str_replace(array_keys($variables), array_values($variables), $template['body_text']);
            
            // Send email
            $emailSent = sendEmail($contract['client_email'], $subject, $bodyHtml, $bodyText);
            
            if ($emailSent) {
                // Log activity
                $stmt = $db->prepare('
                    INSERT INTO ' . DB_PREFIX . 'activity_log 
                    (contract_id, user_id, action, details, ip_address)
                    VALUES (?, ?, ?, ?, ?)
                ');
                $stmt->execute([
                    $contractId,
                    $current_user['id'],
                    'email_sent',
                    json_encode([
                        'recipient' => $contract['client_email'],
                        'template' => 'contract_ready'
                    ]),
                    $_SERVER['REMOTE_ADDR']
                ]);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Email sent successfully',
                    'contract_link' => $contractLink
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Failed to send email']);
            }
            
        } catch (Exception $e) {
            error_log($e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Server error']);
        }
        break;
        
    case 'test':
        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            exit;
        }
        
        $testEmail = $input['email'] ?? $current_user['email'];
        
        $sent = sendEmail(
            $testEmail,
            'Test Email - Kitchen & Bathroom Contracts',
            '<h2>Test Email</h2><p>This is a test email from your digital contracts system.</p><p>If you received this, your email configuration is working correctly!</p>',
            'This is a test email from your digital contracts system. If you received this, your email configuration is working correctly!'
        );
        
        if ($sent) {
            echo json_encode(['success' => true, 'message' => 'Test email sent']);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to send test email']);
        }
        break;
        
    default:
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Endpoint not found']);
        break;
}

/**
 * Send email using SMTP
 */
function sendEmail($to, $subject, $htmlBody, $textBody = '') {
    // If SMTP not configured, return false
    if (!SMTP_HOST || !SMTP_USER || !SMTP_PASS) {
        error_log('SMTP configuration missing');
        return false;
    }
    
    try {
        // Use PHPMailer if available, otherwise use mail()
        if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            // PHPMailer implementation
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = SMTP_USER;
            $mail->Password = SMTP_PASS;
            $mail->SMTPSecure = SMTP_SECURE;
            $mail->Port = SMTP_PORT;
            
            $mail->setFrom(APP_EMAIL, APP_NAME);
            $mail->addAddress($to);
            
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            $mail->AltBody = $textBody ?: strip_tags($htmlBody);
            
            $mail->send();
            return true;
        } else {
            // Fallback to mail() function
            $headers = [
                'MIME-Version: 1.0',
                'Content-type: text/html; charset=UTF-8',
                'From: ' . APP_NAME . ' <' . APP_EMAIL . '>',
                'Reply-To: ' . APP_EMAIL,
                'X-Mailer: PHP/' . phpversion()
            ];
            
            return mail($to, $subject, $htmlBody, implode("\r\n", $headers));
        }
    } catch (Exception $e) {
        error_log('Email error: ' . $e->getMessage());
        return false;
    }
}
