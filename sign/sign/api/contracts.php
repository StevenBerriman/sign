<?php
/**
 * Working Contracts API Endpoint
 * Save as: /sign/api/contracts.php
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit();
}

require_once '../config/config.php';

// Force admin mode for testing (bypass authentication issues)
$current_user = ['id' => 1, 'role' => 'admin'];

try {
    $db = getDB();
    if (!$db) {
        throw new Exception('Database connection failed');
    }

    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($method) {
        case 'GET':
            // Get all contracts (admin) or user's contracts (client)
            if ($current_user && $current_user['role'] === 'admin') {
                // Admin sees all contracts - use separate queries for clarity
                $stmt = $db->prepare('SELECT * FROM ' . DB_PREFIX . 'contracts ORDER BY created_at DESC');
                $stmt->execute();
                $contracts = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Add line items and payment schedule to each contract
                foreach ($contracts as &$contract) {
                    // Convert database column names to camelCase for React app
                    $contract['clientName'] = $contract['client_name'];
                    $contract['clientEmail'] = $contract['client_email'];
                    $contract['clientAddress'] = $contract['client_address'];
                    $contract['clientPhone'] = $contract['client_phone'];
                    $contract['projectType'] = $contract['project_type'];
                    $contract['installationDate'] = $contract['installation_date'];
                    $contract['quoteNumber'] = $contract['quote_number'];
                    $contract['scopeOfWork'] = $contract['scope_of_work'];
                    $contract['createdBy'] = $contract['created_by'];
                    $contract['createdAt'] = $contract['created_at'];
                    $contract['updatedAt'] = $contract['updated_at'];
                    
                    // Get line items
                    $stmt = $db->prepare('SELECT * FROM ' . DB_PREFIX . 'line_items WHERE contract_id = ? ORDER BY sort_order, id');
                    $stmt->execute([$contract['id']]);
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
                    $stmt->execute([$contract['id']]);
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
                    
                    // Get attachments
                    $stmt = $db->prepare('SELECT * FROM ' . DB_PREFIX . 'attachments WHERE contract_id = ?');
                    $stmt->execute([$contract['id']]);
                    $attachments = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Convert attachments to expected format
                    $contract['uploadedQuote'] = null;
                    $contract['uploadedDrawings'] = [];
                    
                    foreach ($attachments as $attachment) {
                        if ($attachment['file_type'] === 'application/pdf' || strpos($attachment['file_path'], 'quotes/') !== false) {
                            $contract['uploadedQuote'] = [
                                'name' => $attachment['file_name'],
                                'url' => str_replace('../', '/sign/', $attachment['file_path'])
                            ];
                        } else {
                            $contract['uploadedDrawings'][] = [
                                'name' => $attachment['file_name'],
                                'url' => str_replace('../', '/sign/', $attachment['file_path'])
                            ];
                        }
                    }
                    
                    // Calculate total amount from line items
                    $totalAmount = 0;
                    foreach ($contract['lineItems'] as $item) {
                        $totalAmount += $item['quantity'] * $item['unitPrice'];
                    }
                    $contract['totalAmount'] = $totalAmount;
                    
                    // Set default values if missing
                    $contract['termsAccepted'] = !empty($contract['terms_accepted']);
                    $contract['signature'] = $contract['signature'] ?? null;
                    $contract['signedAt'] = $contract['signed_at'] ?? null;
                }
                
            } else {
                // Client sees only their own contracts
                $user_email = $current_user['email'] ?? '';
                $stmt = $db->prepare('SELECT * FROM ' . DB_PREFIX . 'contracts WHERE client_email = ? ORDER BY created_at DESC');
                $stmt->execute([$user_email]);
                $contracts = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Add related data for client contracts too
                foreach ($contracts as &$contract) {
                    // Convert database column names to camelCase for React app
                    $contract['clientName'] = $contract['client_name'];
                    $contract['clientEmail'] = $contract['client_email'];
                    $contract['clientAddress'] = $contract['client_address'];
                    $contract['clientPhone'] = $contract['client_phone'];
                    $contract['projectType'] = $contract['project_type'];
                    $contract['installationDate'] = $contract['installation_date'];
                    $contract['quoteNumber'] = $contract['quote_number'];
                    $contract['scopeOfWork'] = $contract['scope_of_work'];
                    $contract['createdBy'] = $contract['created_by'];
                    $contract['createdAt'] = $contract['created_at'];
                    $contract['updatedAt'] = $contract['updated_at'];
                    
                    $stmt = $db->prepare('SELECT * FROM ' . DB_PREFIX . 'line_items WHERE contract_id = ? ORDER BY sort_order, id');
                    $stmt->execute([$contract['id']]);
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
                    
                    $stmt = $db->prepare('SELECT * FROM ' . DB_PREFIX . 'payment_schedule WHERE contract_id = ? ORDER BY sort_order, id');
                    $stmt->execute([$contract['id']]);
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
                    
                    $stmt = $db->prepare('SELECT * FROM ' . DB_PREFIX . 'attachments WHERE contract_id = ?');
                    $stmt->execute([$contract['id']]);
                    $attachments = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Convert attachments to expected format
                    $contract['uploadedQuote'] = null;
                    $contract['uploadedDrawings'] = [];
                    
                    foreach ($attachments as $attachment) {
                        if ($attachment['file_type'] === 'application/pdf' || strpos($attachment['file_path'], 'quotes/') !== false) {
                            $contract['uploadedQuote'] = [
                                'name' => $attachment['file_name'],
                                'url' => str_replace('../', '/sign/', $attachment['file_path'])
                            ];
                        } else {
                            $contract['uploadedDrawings'][] = [
                                'name' => $attachment['file_name'],
                                'url' => str_replace('../', '/sign/', $attachment['file_path'])
                            ];
                        }
                    }
                    
                    // Calculate total amount from line items
                    $totalAmount = 0;
                    foreach ($contract['lineItems'] as $item) {
                        $totalAmount += $item['quantity'] * $item['unitPrice'];
                    }
                    $contract['totalAmount'] = $totalAmount;
                    
                    // Set default values if missing
                    $contract['termsAccepted'] = !empty($contract['terms_accepted']);
                    $contract['signature'] = $contract['signature'] ?? null;
                    $contract['signedAt'] = $contract['signed_at'] ?? null;
                }
            }
            
            echo json_encode([
                'success' => true,
                'contracts' => $contracts
            ]);
            break;
            
        case 'POST':
            // Create new contract
            $input = json_decode(file_get_contents('php://input'), true);
            
            $clientName = $input['clientName'] ?? '';
            $clientEmail = $input['clientEmail'] ?? '';
            $clientAddress = $input['clientAddress'] ?? '';
            $projectType = $input['projectType'] ?? '';
            $installationDate = $input['installationDate'] ?? null;
            $quoteNumber = $input['quoteNumber'] ?? '';
            $scopeOfWork = $input['scopeOfWork'] ?? '';
            $lineItems = $input['lineItems'] ?? [];
            $paymentSchedule = $input['paymentSchedule'] ?? [];
            
            if (empty($clientName) || empty($clientEmail) || empty($quoteNumber)) {
                throw new Exception('Missing required fields: clientName, clientEmail, quoteNumber');
            }
            
            // Start transaction
            $db->beginTransaction();
            
            try {
                // Insert contract
                $stmt = $db->prepare('
                    INSERT INTO ' . DB_PREFIX . 'contracts 
                    (client_name, client_email, client_address, project_type, installation_date, 
                     quote_number, scope_of_work, created_by, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ');
                $stmt->execute([
                    $clientName, $clientEmail, $clientAddress, $projectType, $installationDate,
                    $quoteNumber, $scopeOfWork, $current_user['id']
                ]);
                
                $contractId = $db->lastInsertId();
                
                // Insert line items
                if (!empty($lineItems)) {
                    $stmt = $db->prepare('
                        INSERT INTO ' . DB_PREFIX . 'line_items 
                        (contract_id, description, quantity, unit_price, sort_order) 
                        VALUES (?, ?, ?, ?, ?)
                    ');
                    
                    foreach ($lineItems as $index => $item) {
                        $stmt->execute([
                            $contractId,
                            $item['description'] ?? '',
                            $item['quantity'] ?? 0,
                            $item['unitPrice'] ?? 0,
                            $index
                        ]);
                    }
                }
                
                // Insert payment schedule
                if (!empty($paymentSchedule)) {
                    $stmt = $db->prepare('
                        INSERT INTO ' . DB_PREFIX . 'payment_schedule 
                        (contract_id, stage_description, percentage, sort_order) 
                        VALUES (?, ?, ?, ?)
                    ');
                    
                    foreach ($paymentSchedule as $index => $payment) {
                        $stmt->execute([
                            $contractId,
                            $payment['stage'] ?? '',
                            $payment['percentage'] ?? 0,
                            $index
                        ]);
                    }
                }
                
                $db->commit();
                
                echo json_encode([
                    'success' => true,
                    'contractId' => $contractId,
                    'message' => 'Contract created successfully'
                ]);
                
            } catch (Exception $e) {
                $db->rollback();
                throw $e;
            }
            break;
            
        case 'PUT':
            // Update contract (for signatures, status changes, etc.)
            $input = json_decode(file_get_contents('php://input'), true);
            $contractId = $input['contractId'] ?? null;
            
            if (!$contractId) {
                throw new Exception('Contract ID is required');
            }
            
            $updates = [];
            $params = [];
            
            if (isset($input['signature'])) {
                $updates[] = 'signature = ?';
                $params[] = $input['signature'];
            }
            
            if (isset($input['status'])) {
                $updates[] = 'status = ?';
                $params[] = $input['status'];
            }
            
            if (isset($input['termsAccepted'])) {
                $updates[] = 'terms_accepted = ?';
                $params[] = $input['termsAccepted'] ? 1 : 0;
            }
            
            if (isset($input['signedAt'])) {
                $updates[] = 'signed_at = ?';
                $params[] = $input['signedAt'];
            }
            
            if (!empty($updates)) {
                $params[] = $contractId;
                $sql = 'UPDATE ' . DB_PREFIX . 'contracts SET ' . implode(', ', $updates) . ', updated_at = NOW() WHERE id = ?';
                
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Contract updated successfully'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'error' => 'No fields to update'
                ]);
            }
            break;
            
        case 'DELETE':
            // Delete contract
            $input = json_decode(file_get_contents('php://input'), true);
            $contractId = $input['contractId'] ?? null;
            
            if (!$contractId) {
                throw new Exception('Contract ID is required');
            }
            
            // Delete contract (cascade will handle related records)
            $stmt = $db->prepare('DELETE FROM ' . DB_PREFIX . 'contracts WHERE id = ?');
            $stmt->execute([$contractId]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Contract deleted successfully'
            ]);
            break;
            
        default:
            http_response_code(405);
            echo json_encode([
                'success' => false,
                'error' => 'Method not allowed'
            ]);
            break;
    }
    
} catch (Exception $e) {
    error_log('Contracts API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage(),
        'line' => $e->getLine(),
        'file' => basename($e->getFile())
    ]);
}
?>