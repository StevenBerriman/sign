<?php
/**
 * Dashboard API Endpoint
 * Save as: /api/dashboard.php
 */

// This file is included from index.php, so all setup is already done

// Require authentication
if (!$current_user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
    exit;
}

// Only GET method allowed
if ($method !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$sub_endpoint = $segments[1] ?? 'summary';

try {
    $db = getDB();
    
    switch ($sub_endpoint) {
        case 'summary':
            // Get dashboard summary based on user role
            if ($current_user['role'] === 'admin') {
                // Admin dashboard - all contracts
                $summary = getAdminDashboard($db);
            } else {
                // Client dashboard - only their contracts
                $summary = getClientDashboard($db, $current_user['email']);
            }
            
            echo json_encode(['success' => true, 'data' => $summary]);
            break;
            
        case 'recent-activity':
            // Get recent activity
            $limit = min(50, intval($_GET['limit'] ?? 10));
            
            if ($current_user['role'] === 'admin') {
                $stmt = $db->prepare('
                    SELECT al.*, u.email as user_email, c.quote_number, c.client_name
                    FROM ' . DB_PREFIX . 'activity_log al
                    LEFT JOIN ' . DB_PREFIX . 'users u ON al.user_id = u.id
                    LEFT JOIN ' . DB_PREFIX . 'contracts c ON al.contract_id = c.id
                    ORDER BY al.created_at DESC
                    LIMIT ?
                ');
                $stmt->execute([$limit]);
            } else {
                $stmt = $db->prepare('
                    SELECT al.*, c.quote_number, c.client_name
                    FROM ' . DB_PREFIX . 'activity_log al
                    JOIN ' . DB_PREFIX . 'contracts c ON al.contract_id = c.id
                    WHERE c.client_email = ?
                    ORDER BY al.created_at DESC
                    LIMIT ?
                ');
                $stmt->execute([$current_user['email'], $limit]);
            }
            
            $activities = $stmt->fetchAll();
            
            echo json_encode(['success' => true, 'activities' => $activities]);
            break;
            
        case 'payment-schedule':
            // Get upcoming payments
            if ($current_user['role'] === 'admin') {
                $stmt = $db->prepare('
                    SELECT ps.*, c.quote_number, c.client_name, c.client_email,
                           (SELECT SUM(li.total_price) FROM ' . DB_PREFIX . 'line_items li WHERE li.contract_id = c.id) as contract_total
                    FROM ' . DB_PREFIX . 'payment_schedule ps
                    JOIN ' . DB_PREFIX . 'contracts c ON ps.contract_id = c.id
                    WHERE ps.paid = 0 AND c.status = "signed"
                    ORDER BY ps.due_date ASC
                    LIMIT 20
                ');
                $stmt->execute();
            } else {
                $stmt = $db->prepare('
                    SELECT ps.*, c.quote_number, c.client_name,
                           (SELECT SUM(li.total_price) FROM ' . DB_PREFIX . 'line_items li WHERE li.contract_id = c.id) as contract_total
                    FROM ' . DB_PREFIX . 'payment_schedule ps
                    JOIN ' . DB_PREFIX . 'contracts c ON ps.contract_id = c.id
                    WHERE ps.paid = 0 AND c.status = "signed" AND c.client_email = ?
                    ORDER BY ps.due_date ASC
                ');
                $stmt->execute([$current_user['email']]);
            }
            
            $payments = $stmt->fetchAll();
            
            // Calculate amounts
            foreach ($payments as &$payment) {
                $payment['amount'] = $payment['contract_total'] * $payment['percentage'] / 100;
            }
            
            echo json_encode(['success' => true, 'payments' => $payments]);
            break;
            
        case 'charts':
            // Get chart data for admin dashboard
            if ($current_user['role'] !== 'admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Unauthorized']);
                exit;
            }
            
            $chartData = [
                'monthly_contracts' => getMonthlyContracts($db),
                'project_types' => getProjectTypeDistribution($db),
                'contract_values' => getContractValues($db),
                'conversion_rate' => getConversionRate($db)
            ];
            
            echo json_encode(['success' => true, 'charts' => $chartData]);
            break;
            
        default:
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Endpoint not found']);
            break;
    }
    
} catch (Exception $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
}

/**
 * Get admin dashboard summary
 */
function getAdminDashboard($db) {
    $summary = [];
    
    // Total contracts by status
    $stmt = $db->prepare('
        SELECT status, COUNT(*) as count 
        FROM ' . DB_PREFIX . 'contracts 
        GROUP BY status
    ');
    $stmt->execute();
    $statusCounts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $summary['total_contracts'] = array_sum($statusCounts);
    $summary['pending_contracts'] = $statusCounts['pending'] ?? 0;
    $summary['signed_contracts'] = $statusCounts['signed'] ?? 0;
    $summary['completed_contracts'] = $statusCounts['completed'] ?? 0;
    
    // Total contract value
    $stmt = $db->prepare('
        SELECT SUM(li.total_price) as total 
        FROM ' . DB_PREFIX . 'line_items li
        JOIN ' . DB_PREFIX . 'contracts c ON li.contract_id = c.id
        WHERE c.status IN ("signed", "completed")
    ');
    $stmt->execute();
    $result = $stmt->fetch();
    $summary['total_value'] = $result['total'] ?? 0;
    
    // This month's contracts
    $stmt = $db->prepare('
        SELECT COUNT(*) as count 
        FROM ' . DB_PREFIX . 'contracts 
        WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) 
        AND YEAR(created_at) = YEAR(CURRENT_DATE())
    ');
    $stmt->execute();
    $result = $stmt->fetch();
    $summary['monthly_contracts'] = $result['count'];
    
    // Pending signatures
    $stmt = $db->prepare('
        SELECT COUNT(*) as count 
        FROM ' . DB_PREFIX . 'contracts 
        WHERE status = "pending"
    ');
    $stmt->execute();
    $result = $stmt->fetch();
    $summary['pending_signatures'] = $result['count'];
    
    // Recent contracts
    $stmt = $db->prepare('
        SELECT c.*, 
               (SELECT SUM(li.total_price) FROM ' . DB_PREFIX . 'line_items li WHERE li.contract_id = c.id) as total_value,
               (SELECT COUNT(*) FROM ' . DB_PREFIX . 'signatures s WHERE s.contract_id = c.id) as signature_count
        FROM ' . DB_PREFIX . 'contracts c
        ORDER BY c.created_at DESC
        LIMIT 5
    ');
    $stmt->execute();
    $summary['recent_contracts'] = $stmt->fetchAll();
    
    return $summary;
}

/**
 * Get client dashboard summary
 */
function getClientDashboard($db, $email) {
    $summary = [];
    
    // Get client's contracts
    $stmt = $db->prepare('
        SELECT c.*, 
               (SELECT SUM(li.total_price) FROM ' . DB_PREFIX . 'line_items li WHERE li.contract_id = c.id) as total_value,
               (SELECT COUNT(*) FROM ' . DB_PREFIX . 'signatures s WHERE s.contract_id = c.id) as signature_count
        FROM ' . DB_PREFIX . 'contracts c
        WHERE c.client_email = ?
        ORDER BY c.created_at DESC
    ');
    $stmt->execute([$email]);
    $contracts = $stmt->fetchAll();
    
    $summary['contracts'] = $contracts;
    $summary['total_contracts'] = count($contracts);
    
    // Count by status
    $statusCounts = array_count_values(array_column($contracts, 'status'));
    $summary['pending_contracts'] = $statusCounts['pending'] ?? 0;
    $summary['signed_contracts'] = $statusCounts['signed'] ?? 0;
    $summary['completed_contracts'] = $statusCounts['completed'] ?? 0;
    
    // Total value of signed/completed contracts
    $totalValue = 0;
    foreach ($contracts as $contract) {
        if (in_array($contract['status'], ['signed', 'completed'])) {
            $totalValue += $contract['total_value'];
        }
    }
    $summary['total_value'] = $totalValue;
    
    // Upcoming payments
    $stmt = $db->prepare('
        SELECT ps.*, c.quote_number,
               (SELECT SUM(li.total_price) FROM ' . DB_PREFIX . 'line_items li WHERE li.contract_id = c.id) as contract_total
        FROM ' . DB_PREFIX . 'payment_schedule ps
        JOIN ' . DB_PREFIX . 'contracts c ON ps.contract_id = c.id
        WHERE c.client_email = ? AND ps.paid = 0
        ORDER BY ps.due_date ASC
        LIMIT 5
    ');
    $stmt->execute([$email]);
    $payments = $stmt->fetchAll();
    
    foreach ($payments as &$payment) {
        $payment['amount'] = $payment['contract_total'] * $payment['percentage'] / 100;
    }
    
    $summary['upcoming_payments'] = $payments;
    
    return $summary;
}

/**
 * Get monthly contracts data for charts
 */
function getMonthlyContracts($db) {
    $stmt = $db->prepare('
        SELECT 
            DATE_FORMAT(created_at, "%Y-%m") as month,
            COUNT(*) as count,
            SUM(CASE WHEN status = "signed" THEN 1 ELSE 0 END) as signed_count
        FROM ' . DB_PREFIX . 'contracts
        WHERE created_at >= DATE_SUB(CURRENT_DATE(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(created_at, "%Y-%m")
        ORDER BY month ASC
    ');
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Get project type distribution
 */
function getProjectTypeDistribution($db) {
    $stmt = $db->prepare('
        SELECT project_type, COUNT(*) as count
        FROM ' . DB_PREFIX . 'contracts
        GROUP BY project_type
    ');
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Get contract values over time
 */
function getContractValues($db) {
    $stmt = $db->prepare('
        SELECT 
            DATE_FORMAT(c.created_at, "%Y-%m") as month,
            SUM(li.total_price) as total_value
        FROM ' . DB_PREFIX . 'contracts c
        JOIN ' . DB_PREFIX . 'line_items li ON c.id = li.contract_id
        WHERE c.status IN ("signed", "completed")
        AND c.created_at >= DATE_SUB(CURRENT_DATE(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(c.created_at, "%Y-%m")
        ORDER BY month ASC
    ');
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Get conversion rate (signed vs total)
 */
function getConversionRate($db) {
    $stmt = $db->prepare('
        SELECT 
            DATE_FORMAT(created_at, "%Y-%m") as month,
            COUNT(*) as total,
            SUM(CASE WHEN status IN ("signed", "completed") THEN 1 ELSE 0 END) as converted
        FROM ' . DB_PREFIX . 'contracts
        WHERE created_at >= DATE_SUB(CURRENT_DATE(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(created_at, "%Y-%m")
        ORDER BY month ASC
    ');
    $stmt->execute();
    
    $data = $stmt->fetchAll();
    foreach ($data as &$row) {
        $row['conversion_rate'] = $row['total'] > 0 ? round(($row['converted'] / $row['total']) * 100, 2) : 0;
    }
    
    return $data;
}
