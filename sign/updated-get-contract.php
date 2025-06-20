<?php
// Updated get-contract.php with monetary payment schedule and document management

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit();
}

// Database configuration
require_once 'db-config.php';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!isset($_GET['token'])) {
        echo json_encode(['success' => false, 'error' => 'Token required']);
        exit();
    }

    $token = $_GET['token'];
    $decoded = base64_decode($token);
    
    if (!$decoded) {
        echo json_encode(['success' => false, 'error' => 'Invalid token']);
        exit();
    }

    $parts = explode('|', $decoded);
    if (count($parts) !== 5) {
        echo json_encode(['success' => false, 'error' => 'Invalid token format']);
        exit();
    }

    list($email, $contractId, $timestamp, $expiry, $hash) = $parts;
    
    // Get contract details
    $stmt = $pdo->prepare("SELECT * FROM kb_contracts WHERE id = ?");
    $stmt->execute([$contractId]);
    $contract = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$contract) {
        echo json_encode(['success' => false, 'error' => 'Contract not found']);
        exit();
    }

    // Get line items
    $stmt = $pdo->prepare("SELECT * FROM kb_contract_items WHERE contract_id = ? ORDER BY id");
    $stmt->execute([$contractId]);
    $lineItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get active terms and conditions
    $stmt = $pdo->prepare("SELECT content FROM kb_terms WHERE is_active = 1 ORDER BY created_at DESC LIMIT 1");
    $stmt->execute();
    $terms = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get contract documents
    $stmt = $pdo->prepare("SELECT * FROM kb_contract_documents WHERE contract_id = ? ORDER BY upload_date DESC");
    $stmt->execute([$contractId]);
    $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate payment schedule with monetary values
    $totalAmount = (float)$contract['total_amount'];
    $depositPercent = (float)$contract['deposit_percent'];
    $paymentSchedule = [];

    if ($depositPercent > 0) {
        $depositAmount = ($totalAmount * $depositPercent) / 100;
        $paymentSchedule[] = [
            'stage' => 'Deposit',
            'description' => 'Required to commence work',
            'percentage' => $depositPercent,
            'amount' => $depositAmount,
            'formatted_amount' => '£' . number_format($depositAmount, 2),
            'due_date' => 'Before work starts'
        ];
    }

    // Parse additional payment schedule if available
    if (!empty($contract['payment_schedule'])) {
        $schedule = json_decode($contract['payment_schedule'], true);
        if ($schedule && is_array($schedule)) {
            foreach ($schedule as $payment) {
                $amount = isset($payment['amount']) ? (float)$payment['amount'] : 0;
                if ($amount > 0) {
                    $paymentSchedule[] = [
                        'stage' => $payment['stage'] ?? 'Payment',
                        'description' => $payment['description'] ?? '',
                        'percentage' => 0, // Not percentage based anymore
                        'amount' => $amount,
                        'formatted_amount' => '£' . number_format($amount, 2),
                        'due_date' => $payment['due_date'] ?? 'As specified'
                    ];
                }
            }
        }
    }

    // If no specific schedule, create final payment
    $totalScheduled = array_sum(array_column($paymentSchedule, 'amount'));
    if ($totalScheduled < $totalAmount) {
        $finalAmount = $totalAmount - $totalScheduled;
        $paymentSchedule[] = [
            'stage' => 'Final Payment',
            'description' => 'Upon completion of work',
            'percentage' => 0,
            'amount' => $finalAmount,
            'formatted_amount' => '£' . number_format($finalAmount, 2),
            'due_date' => 'On completion'
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'contract' => $contract,
            'lineItems' => $lineItems,
            'paymentSchedule' => $paymentSchedule,
            'terms' => $terms['content'] ?? 'No terms available',
            'documents' => $documents,
            'total_amount' => $totalAmount,
            'formatted_total' => '£' . number_format($totalAmount, 2)
        ]
    ]);

} else {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
}
?>
