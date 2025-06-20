<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit();
}

require_once '../config/config.php';

try {
    $db = getDB();
    $current_user = ['id' => 1, 'role' => 'admin'];
    
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($method) {
        case 'POST':
            // Save manually entered data
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input || !isset($input['quoteId'])) {
                throw new Exception('Quote ID and data required');
            }
            
            $quoteId = $input['quoteId'];
            $clientData = $input['clientData'] ?? [];
            $lineItems = $input['lineItems'] ?? [];
            $contractContent = $input['contractContent'] ?? '';
            
            // Calculate total amount
            $totalAmount = 0;
            foreach ($lineItems as $item) {
                $totalAmount += ($item['quantity'] ?? 0) * ($item['unitPrice'] ?? 0);
            }
            
            // Update the quote record with manually entered data
            $stmt = $db->prepare('
                UPDATE kb_quotes 
                SET client_name = ?, client_email = ?, client_address = ?, client_phone = ?, 
                    quote_number = ?, line_items_json = ?, total_amount = ?, 
                    requires_manual_entry = 0, updated_at = NOW()
                WHERE id = ?
            ');
            
            $stmt->execute([
                $clientData['clientName'] ?? '',
                $clientData['clientEmail'] ?? '',
                $clientData['clientAddress'] ?? '',
                $clientData['clientPhone'] ?? '',
                $clientData['quoteNumber'] ?? 'Q-' . date('Y') . '-' . rand(100, 999),
                json_encode($lineItems),
                $totalAmount,
                $quoteId
            ]);
            
            // If contract content provided, save it as well
            if (!empty($contractContent)) {
                $stmt = $db->prepare('
                    UPDATE kb_quotes 
                    SET contract_content = ? 
                    WHERE id = ?
                ');
                $stmt->execute([$contractContent, $quoteId]);
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Data saved successfully',
                'quoteId' => $quoteId,
                'totalAmount' => $totalAmount
            ]);
            break;
            
        case 'GET':
            // Get quote data for editing
            $quoteId = $_GET['id'] ?? null;
            
            if (!$quoteId) {
                throw new Exception('Quote ID required');
            }
            
            $stmt = $db->prepare('SELECT * FROM kb_quotes WHERE id = ?');
            $stmt->execute([$quoteId]);
            $quote = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$quote) {
                throw new Exception('Quote not found');
            }
            
            // Get associated file information
            $stmt = $db->prepare('SELECT id, file_name, file_type FROM kb_attachments WHERE file_path = ?');
            $stmt->execute([$quote['file_path']]);
            $fileInfo = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $lineItems = json_decode($quote['line_items_json'] ?? '[]', true);
            
            echo json_encode([
                'success' => true,
                'quote' => [
                    'id' => $quote['id'],
                    'clientName' => $quote['client_name'],
                    'clientEmail' => $quote['client_email'],
                    'clientAddress' => $quote['client_address'],
                    'clientPhone' => $quote['client_phone'],
                    'quoteNumber' => $quote['quote_number'],
                    'lineItems' => $lineItems,
                    'totalAmount' => $quote['total_amount'],
                    'contractContent' => $quote['contract_content'] ?? '',
                    'requiresManualEntry' => $quote['requires_manual_entry']
                ],
                'fileInfo' => $fileInfo ? [
                    'id' => $fileInfo['id'],
                    'name' => $fileInfo['file_name'],
                    'type' => $fileInfo['file_type'],
                    'viewUrl' => '/sign/api/view-file.php?id=' . $fileInfo['id']
                ] : null
            ]);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            break;
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error: ' . $e->getMessage(),
        'line' => $e->getLine(),
        'file' => basename($e->getFile())
    ]);
}
?>