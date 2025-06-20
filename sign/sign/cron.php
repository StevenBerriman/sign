<?php
/**
 * Cron Job Script - Run every 5 minutes
 * Save as: /cron.php
 * 
 * Add to crontab:
 * */5 * * * * /usr/bin/php /path/to/your/site/cron.php
 */

// Prevent web access
if (php_sapi_name() !== 'cli') {
    die('This script can only be run from the command line');
}

// Load configuration
require_once __DIR__ . '/config/config.php';

// Tasks to run
$tasks = [
    'cleanupExpiredTokens',
    'cleanupOldTempFiles',
    'sendPaymentReminders',
    'updateContractStatuses'
];

// Run each task
foreach ($tasks as $task) {
    echo "[" . date('Y-m-d H:i:s') . "] Running task: $task\n";
    try {
        $task();
        echo "[" . date('Y-m-d H:i:s') . "] Task completed: $task\n";
    } catch (Exception $e) {
        error_log("Cron task error ($task): " . $e->getMessage());
        echo "[" . date('Y-m-d H:i:s') . "] Task failed: $task - " . $e->getMessage() . "\n";
    }
}

/**
 * Clean up expired access tokens
 */
function cleanupExpiredTokens() {
    $db = getDB();
    
    // Delete tokens older than their expiry date
    $stmt = $db->prepare('
        DELETE FROM ' . DB_PREFIX . 'access_tokens 
        WHERE expires_at < NOW() OR (used = 1 AND used_at < DATE_SUB(NOW(), INTERVAL 1 DAY))
    ');
    $stmt->execute();
    
    $deleted = $stmt->rowCount();
    if ($deleted > 0) {
        echo "  - Deleted $deleted expired tokens\n";
    }
}

/**
 * Clean up old temporary files
 */
function cleanupOldTempFiles() {
    $tempDir = TEMP_PATH;
    if (!is_dir($tempDir)) {
        return;
    }
    
    $deleted = 0;
    $files = scandir($tempDir);
    
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        
        $filepath = $tempDir . '/' . $file;
        
        // Delete files older than 24 hours
        if (is_file($filepath) && filemtime($filepath) < time() - 86400) {
            if (unlink($filepath)) {
                $deleted++;
            }
        }
    }
    
    if ($deleted > 0) {
        echo "  - Deleted $deleted old temporary files\n";
    }
}

/**
 * Send payment reminders for upcoming due dates
 */
function sendPaymentReminders() {
    $db = getDB();
    
    // Find payments due in the next 3 days that haven't been paid
    $stmt = $db->prepare('
        SELECT ps.*, c.client_name, c.client_email, c.quote_number, c.project_type,
               (SELECT SUM(li.total_price) FROM ' . DB_PREFIX . 'line_items li WHERE li.contract_id = c.id) as contract_total
        FROM ' . DB_PREFIX . 'payment_schedule ps
        JOIN ' . DB_PREFIX . 'contracts c ON ps.contract_id = c.id
        WHERE ps.paid = 0 
        AND ps.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)
        AND c.status = "signed"
    ');
    $stmt->execute();
    
    $reminders = $stmt->fetchAll();
    
    foreach ($reminders as $payment) {
        // Calculate payment amount
        $amount = ($payment['contract_total'] * $payment['percentage'] / 100);
        
        // Send reminder email (implement your email logic here)
        echo "  - Payment reminder: {$payment['client_name']} - £" . number_format($amount, 2) . " due on {$payment['due_date']}\n";
        
        // Log the reminder
        $stmt = $db->prepare('
            INSERT INTO ' . DB_PREFIX . 'activity_log 
            (contract_id, action, details)
            VALUES (?, ?, ?)
        ');
        $stmt->execute([
            $payment['contract_id'],
            'payment_reminder_sent',
            json_encode([
                'stage' => $payment['stage_description'],
                'amount' => $amount,
                'due_date' => $payment['due_date']
            ])
        ]);
    }
}

/**
 * Update contract statuses based on dates
 */
function updateContractStatuses() {
    $db = getDB();
    
    // Mark contracts as overdue if installation date has passed and not completed
    $stmt = $db->prepare('
        UPDATE ' . DB_PREFIX . 'contracts 
        SET status = "overdue" 
        WHERE status IN ("pending", "signed") 
        AND installation_date < CURDATE()
    ');
    $stmt->execute();
    
    $updated = $stmt->rowCount();
    if ($updated > 0) {
        echo "  - Marked $updated contracts as overdue\n";
    }
    
    // Archive old completed contracts (optional)
    $stmt = $db->prepare('
        UPDATE ' . DB_PREFIX . 'contracts 
        SET status = "archived" 
        WHERE status = "completed" 
        AND updated_at < DATE_SUB(NOW(), INTERVAL 1 YEAR)
    ');
    $stmt->execute();
    
    $archived = $stmt->rowCount();
    if ($archived > 0) {
        echo "  - Archived $archived old contracts\n";
    }
}

echo "[" . date('Y-m-d H:i:s') . "] Cron job completed\n\n";
