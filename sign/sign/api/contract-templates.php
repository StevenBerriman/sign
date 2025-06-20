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
        case 'GET':
            $templateType = $_GET['type'] ?? 'all';
            
            if ($templateType === 'all') {
                // Get all templates
                $stmt = $db->prepare('SELECT * FROM kb_contract_templates ORDER BY template_type, created_at DESC');
                $stmt->execute();
                $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    'success' => true,
                    'templates' => $templates
                ]);
            } else {
                // Get templates for specific type (kitchen, bathroom, general)
                $stmt = $db->prepare('SELECT * FROM kb_contract_templates WHERE template_type = ? AND active = 1 ORDER BY is_default DESC, created_at DESC');
                $stmt->execute([$templateType]);
                $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    'success' => true,
                    'templates' => $templates,
                    'type' => $templateType
                ]);
            }
            break;
            
        case 'POST':
            // Create new template
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input || !isset($input['templateName']) || !isset($input['content'])) {
                throw new Exception('Template name and content required');
            }
            
            // If setting as default, unset other defaults for this type
            if ($input['isDefault'] ?? false) {
                $stmt = $db->prepare('UPDATE kb_contract_templates SET is_default = 0 WHERE template_type = ?');
                $stmt->execute([$input['templateType'] ?? 'general']);
            }
            
            $stmt = $db->prepare('
                INSERT INTO kb_contract_templates 
                (template_name, template_type, content, placeholders_json, is_default, active, created_by) 
                VALUES (?, ?, ?, ?, ?, 1, ?)
            ');
            
            $stmt->execute([
                $input['templateName'],
                $input['templateType'] ?? 'general',
                $input['content'],
                json_encode($input['placeholders'] ?? []),
                $input['isDefault'] ?? false,
                $current_user['id']
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Template created successfully',
                'templateId' => $db->lastInsertId()
            ]);
            break;
            
        case 'PUT':
            // Update existing template
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input || !isset($input['id'])) {
                throw new Exception('Template ID required');
            }
            
            $templateId = $input['id'];
            
            // If setting as default, unset other defaults for this type
            if ($input['isDefault'] ?? false) {
                $stmt = $db->prepare('UPDATE kb_contract_templates SET is_default = 0 WHERE template_type = ? AND id != ?');
                $stmt->execute([$input['templateType'] ?? 'general', $templateId]);
            }
            
            $stmt = $db->prepare('
                UPDATE kb_contract_templates 
                SET template_name = ?, template_type = ?, content = ?, placeholders_json = ?, 
                    is_default = ?, active = ?, updated_at = NOW()
                WHERE id = ?
            ');
            
            $stmt->execute([
                $input['templateName'],
                $input['templateType'] ?? 'general',
                $input['content'],
                json_encode($input['placeholders'] ?? []),
                $input['isDefault'] ?? false,
                $input['active'] ?? true,
                $templateId
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Template updated successfully'
            ]);
            break;
            
        case 'DELETE':
            // Deactivate template (don't actually delete)
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input || !isset($input['id'])) {
                throw new Exception('Template ID required');
            }
            
            $stmt = $db->prepare('UPDATE kb_contract_templates SET active = 0, updated_at = NOW() WHERE id = ?');
            $stmt->execute([$input['id']]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Template deactivated successfully'
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
?>