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
            // Generate contract from template and quote data
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input || !isset($input['quoteId']) || !isset($input['templateId'])) {
                throw new Exception('Quote ID and template ID required');
            }
            
            $quoteId = $input['quoteId'];
            $templateId = $input['templateId'];
            
            // Get quote data
            $stmt = $db->prepare('SELECT * FROM kb_quotes WHERE id = ?');
            $stmt->execute([$quoteId]);
            $quote = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$quote) {
                throw new Exception('Quote not found');
            }
            
            // Get template
            $stmt = $db->prepare('SELECT * FROM kb_contract_templates WHERE id = ? AND active = 1');
            $stmt->execute([$templateId]);
            $template = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$template) {
                throw new Exception('Template not found');
            }
            
            // Get current terms and conditions
            $stmt = $db->prepare('SELECT content FROM kb_terms_conditions WHERE active = 1 ORDER BY created_at DESC LIMIT 1');
            $stmt->execute();
            $terms = $stmt->fetch(PDO::FETCH_ASSOC);
            $termsContent = $terms['content'] ?? 'Terms and conditions not set';
            
            // Parse line items
            $lineItems = json_decode($quote['line_items_json'] ?? '[]', true);
            
            // Generate contract content by replacing placeholders
            $contractContent = generateContractFromTemplate($template['content'], $quote, $lineItems, $termsContent);
            
            // Save generated contract
            $stmt = $db->prepare('
                INSERT INTO ' . DB_PREFIX . 'contracts 
                (quote_number, client_name, client_email, client_address, client_phone, project_type, 
                 installation_date, scope_of_work, status, created_by, contract_content, template_id, quote_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');
            
            $stmt->execute([
                $quote['quote_number'],
                $quote['client_name'],
                $quote['client_email'],
                $quote['client_address'],
                $quote['client_phone'],
                $template['template_type'],
                $input['installationDate'] ?? null,
                generateScopeFromLineItems($lineItems),
                'pending',
                $current_user['id'],
                $contractContent,
                $templateId,
                $quoteId
            ]);
            
            $contractId = $db->lastInsertId();
            
            // Save line items
            foreach ($lineItems as $item) {
                $stmt = $db->prepare('
                    INSERT INTO ' . DB_PREFIX . 'line_items 
                    (contract_id, description, quantity, unit_price) 
                    VALUES (?, ?, ?, ?)
                ');
                $stmt->execute([
                    $contractId,
                    $item['description'] ?? '',
                    $item['quantity'] ?? 1,
                    $item['unitPrice'] ?? 0
                ]);
            }
            
            // Save payment schedule (default if not provided)
            $paymentSchedule = $input['paymentSchedule'] ?? getDefaultPaymentSchedule();
            foreach ($paymentSchedule as $payment) {
                $stmt = $db->prepare('
                    INSERT INTO ' . DB_PREFIX . 'payment_schedule 
                    (contract_id, stage_description, percentage) 
                    VALUES (?, ?, ?)
                ');
                $stmt->execute([
                    $contractId,
                    $payment['stage'] ?? $payment['stage_description'] ?? '',
                    $payment['percentage'] ?? 0
                ]);
            }
            
            // Mark quote as used
            $stmt = $db->prepare('UPDATE kb_quotes SET used_in_contract = 1, contract_id = ? WHERE id = ?');
            $stmt->execute([$contractId, $quoteId]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Contract generated successfully',
                'contractId' => $contractId,
                'contractContent' => $contractContent
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

/**
 * Generate contract content from template
 */
function generateContractFromTemplate($templateContent, $quote, $lineItems, $termsContent) {
    $placeholders = [
        '{{CLIENT_NAME}}' => $quote['client_name'],
        '{{CLIENT_EMAIL}}' => $quote['client_email'],
        '{{CLIENT_ADDRESS}}' => $quote['client_address'],
        '{{CLIENT_PHONE}}' => $quote['client_phone'],
        '{{QUOTE_NUMBER}}' => $quote['quote_number'],
        '{{CONTRACT_DATE}}' => date('F j, Y'),
        '{{COMPANY_NAME}}' => 'Kitchen and Bathroom (NE) Ltd',
        '{{LINE_ITEMS}}' => generateLineItemsHTML($lineItems),
        '{{TOTAL_AMOUNT}}' => '£' . number_format($quote['total_amount'], 2),
        '{{TERMS_CONDITIONS}}' => $termsContent,
        '{{INSTALLATION_DATE}}' => '{{INSTALLATION_DATE}}', // To be filled later
        '{{PROJECT_TYPE}}' => ucfirst($quote['project_type'] ?? 'renovation')
    ];
    
    $content = $templateContent;
    foreach ($placeholders as $placeholder => $value) {
        $content = str_replace($placeholder, $value, $content);
    }
    
    return $content;
}

/**
 * Generate HTML for line items
 */
function generateLineItemsHTML($lineItems) {
    if (empty($lineItems)) {
        return '<p>No items specified</p>';
    }
    
    $html = '<table border="1" style="width: 100%; border-collapse: collapse; margin: 20px 0;">
        <thead>
            <tr style="background-color: #f5f5f5;">
                <th style="padding: 10px; text-align: left;">Description</th>
                <th style="padding: 10px; text-align: center;">Quantity</th>
                <th style="padding: 10px; text-align: right;">Unit Price</th>
                <th style="padding: 10px; text-align: right;">Total</th>
            </tr>
        </thead>
        <tbody>';
    
    $grandTotal = 0;
    foreach ($lineItems as $item) {
        $quantity = $item['quantity'] ?? 1;
        $unitPrice = $item['unitPrice'] ?? 0;
        $total = $quantity * $unitPrice;
        $grandTotal += $total;
        
        $html .= '<tr>
            <td style="padding: 8px;">' . htmlspecialchars($item['description'] ?? '') . '</td>
            <td style="padding: 8px; text-align: center;">' . $quantity . '</td>
            <td style="padding: 8px; text-align: right;">£' . number_format($unitPrice, 2) . '</td>
            <td style="padding: 8px; text-align: right;">£' . number_format($total, 2) . '</td>
        </tr>';
    }
    
    $html .= '<tr style="background-color: #f9f9f9; font-weight: bold;">
        <td colspan="3" style="padding: 10px; text-align: right;">Grand Total:</td>
        <td style="padding: 10px; text-align: right;">£' . number_format($grandTotal, 2) . '</td>
    </tr>';
    
    $html .= '</tbody></table>';
    
    return $html;
}

/**
 * Generate scope of work from line items
 */
function generateScopeFromLineItems($lineItems) {
    if (empty($lineItems)) {
        return 'Complete renovation as per uploaded quote and specifications';
    }
    
    $descriptions = array_column($lineItems, 'description');
    return 'Supply and installation of: ' . implode(', ', array_slice($descriptions, 0, 5)) . 
           (count($descriptions) > 5 ? ' and other items as detailed in the quote' : '');
}

/**
 * Get default payment schedule
 */
function getDefaultPaymentSchedule() {
    return [
        ['stage' => 'Deposit on signing', 'percentage' => 25],
        ['stage' => 'On delivery of materials', 'percentage' => 50],
        ['stage' => 'On completion', 'percentage' => 25]
    ];
}
?>