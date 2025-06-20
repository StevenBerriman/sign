<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
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

// Get authorization header
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? '';

if (!$authHeader || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
    echo json_encode(['success' => false, 'message' => 'Authorization header required']);
    exit();
}

$token = $matches[1];

try {
    // Validate token against database
    $stmt = $pdo->prepare("SELECT role, email FROM kb_users WHERE auth_token = ?");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if ($user) {
        echo json_encode([
            'success' => true,
            'role' => $user['role'],
            'email' => $user['email']
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid or expired token']);
    }

} catch(Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error validating token: ' . $e->getMessage()]);
}
?>