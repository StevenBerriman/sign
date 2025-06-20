<?php
/**
 * Users API Endpoint
 * Save as: /api/users.php
 */

// This file is included from index.php, so all setup is already done

// Require authentication
if (!$current_user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
    exit;
}

// Only admins can access user management
if ($current_user['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = $segments[1] ?? '';
$sub_action = $segments[2] ?? '';

switch ($method) {
    case 'GET':
        if ($user_id) {
            // Get specific user
            try {
                $db = getDB();
                $stmt = $db->prepare('
                    SELECT id, email, role, company_name, created_at, updated_at 
                    FROM ' . DB_PREFIX . 'users 
                    WHERE id = ?
                ');
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();
                
                if (!$user) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'User not found']);
                    exit;
                }
                
                // Get user statistics
                $stmt = $db->prepare('
                    SELECT COUNT(*) as contract_count 
                    FROM ' . DB_PREFIX . 'contracts 
                    WHERE created_by = ?
                ');
                $stmt->execute([$user_id]);
                $stats = $stmt->fetch();
                $user['contract_count'] = $stats['contract_count'];
                
                echo json_encode(['success' => true, 'user' => $user]);
                
            } catch (Exception $e) {
                error_log($e->getMessage());
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Database error']);
            }
        } else {
            // List all users
            try {
                $db = getDB();
                
                $query = '
                    SELECT u.id, u.email, u.role, u.company_name, u.created_at, u.updated_at,
                           COUNT(DISTINCT c.id) as contract_count,
                           MAX(al.created_at) as last_active
                    FROM ' . DB_PREFIX . 'users u
                    LEFT JOIN ' . DB_PREFIX . 'contracts c ON u.id = c.created_by
                    LEFT JOIN ' . DB_PREFIX . 'activity_log al ON u.id = al.user_id
                    GROUP BY u.id
                    ORDER BY u.created_at DESC
                ';
                
                $stmt = $db->prepare($query);
                $stmt->execute();
                $users = $stmt->fetchAll();
                
                echo json_encode(['success' => true, 'users' => $users]);
                
            } catch (Exception $e) {
                error_log($e->getMessage());
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Database error']);
            }
        }
        break;
        
    case 'POST':
        // Create new user
        $email = $input['email'] ?? '';
        $password = $input['password'] ?? '';
        $role = $input['role'] ?? 'client';
        $company_name = $input['company_name'] ?? '';
        
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Valid email required']);
            exit;
        }
        
        if (!$password || strlen($password) < 8) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Password must be at least 8 characters']);
            exit;
        }
        
        if (!in_array($role, ['admin', 'client'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid role']);
            exit;
        }
        
        try {
            $db = getDB();
            
            // Check if email already exists
            $stmt = $db->prepare('SELECT id FROM ' . DB_PREFIX . 'users WHERE email = ?');
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Email already exists']);
                exit;
            }
            
            // Create user
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare('
                INSERT INTO ' . DB_PREFIX . 'users 
                (email, password_hash, role, company_name)
                VALUES (?, ?, ?, ?)
            ');
            $stmt->execute([$email, $password_hash, $role, $company_name]);
            
            $new_user_id = $db->lastInsertId();
            
            // Log activity
            $stmt = $db->prepare('
                INSERT INTO ' . DB_PREFIX . 'activity_log 
                (user_id, action, details, ip_address)
                VALUES (?, ?, ?, ?)
            ');
            $stmt->execute([
                $current_user['id'],
                'user_created',
                json_encode(['new_user_id' => $new_user_id, 'email' => $email, 'role' => $role]),
                $_SERVER['REMOTE_ADDR']
            ]);
            
            echo json_encode([
                'success' => true,
                'user_id' => $new_user_id,
                'message' => 'User created successfully'
            ]);
            
        } catch (Exception $e) {
            error_log($e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Database error']);
        }
        break;
        
    case 'PUT':
        if (!$user_id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'User ID required']);
            exit;
        }
        
        if ($sub_action === 'password') {
            // Change password
            $new_password = $input['password'] ?? '';
            
            if (!$new_password || strlen($new_password) < 8) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Password must be at least 8 characters']);
                exit;
            }
            
            try {
                $db = getDB();
                
                $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $db->prepare('
                    UPDATE ' . DB_PREFIX . 'users 
                    SET password_hash = ?, updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                ');
                $stmt->execute([$password_hash, $user_id]);
                
                if ($stmt->rowCount() === 0) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'User not found']);
                    exit;
                }
                
                // Log activity
                $stmt = $db->prepare('
                    INSERT INTO ' . DB_PREFIX . 'activity_log 
                    (user_id, action, details, ip_address)
                    VALUES (?, ?, ?, ?)
                ');
                $stmt->execute([
                    $current_user['id'],
                    'password_changed',
                    json_encode(['target_user_id' => $user_id]),
                    $_SERVER['REMOTE_ADDR']
                ]);
                
                echo json_encode(['success' => true, 'message' => 'Password updated successfully']);
                
            } catch (Exception $e) {
                error_log($e->getMessage());
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Database error']);
            }
        } else {
            // Update user details
            try {
                $db = getDB();
                
                $updates = [];
                $params = [];
                
                if (isset($input['email'])) {
                    $updates[] = 'email = ?';
                    $params[] = $input['email'];
                }
                
                if (isset($input['role'])) {
                    $updates[] = 'role = ?';
                    $params[] = $input['role'];
                }
                
                if (isset($input['company_name'])) {
                    $updates[] = 'company_name = ?';
                    $params[] = $input['company_name'];
                }
                
                if (empty($updates)) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'No fields to update']);
                    exit;
                }
                
                $updates[] = 'updated_at = CURRENT_TIMESTAMP';
                $params[] = $user_id;
                
                $stmt = $db->prepare('
                    UPDATE ' . DB_PREFIX . 'users 
                    SET ' . implode(', ', $updates) . '
                    WHERE id = ?
                ');
                $stmt->execute($params);
                
                if ($stmt->rowCount() === 0) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'User not found']);
                    exit;
                }
                
                echo json_encode(['success' => true, 'message' => 'User updated successfully']);
                
            } catch (Exception $e) {
                error_log($e->getMessage());
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Database error']);
            }
        }
        break;
        
    case 'DELETE':
        if (!$user_id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'User ID required']);
            exit;
        }
        
        // Prevent deleting own account
        if ($user_id == $current_user['id']) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Cannot delete your own account']);
            exit;
        }
        
        try {
            $db = getDB();
            
            // Check if user has contracts
            $stmt = $db->prepare('
                SELECT COUNT(*) as count 
                FROM ' . DB_PREFIX . 'contracts 
                WHERE created_by = ?
            ');
            $stmt->execute([$user_id]);
            $result = $stmt->fetch();
            
            if ($result['count'] > 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Cannot delete user with existing contracts']);
                exit;
            }
            
            // Delete user
            $stmt = $db->prepare('DELETE FROM ' . DB_PREFIX . 'users WHERE id = ?');
            $stmt->execute([$user_id]);
            
            if ($stmt->rowCount() === 0) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'User not found']);
                exit;
            }
            
            // Log activity
            $stmt = $db->prepare('
                INSERT INTO ' . DB_PREFIX . 'activity_log 
                (user_id, action, details, ip_address)
                VALUES (?, ?, ?, ?)
            ');
            $stmt->execute([
                $current_user['id'],
                'user_deleted',
                json_encode(['deleted_user_id' => $user_id]),
                $_SERVER['REMOTE_ADDR']
            ]);
            
            echo json_encode(['success' => true, 'message' => 'User deleted successfully']);
            
        } catch (Exception $e) {
            error_log($e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Database error']);
        }
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
        break;
}
