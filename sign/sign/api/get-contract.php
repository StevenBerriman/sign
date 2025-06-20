<?php
/**
 * Get Contract API - Enhanced with monetary payments + document management
 * KEEPS ALL EXISTING FUNCTIONALITY + adds new features
 * Save as: /sign/api/get-contract.php
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
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
    
    $token = $_GET['token'] ?? null;
    
    if (!$token) {
        throw new Exception('Token is required');
    }
    
    // Validate and decode token
    $tokenData = validateClientToken($token);
    
    if (!$tokenData) {
        throw new Exception('Invalid or expired token');
    }
    
    $contractId = $tokenData['contract_id'];
    $clientEmail = $tokenData['client_email'];
    
    // Get FRESH contract data from database
    $stmt = $db->prepare('SELECT * FROM ' . DB_PREFIX . 'contracts WHERE id = ? AND client_email = ?');
    $stmt->execute([$contractId, $clientEmail]);
    $contract = $stmt->fetch();
    
    if (!$contract) {
        throw new Exception('Contract not found or access denied for ID: ' . $contractId . ' and email: ' . $clientEmail);
    }
    
    // Get terms ONLY from kb_terms database - NO hardcoded fallbacks
    $termsContent = getTermsFromDatabaseOnly($db);
    
    // Handle line items from database
    $lineItems = [];
    if (!empty($contract['lineItems'])) {
        if (is_array($contract['lineItems'])) {
            $lineItems = $contract['lineItems'];
        } else if (is_string($contract['lineItems'])) {
            $lineItems = json_decode($contract['lineItems'], true) ?: [];
        }
    }
    
    // If no line items, create default based on total amount
    if (empty($lineItems)) {
        $totalFromContract = floatval($contract['total_amount'] ?? 0);
        $lineItems = [
            [
                'description' => $contract['scope_of_work'] ?: 'Kitchen/Bathroom Installation',
                'quantity' => 1,
                'unitPrice' => $totalFromContract > 0 ? $totalFromContract : 1200
            ]
        ];
    }
    
    // Handle payment schedule from database
    $paymentSchedule = [];
    if (!empty($contract['paymentSchedule'])) {
        if (is_array($contract['paymentSchedule'])) {
            $paymentSchedule = $contract['paymentSchedule'];
        } else if (is_string($contract['paymentSchedule'])) {
            $paymentSchedule = json_decode($contract['paymentSchedule'], true) ?: [];
        }
    }
    
    // Calculate total amount from line items or use contract total
    $totalAmount = floatval($contract['total_amount'] ?? 0);
    if ($totalAmount == 0) {
        foreach ($lineItems as $item) {
            $quantity = floatval($item['quantity'] ?? 0);
            $unitPrice = floatval($item['unitPrice'] ?? $item['price'] ?? 0);
            $totalAmount += $quantity * $unitPrice;
        }
    }
    
    // Generate MONETARY payment schedule if needed (no percentages)
    if (empty($paymentSchedule) && $totalAmount > 0) {
        $paymentSchedule = generateMonetaryPaymentSchedule($totalAmount);
    }
    
    // Get client-visible documents only
    $documents = getClientVisibleDocuments($db, $contractId);
    
    // Check if already signed
    $stmt = $db->prepare('SELECT id, signed_at FROM ' . DB_PREFIX . 'signatures WHERE contract_id = ? ORDER BY signed_at DESC LIMIT 1');
    $stmt->execute([$contractId]);
    $signature = $stmt->fetch();
    
    $isSigned = !empty($signature);
    
    // Format installation date
    $installationDate = $contract['installation_date'] ?? null;
    if ($installationDate && $installationDate !== '0000-00-00' && $installationDate !== null) {
        $installationDate = date('d/m/Y', strtotime($installationDate));
    } else {
        $installationDate = 'To be confirmed';
    }
    
    // Return fresh data with all enhancements
    echo json_encode([
        'success' => true,
        'contract' => [
            'id' => $contract['id'],
            'clientName' => $contract['client_name'] ?? 'N/A',
            'clientEmail' => $contract['client_email'] ?? 'N/A', 
            'clientAddress' => $contract['client_address'] ?? 'N/A',
            'clientPhone' => $contract['client_phone'] ?? 'N/A',
            'projectType' => ucfirst($contract['project_type'] ?? 'general'),
            'installationDate' => $installationDate,
            'quoteNumber' => $contract['quote_number'] ?? 'N/A',
            'scopeOfWork' => $contract['scope_of_work'] ?? 'Standard installation',
            'status' => $contract['status'] ?? 'pending',
            'lineItems' => $lineItems,
            'paymentSchedule' => $paymentSchedule,
            'totalAmount' => $totalAmount,
            'termsContent' => $termsContent['content'],
            'documents' => $documents, // NEW: Client-visible documents
            'isSigned' => $isSigned,
            'signedAt' => $signature['signed_at'] ?? null,
            'lastUpdated' => $contract['updated_at'] ?? $contract['created_at'],
            // Debug info
            'termsDebug' => $termsContent['debug'],
            'documentsDebug' => count($documents) . ' client-visible documents found' // NEW: Document debug
        ]
    ]);
    
} catch (Exception $e) {
    error_log('Get Contract API Error: ' . $e->getMessage());
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
 * Generate monetary payment schedule (NEW FEATURE)
 * No percentages - only monetary amounts
 */
