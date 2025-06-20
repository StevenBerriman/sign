-- Kitchen & Bathroom Digital Contracts Database Schema
-- Generated: 2025-06-04T12:18:18.552Z

-- Users table
CREATE TABLE IF NOT EXISTS kb_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(50) NOT NULL DEFAULT 'client',
    company_name VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Contracts table
CREATE TABLE IF NOT EXISTS kb_contracts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quote_number VARCHAR(100) UNIQUE NOT NULL,
    client_name VARCHAR(255) NOT NULL,
    client_email VARCHAR(255) NOT NULL,
    client_address TEXT,
    client_phone VARCHAR(50),
    project_type VARCHAR(50) NOT NULL,
    installation_date DATE,
    scope_of_work TEXT,
    status VARCHAR(50) DEFAULT 'pending',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES kb_users(id) ON DELETE SET NULL,
    INDEX idx_client_email (client_email),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Contract line items
CREATE TABLE IF NOT EXISTS kb_line_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contract_id INT NOT NULL,
    description TEXT NOT NULL,
    quantity DECIMAL(10,2) NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    total_price DECIMAL(10,2) AS (quantity * unit_price) STORED,
    sort_order INT DEFAULT 0,
    FOREIGN KEY (contract_id) REFERENCES kb_contracts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Payment schedule
CREATE TABLE IF NOT EXISTS kb_payment_schedule (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contract_id INT NOT NULL,
    stage_description VARCHAR(255) NOT NULL,
    percentage DECIMAL(5,2) NOT NULL,
    amount DECIMAL(10,2),
    due_date DATE,
    paid BOOLEAN DEFAULT FALSE,
    paid_date DATE,
    sort_order INT DEFAULT 0,
    FOREIGN KEY (contract_id) REFERENCES kb_contracts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- File attachments
CREATE TABLE IF NOT EXISTS kb_attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contract_id INT NOT NULL,
    file_type VARCHAR(50) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (contract_id) REFERENCES kb_contracts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Contract signatures
CREATE TABLE IF NOT EXISTS kb_signatures (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contract_id INT NOT NULL,
    signature_data LONGTEXT NOT NULL,
    signed_by_name VARCHAR(255),
    signed_by_email VARCHAR(255),
    signed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(50),
    user_agent TEXT,
    FOREIGN KEY (contract_id) REFERENCES kb_contracts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Terms acceptance log
CREATE TABLE IF NOT EXISTS kb_terms_acceptance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contract_id INT NOT NULL,
    terms_version VARCHAR(50),
    accepted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(50),
    FOREIGN KEY (contract_id) REFERENCES kb_contracts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Access tokens for direct links
CREATE TABLE IF NOT EXISTS kb_access_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    token VARCHAR(255) UNIQUE NOT NULL,
    contract_id INT NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    used BOOLEAN DEFAULT FALSE,
    used_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (contract_id) REFERENCES kb_contracts(id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Terms and conditions versions
CREATE TABLE IF NOT EXISTS kb_terms_conditions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    version VARCHAR(50) NOT NULL,
    content LONGTEXT NOT NULL,
    active BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Activity log
CREATE TABLE IF NOT EXISTS kb_activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contract_id INT,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    details JSON,
    ip_address VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (contract_id) REFERENCES kb_contracts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES kb_users(id) ON DELETE SET NULL,
    INDEX idx_contract_id (contract_id),
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Email templates
CREATE TABLE IF NOT EXISTS kb_email_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_key VARCHAR(100) UNIQUE NOT NULL,
    subject VARCHAR(255) NOT NULL,
    body_html LONGTEXT NOT NULL,
    body_text TEXT,
    variables JSON,
    active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert admin user (UPDATE PASSWORD HASH AFTER IMPORT!)
INSERT INTO kb_users (email, password_hash, role, company_name) 
VALUES ('info@kitchen-bathroom.co.uk', '$2y$10$CHANGE_THIS_HASH_AFTER_IMPORT', 'admin', 'Kitchen and Bathroom (NE) Ltd');

-- Insert default email templates
INSERT INTO kb_email_templates (template_key, subject, body_html, body_text, variables) VALUES 
('contract_ready', 'Your Contract is Ready for Signature', '<h2>Hello {{client_name}},</h2><p>Your {{project_type}} contract is ready for review and signature.</p><p><a href="{{contract_link}}">Review & Sign Contract</a></p>', 'Hello {{client_name}}, Your {{project_type}} contract is ready. Click here to review and sign: {{contract_link}}', '["client_name","project_type","contract_link"]'),
('contract_signed', 'Contract Signed Successfully', '<h2>Thank you, {{client_name}}!</h2><p>Your contract has been signed successfully.</p><p>Contract Details:<br>Quote Number: {{quote_number}}<br>Project: {{project_type}}<br>Total: £{{total_amount}}</p>', 'Thank you {{client_name}}! Your contract has been signed.', '["client_name","quote_number","project_type","total_amount"]');

-- IMPORTANT: After importing this file, you must:
-- 1. Generate a proper password hash for the admin user
-- 2. Update the admin password using: UPDATE kb_users SET password_hash = 'YOUR_HASH' WHERE email = 'info@kitchen-bathroom.co.uk';