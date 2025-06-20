<?php
/**
 * Authentication API Endpoint
 * Save as: /api/auth.php
 */

// This file is included from index.php, so all setup is already done

$sub_endpoint = $segments[1] ?? '';

switch ($sub_endpoint) {
    case 'login':
        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            exit;
        }
        
        $email = $input['email'] ?? '';
        $password = $input['password'] ?? '';
        
        if (!$email || !$password) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Email and password required']);
            exit;
        }
        
        try {
            $db = getDB();
            $stmt = $db->prepare('SELECT id, email, password_hash, role, company_name FROM ' . DB_PREFIX . 'users WHERE email = ?');
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if (!$user || !password_verify($password, $user['password_hash'])) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Invalid credentials']);
                exit;
            }
            
            // Generate JWT
            $payload = [
                'user_id' => $user['id'],
                'email' => $user['email'],
                'role' => $user['role'],
                'exp' => time() + SESSION_LIFETIME
            ];
            
            $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
            $payload = json_encode($payload);
            
            $base64Header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
            $base64Payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
            
            $signature = hash_hmac('sha256', $base64Header . '.' . $base64Payload, JWT_SECRET, true);
            $base64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
            
            $jwt = $base64Header . '.' . $base64Payload . '.' . $base64Signature;
            
            // Log activity
            $stmt = $db->prepare('INSERT INTO ' . DB_PREFIX . 'activity_log (user_id, action, ip_address) VALUES (?, ?, ?)');
            $stmt->execute([$user['id'], 'login', $_SERVER['REMOTE_ADDR']]);
            
            echo json_encode([
                'success' => true,
                'token' => $jwt,
                'user' => [
                    'id' => $user['id'],
                    'email' => $user['email'],
                    'role' => $user['role'],
                    'company_name' => $user['company_name']
                ]
            ]);
            
        } catch (Exception $e) {
            error_log($e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Database error']);
        }
        break;
        
    case 'logout':
        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            exit;
        }
        
        if ($current_user) {
            try {
                $db = getDB();
                $stmt = $db->prepare('INSERT INTO ' . DB_PREFIX . 'activity_log (user_id, action, ip_address) VALUES (?, ?, ?)');
                $stmt->execute([$current_user['id'], 'logout', $_SERVER['REMOTE_ADDR']]);
            } catch (Exception $e) {
                error_log($e->getMessage());
            }
        }
        
        session_destroy();
        echo json_encode(['success' => true]);
        break;
        
    case 'check':
        if ($current_user) {
            echo json_encode([
                'success' => true,
                'authenticated' => true,
                'user' => $current_user
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'authenticated' => false
            ]);
        }
        break;
        
    case 'register':
        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            exit;
        }
        
        // Only admins can register new users
        if (!$current_user || $current_user['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }
        
        $email = $input['email'] ?? '';
        $password = $input['password'] ?? '';
        $role = $input['role'] ?? 'client';
        $company_name = $input['company_name'] ?? '';
        
        if (!$email || !$password) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Email and password required']);
            exit;
        }
        
        try {
            $db = getDB();
            
            // Check if email exists
            $stmt = $db->prepare('SELECT id FROM ' . DB_PREFIX . 'users WHERE email = ?');
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Email already exists']);
                exit;
            }
            
            // Create user
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare('INSERT INTO ' . DB_PREFIX . 'users (email, password_hash, role, company_name) VALUES (?, ?, ?, ?)');
            $stmt->execute([$email, $password_hash, $role, $company_name]);
            
            echo json_encode([
                'success' => true,
                'user_id' => $db->lastInsertId()
            ]);
            
        } catch (Exception $e) {
            error_log($e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Database error']);
        }
        break;
        
    case 'reset-password':
        // TODO: Implement password reset functionality
        http_response_code(501);
        echo json_encode(['success' => false, 'error' => 'Not implemented']);
        break;
        
    default:
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Endpoint not found']);
        break;
}