function generateMonetaryPaymentSchedule($totalAmount) {
    if ($totalAmount <= 0) {
        return [];
    }
    
    if ($totalAmount <= 5000) {
        // Small projects: 50% deposit, 50% completion
        $deposit = round($totalAmount * 0.5, 2);
        $completion = $totalAmount - $deposit; // Ensure exact total
        
        return [
            [
                'stage' => 'Deposit',
                'amount' => $deposit,
                'description' => 'Initial deposit to secure booking'
            ],
            [
                'stage' => 'Completion',
                'amount' => $completion,
                'description' => 'Final payment upon completion'
            ]
        ];
    } elseif ($totalAmount <= 15000) {
        // Medium projects: 30% deposit, 40% materials, 30% completion
        $deposit = round($totalAmount * 0.3, 2);
        $materials = round($totalAmount * 0.4, 2);
        $completion = $totalAmount - $deposit - $materials; // Ensure exact total
        
        return [
            [
                'stage' => 'Deposit',
                'amount' => $deposit,
                'description' => 'Initial deposit (30% of total)'
            ],
            [
                'stage' => 'Materials',
                'amount' => $materials,
                'description' => 'Materials payment (40% of total)'
            ],
            [
                'stage' => 'Completion',
                'amount' => $completion,
                'description' => 'Final payment upon completion'
            ]
        ];
    } else {
        // Large projects: 4-stage payment
        $deposit = round($totalAmount * 0.25, 2);
        $materials = round($totalAmount * 0.35, 2);
        $progress = round($totalAmount * 0.25, 2);
        $completion = $totalAmount - $deposit - $materials - $progress; // Ensure exact total
        
        return [
            [
                'stage' => 'Deposit',
                'amount' => $deposit,
                'description' => 'Initial deposit (25% of total)'
            ],
            [
                'stage' => 'Materials',
                'amount' => $materials,
                'description' => 'Materials and supplies (35% of total)'
            ],
            [
                'stage' => 'Progress',
                'amount' => $progress,
                'description' => 'Progress payment at 50% completion (25% of total)'
            ],
            [
                'stage' => 'Completion',
                'amount' => $completion,
                'description' => 'Final payment upon completion'
            ]
        ];
    }
}

/**
 * Get client-visible documents only (NEW FEATURE)
 */
function getClientVisibleDocuments($db, $contractId) {
    try {
        $stmt = $db->prepare('
            SELECT id, document_name, original_filename, file_type, file_size, upload_date, description, document_category
            FROM kb_contract_documents 
            WHERE contract_id = ? AND is_active = 1 AND client_visible = 1
            ORDER BY upload_date DESC, created_at DESC
        ');
        $stmt->execute([$contractId]);
        $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $documents ?: [];
        
    } catch (Exception $e) {
        // If table doesn't exist yet, return empty array
        error_log('Document loading error (table may not exist yet): ' . $e->getMessage());
        return [];
    }
}

/**
 * Get terms ONLY from kb_terms database - NO hardcoded fallbacks
 * KEEPS YOUR EXISTING FUNCTIONALITY
 */
function getTermsFromDatabaseOnly($db) {
    try {
        // First try: Get active terms (is_active = 1) ordered by most recent
        $stmt = $db->prepare('SELECT * FROM kb_terms WHERE is_active = 1 ORDER BY created_at DESC, id DESC LIMIT 1');
        $stmt->execute();
        $activeTerms = $stmt->fetch();
        
        if ($activeTerms && !empty($activeTerms['content'])) {
            return [
                'content' => formatTermsContent($activeTerms['content']),
                'debug' => 'Active terms from kb_terms database (ID: ' . $activeTerms['id'] . ', Version: ' . $activeTerms['version'] . ', Upload Date: ' . $activeTerms['upload_date'] . ', Created: ' . $activeTerms['created_at'] . ')'
            ];
        }
        
        // Second try: Get the most recent terms regardless of active status
        $stmt = $db->prepare('SELECT * FROM kb_terms ORDER BY created_at DESC, id DESC LIMIT 1');
        $stmt->execute();
        $latestTerms = $stmt->fetch();
        
        if ($latestTerms && !empty($latestTerms['content'])) {
            return [
                'content' => formatTermsContent($latestTerms['content']),
                'debug' => 'Latest terms from kb_terms database (ID: ' . $latestTerms['id'] . ', Version: ' . $latestTerms['version'] . ', Active: ' . ($latestTerms['is_active'] ? 'Yes' : 'No') . ', Upload Date: ' . $latestTerms['upload_date'] . ')'
            ];
        }
        
        // No terms found in database at all
        $stmt = $db->prepare('SELECT COUNT(*) as count FROM kb_terms');
        $stmt->execute();
        $count = $stmt->fetch();
        
        return [
            'content' => '<div class="terms-content"><h3>Terms and Conditions</h3><p><strong>No terms and conditions have been configured.</strong></p><p>Please contact the administrator to set up the terms and conditions.</p></div>',
            'debug' => 'NO TERMS FOUND in kb_terms database. Total rows in table: ' . $count['count'] . '. Please add terms via admin interface.'
        ];
        
    } catch (Exception $e) {
        return [
            'content' => '<div class="terms-content"><h3>Terms and Conditions</h3><p><strong>Error loading terms from database.</strong></p><p>Please contact support.</p></div>',
            'debug' => 'DATABASE ERROR accessing kb_terms: ' . $e->getMessage()
        ];
    }
}

/**
 * Format terms content for display
 * KEEPS YOUR EXISTING FUNCTIONALITY
 */
function formatTermsContent($content) {
    // If content already has proper HTML structure, return as-is
    if (strpos($content, '<div') !== false || strpos($content, '<h') !== false) {
        return $content;
    }
    
    // If it's plain text, wrap it in basic HTML structure
    return '<div class="terms-content"><h3>Terms and Conditions - Kitchen & Bathroom (NE) Ltd</h3>' . nl2br(htmlspecialchars($content)) . '</div>';
}

/**
 * Validate client access token
 * KEEPS YOUR EXISTING FUNCTIONALITY
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
?>