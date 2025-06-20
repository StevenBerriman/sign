<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Database configuration
$host = 'localhost';
$dbname = 'kb_contracts';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Verify authentication
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? '';

if (!$authHeader || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
    echo json_encode(['success' => false, 'message' => 'Authorization required']);
    exit();
}

$token = $matches[1];

// Validate token
$stmt = $pdo->prepare("SELECT role FROM kb_users WHERE auth_token = ?");
$stmt->execute([$token]);
$user = $stmt->fetch();

if (!$user || $user['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Invalid authorization']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Extract all the new fields
    $clientName = $input['clientName'] ?? '';
    $clientEmail = $input['clientEmail'] ?? '';
    $clientPhone = $input['clientPhone'] ?? '';
    $clientAddress = $input['clientAddress'] ?? '';
    $installationDate = $input['installationDate'] ?? null;
    $contractHtml = $input['contractHtml'] ?? '';
    $templateType = $input['templateType'] ?? 'general';
    $totalAmount = $input['totalAmount'] ?? 0;
    $status = $input['status'] ?? 'pending';
    
    // Payment schedule
    $paymentSchedule = $input['paymentSchedule'] ?? [];
    $depositPercent = $paymentSchedule['deposit'] ?? 20;
    $materialsPercent = $paymentSchedule['materials'] ?? 50;
    $completionPercent = $paymentSchedule['completion'] ?? 30;
    
    // Line items
    $lineItems = $input['lineItems'] ?? [];

    // Validation
    if (empty($clientName) || empty($clientEmail)) {
        echo json_encode(['success' => false, 'message' => 'Client name and email are required']);
        exit();
    }

    if (!filter_var($clientEmail, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email format']);
        exit();
    }

    try {
        $pdo->beginTransaction();

        // Check if this is an update or new contract
        $contractId = $input['contractId'] ?? null;
        
        if ($contractId) {
            // Update existing contract
            $stmt = $pdo->prepare("
                UPDATE kb_quotes SET 
                    client_name = ?, 
                    client_email = ?, 
                    client_phone = ?,
                    client_address = ?,
                    installation_date = ?,
                    contract_html = ?, 
                    template_type = ?, 
                    total_amount = ?, 
                    status = ?,
                    payment_deposit_percent = ?,
                    payment_materials_percent = ?,
                    payment_completion_percent = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([
                $clientName, $clientEmail, $clientPhone, $clientAddress,
                $installationDate, $contractHtml, $templateType, $totalAmount, 
                $status, $depositPercent, $materialsPercent, $completionPercent,
                $contractId
            ]);
            
            // Delete existing line items
            $stmt = $pdo->prepare("DELETE FROM kb_quote_items WHERE quote_id = ?");
            $stmt->execute([$contractId]);
            
        } else {
            // Create new contract
            $stmt = $pdo->prepare("
                INSERT INTO kb_quotes (
                    client_name, client_email, client_phone, client_address,
                    installation_date, contract_html, template_type, total_amount, 
                    status, payment_deposit_percent, payment_materials_percent, 
                    payment_completion_percent, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $clientName, $clientEmail, $clientPhone, $clientAddress,
                $installationDate, $contractHtml, $templateType, $totalAmount, 
                $status, $depositPercent, $materialsPercent, $completionPercent
            ]);
            
            $contractId = $pdo->lastInsertId();
        }

        // Save line items
        if (!empty($lineItems)) {
            $stmt = $pdo->prepare("
                INSERT INTO kb_quote_items (quote_id, description, quantity, price, total) 
                VALUES (?, ?, ?, ?, ?)
            ");
            
            foreach ($lineItems as $item) {
                $description = $item['description'] ?? '';
                $quantity = $item['quantity'] ?? 1;
                $price = $item['price'] ?? 0;
                $total = $quantity * $price;
                
                $stmt->execute([$contractId, $description, $quantity, $price, $total]);
            }
        }

        $pdo->commit();

        echo json_encode([
            'success' => true, 
            'message' => 'Contract saved successfully',
            'contract_id' => $contractId,
            'status' => $status
        ]);

    } catch(Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Error saving contract: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
?>