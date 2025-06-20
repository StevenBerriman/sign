<?php
/**
 * Main API Router - This file sets up everything the other API files need
 */

// Enable CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Content-Type: application/json; charset=UTF-8');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Load configuration
require_once '../config/config.php';

// Initialize session
session_name(SESSION_NAME);
session_start();

// Parse request
$method = $_SERVER['REQUEST_METHOD'];
$request_uri = $_SERVER['REQUEST_URI'];
$path = parse_url($request_uri, PHP_URL_PATH);

// Extract segments after /api/
$segments = explode('/', $path);
$api_index = array_search('api', $segments);
if ($api_index !== false) {
    $segments = array_slice($segments, $api_index + 1);
    $segments = array_values($segments); // Reset keys to start at 0
}

// Get request body
$input = json_decode(file_get_contents('php://input'), true);

// Initialize current user (normally from JWT, but simplified for now)
$current_user = null;

// Simple JWT verification (if provided)
$auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (preg_match('/Bearer\s+(.*)$/i', $auth_header, $matches)) {
    $token = $matches[1];
    // For now, just extract user ID from simple token
    if (preg_match('/simple-token-(\d+)/', $token, $matches)) {
        $current_user = ['id' => $matches[1], 'role' => 'admin'];
    }
}

// Route to appropriate endpoint
$endpoint = $segments[0] ?? '';

switch ($endpoint) {
    case 'auth':
        require_once 'auth.php';
        break;
        
    case 'contracts':
        require_once 'contracts.php';
        break;
        
    case 'users':
        require_once 'users.php';
        break;
        
    case 'upload':
        require_once 'upload.php';
        break;
        
    case 'email':
        require_once 'email.php';
        break;
        
    case 'dashboard':
        require_once 'dashboard.php';
        break;
        
    default:
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Endpoint not found',
            'requested' => $endpoint,
            'segments' => $segments
        ]);
        break;
}