-- ============================================================================
-- ACCESS (Automated Community and Citizen E-Records Service System) Database
-- For PWD Affair Office at LGU Malasiqui
-- ============================================================================

CREATE DATABASE IF NOT EXISTS access_pwd_system;
USE access_pwd_system;

-- ============================================================================
-- USER ROLES AND AUTHENTICATION TABLES
-- ============================================================================

-- User roles definition
CREATE TABLE user_roles (
    role_id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(50) NOT NULL UNIQUE,
    role_description TEXT,
    permissions JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Main users table
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    email VARCHAR(150) UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role_id INT NOT NULL,
    status ENUM('active', 'inactive', 'pending', 'rejected') DEFAULT 'pending',
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    contact_number VARCHAR(20),
    address TEXT,
    sector VARCHAR(50), -- For sub-admin users
    organization_type ENUM('government', 'private'), -- For sub-admin
    contact_person VARCHAR(150), -- For sub-admin
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    last_logout TIMESTAMP NULL,
    FOREIGN KEY (role_id) REFERENCES user_roles(role_id)
);

-- ============================================================================
-- PWD CITIZEN RECORDS
-- ============================================================================

-- Main PWD citizen records
CREATE TABLE citizen_records (
    citizen_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    pwd_id_number VARCHAR(50) UNIQUE,
    first_name VARCHAR(100) NOT NULL,
    middle_name VARCHAR(100),
    last_name VARCHAR(100) NOT NULL,
    suffix VARCHAR(10),
    date_of_birth DATE,
    gender ENUM('male', 'female', 'other'),
    civil_status ENUM('single', 'married', 'widowed', 'separated', 'divorced'),
    barangay VARCHAR(100),
    municipality VARCHAR(100) DEFAULT 'Malasiqui',
    province VARCHAR(100) DEFAULT 'Pangasinan',
    contact_number VARCHAR(20),
    email VARCHAR(150),
    emergency_contact_name VARCHAR(150),
    emergency_contact_number VARCHAR(20),
    disability_type VARCHAR(100),
    disability_cause VARCHAR(100),
    disability_since DATE,
    verification_status ENUM('pending', 'verified', 'rejected', 'incomplete') DEFAULT 'pending',
    pwd_id_status ENUM('none', 'applied', 'approved', 'issued', 'renewal_due', 'expired') DEFAULT 'none',
    pwd_id_issued_date DATE,
    pwd_id_expiry_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
);

-- ============================================================================
-- DOCUMENT MANAGEMENT
-- ============================================================================

-- Document types
CREATE TABLE document_types (
    doc_type_id INT AUTO_INCREMENT PRIMARY KEY,
    type_name VARCHAR(100) NOT NULL,
    description TEXT,
    required_for VARCHAR(100), -- 'client_registration', 'sub_admin_registration', 'service_application'
    max_file_size INT DEFAULT 524288000, -- 500MB in bytes
    allowed_formats JSON, -- ['jpg', 'png', 'pdf', 'doc']
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Uploaded documents
CREATE TABLE documents (
    document_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    citizen_id INT,
    doc_type_id INT,
    original_filename VARCHAR(255),
    stored_filename VARCHAR(255),
    file_path VARCHAR(500),
    file_size INT,
    file_type VARCHAR(50),
    upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    verification_status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
    verification_notes TEXT,
    verified_by INT,
    verified_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (citizen_id) REFERENCES citizen_records(citizen_id) ON DELETE CASCADE,
    FOREIGN KEY (doc_type_id) REFERENCES document_types(doc_type_id),
    FOREIGN KEY (verified_by) REFERENCES users(user_id)
);

-- ============================================================================
-- SERVICES AND APPLICATIONS
-- ============================================================================

-- Sectors for sub-admin management
CREATE TABLE sectors (
    sector_id INT AUTO_INCREMENT PRIMARY KEY,
    sector_name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    head_office VARCHAR(200),
    contact_info JSON,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Available services per sector
CREATE TABLE services (
    service_id INT AUTO_INCREMENT PRIMARY KEY,
    sector_id INT NOT NULL,
    service_name VARCHAR(150) NOT NULL,
    service_type VARCHAR(100),
    description TEXT,
    eligibility_criteria JSON,
    required_documents JSON,
    capacity INT,
    slots_available INT,
    application_start_date DATE,
    application_end_date DATE,
    processing_time_days INT,
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (sector_id) REFERENCES sectors(sector_id),
    FOREIGN KEY (created_by) REFERENCES users(user_id)
);

-- Service applications
CREATE TABLE applications (
    application_id INT AUTO_INCREMENT PRIMARY KEY,
    reference_number VARCHAR(50) UNIQUE NOT NULL,
    citizen_id INT NOT NULL,
    service_id INT NOT NULL,
    application_type VARCHAR(100), -- 'new', 'renewal', 'replacement'
    application_data JSON, -- Store form data
    status ENUM('submitted', 'in_review', 'approved', 'rejected', 'returned', 'completed') DEFAULT 'submitted',
    priority ENUM('normal', 'urgent', 'emergency') DEFAULT 'normal',
    assigned_to INT,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    sla_due_date DATE,
    reviewer_notes TEXT,
    rejection_reason TEXT,
    FOREIGN KEY (citizen_id) REFERENCES citizen_records(citizen_id),
    FOREIGN KEY (service_id) REFERENCES services(service_id),
    FOREIGN KEY (assigned_to) REFERENCES users(user_id)
);

-- ============================================================================
-- ID AND BOOKLET MANAGEMENT
-- ============================================================================

-- PWD ID and booklet requests
CREATE TABLE id_booklet_requests (
    request_id INT AUTO_INCREMENT PRIMARY KEY,
    reference_number VARCHAR(50) UNIQUE NOT NULL,
    citizen_id INT NOT NULL,
    request_type ENUM('pwd_id', 'discount_booklet_medical', 'discount_booklet_transport', 'discount_booklet_food') NOT NULL,
    application_type ENUM('new', 'renewal', 'replacement') NOT NULL,
    status ENUM('applied', 'processing', 'approved', 'printed', 'released', 'expired') DEFAULT 'applied',
    issued_date DATE,
    expiry_date DATE,
    pickup_location VARCHAR(200),
    tracking_number VARCHAR(100),
    issued_by INT,
    released_to VARCHAR(150),
    release_date DATE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (citizen_id) REFERENCES citizen_records(citizen_id),
    FOREIGN KEY (issued_by) REFERENCES users(user_id)
);

-- ============================================================================
-- ASSISTANCE AND COMPLAINTS
-- ============================================================================

-- Assistance requests
CREATE TABLE assistance_requests (
    assistance_id INT AUTO_INCREMENT PRIMARY KEY,
    reference_number VARCHAR(50) UNIQUE NOT NULL,
    citizen_id INT NOT NULL,
    assistance_type ENUM('financial', 'medical', 'mobility_device', 'food', 'transportation', 'other') NOT NULL,
    description TEXT NOT NULL,
    amount_requested DECIMAL(10,2),
    justification TEXT,
    status ENUM('submitted', 'under_review', 'approved', 'disbursed', 'rejected', 'cancelled') DEFAULT 'submitted',
    approved_amount DECIMAL(10,2),
    disbursement_date DATE,
    disbursed_by INT,
    reviewed_by INT,
    review_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (citizen_id) REFERENCES citizen_records(citizen_id),
    FOREIGN KEY (disbursed_by) REFERENCES users(user_id),
    FOREIGN KEY (reviewed_by) REFERENCES users(user_id)
);

-- Complaints and feedback
CREATE TABLE complaints (
    complaint_id INT AUTO_INCREMENT PRIMARY KEY,
    reference_number VARCHAR(50) UNIQUE NOT NULL,
    citizen_id INT,
    complaint_type ENUM('service_delay', 'accessibility_issues', 'staff_concerns', 'system_issues', 'other') NOT NULL,
    subject VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    status ENUM('new', 'in_progress', 'resolved', 'closed', 'escalated') DEFAULT 'new',
    priority ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    assigned_to INT,
    resolution TEXT,
    resolved_by INT,
    resolved_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (citizen_id) REFERENCES citizen_records(citizen_id),
    FOREIGN KEY (assigned_to) REFERENCES users(user_id),
    FOREIGN KEY (resolved_by) REFERENCES users(user_id)
);

-- ============================================================================
-- NOTIFICATIONS AND COMMUNICATIONS
-- ============================================================================

-- System notifications
CREATE TABLE notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type VARCHAR(100) NOT NULL, -- 'application_status', 'id_renewal', 'appointment', 'system_announcement'
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    related_id INT, -- ID of related record (application_id, citizen_id, etc.)
    related_type VARCHAR(50), -- Type of related record
    is_read BOOLEAN DEFAULT FALSE,
    priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- ============================================================================
-- APPOINTMENTS AND SCHEDULES
-- ============================================================================

-- Appointments
CREATE TABLE appointments (
    appointment_id INT AUTO_INCREMENT PRIMARY KEY,
    citizen_id INT NOT NULL,
    service_id INT,
    appointment_type VARCHAR(100), -- 'consultation', 'document_submission', 'id_release', 'interview'
    appointment_date DATE NOT NULL,
    appointment_time TIME NOT NULL,
    location VARCHAR(200),
    status ENUM('scheduled', 'confirmed', 'completed', 'cancelled', 'no_show') DEFAULT 'scheduled',
    notes TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (citizen_id) REFERENCES citizen_records(citizen_id),
    FOREIGN KEY (service_id) REFERENCES services(service_id),
    FOREIGN KEY (created_by) REFERENCES users(user_id)
);

-- ============================================================================
-- ACTIVITY LOGS AND AUDIT TRAIL
-- ============================================================================

-- Activity logs for audit trail
CREATE TABLE activity_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action VARCHAR(100) NOT NULL, -- 'login', 'logout', 'create', 'update', 'delete', 'approve', 'reject'
    table_name VARCHAR(100),
    record_id INT,
    old_values JSON,
    new_values JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

-- ============================================================================
-- SYSTEM CONFIGURATION
-- ============================================================================

-- System settings
CREATE TABLE system_settings (
    setting_id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type VARCHAR(50), -- 'string', 'number', 'boolean', 'json'
    description TEXT,
    updated_by INT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES users(user_id)
);

-- ============================================================================
-- INSERT DEFAULT DATA
-- ============================================================================

-- Insert user roles
INSERT INTO user_roles (role_name, role_description, permissions) VALUES
('super_admin', 'Super Administrator with full system access', '["all"]'),
('sub_admin_education', 'Education Sector Administrator', '["education_services", "citizen_view", "reports"]'),
('sub_admin_healthcare', 'Healthcare Sector Administrator', '["healthcare_services", "citizen_view", "reports"]'),
('sub_admin_employment', 'Employment Sector Administrator', '["employment_services", "citizen_view", "reports"]'),
('sub_admin_emergency', 'Emergency Response Administrator', '["emergency_services", "citizen_view", "reports"]'),
('client', 'PWD Client with limited access to personal data', '["personal_data", "applications", "services"]');

-- Insert default Super Admin user
INSERT INTO users (username, email, password_hash, role_id, status, first_name, last_name) VALUES
('admin_pwd', 'admin@malasiqui-pwd.gov.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 'active', 'System', 'Administrator');
-- Password: PWDoffice@123

-- Insert sectors
INSERT INTO sectors (sector_name, description, head_office) VALUES
('Education', 'Educational services and support for PWDs', 'Department of Education - Malasiqui'),
('Healthcare', 'Medical services and rehabilitation programs', 'Rural Health Unit - Malasiqui'),
('Employment', 'Job placement and employment support', 'Department of Labor and Employment'),
('Emergency', 'Disaster response and emergency assistance', 'Municipal Disaster Risk Reduction Office');

-- Insert document types
INSERT INTO document_types (type_name, description, required_for, allowed_formats) VALUES
('Barangay Clearance', 'Barangay clearance certificate', 'client_registration', '["jpg", "png", "pdf"]'),
('Medical Certificate', 'Medical certificate from licensed physician', 'client_registration', '["jpg", "png", "pdf"]'),
('Disability Assessment Form', 'Official disability assessment form', 'client_registration', '["jpg", "png", "pdf", "doc"]'),
('MOA Document', 'Memorandum of Agreement', 'sub_admin_registration', '["pdf", "doc"]'),
('Accreditation Certificate', 'Official accreditation certificate', 'sub_admin_registration', '["jpg", "png", "pdf"]'),
('License Document', 'Business or professional license', 'sub_admin_registration', '["jpg", "png", "pdf"]'),
('SEC/DTI Registration', 'SEC or DTI business registration', 'sub_admin_registration', '["jpg", "png", "pdf"]);

-- Insert sample services
INSERT INTO services (sector_id, service_name, service_type, description, capacity, slots_available, processing_time_days, created_by) VALUES
(1, 'Special Education Program Enrollment', 'Education Support', 'Enrollment assistance for special education programs', 50, 45, 14, 1),
(1, 'Educational Scholarship Application', 'Financial Assistance', 'Scholarship grants for PWD students', 20, 18, 21, 1),
(2, 'Medical Consultation Assistance', 'Healthcare Service', 'Free medical consultation for PWDs', 100, 95, 7, 1),
(2, 'Rehabilitation Therapy Program', 'Rehabilitation', 'Physical and occupational therapy programs', 30, 25, 14, 1),
(3, 'Job Placement Assistance', 'Employment Support', 'Job matching and placement services', 40, 35, 30, 1),
(3, 'Skills Training Program', 'Training', 'Vocational and skills training for PWDs', 25, 20, 45, 1),
(4, 'Emergency Response Registration', 'Emergency Preparedness', 'Pre-registration for emergency assistance', 200, 180, 3, 1),
(4, 'Disaster Relief Application', 'Emergency Assistance', 'Application for disaster relief support', 100, 95, 7, 1);

-- Insert system settings
INSERT INTO system_settings (setting_key, setting_value, setting_type, description) VALUES
('pwd_id_validity_years', '5', 'number', 'PWD ID validity period in years'),
('renewal_reminder_months', '1', 'number', 'Months before expiry to send renewal reminders'),
('max_file_upload_size', '524288000', 'number', 'Maximum file upload size in bytes (500MB)'),
('system_email', 'system@malasiqui-pwd.gov.ph', 'string', 'System email address for notifications'),
('office_name', 'PWD Affair Office - LGU Malasiqui', 'string', 'Official office name'),
('office_address', 'Municipal Hall, Malasiqui, Pangasinan', 'string', 'Office address'),
('contact_number', '+63 75 632-8001', 'string', 'Office contact number');

-- ============================================================================
-- INDEXES FOR PERFORMANCE
-- ============================================================================

-- User-related indexes
CREATE INDEX idx_users_username ON users(username);
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_role_status ON users(role_id, status);
CREATE INDEX idx_users_sector ON users(sector);

-- Citizen records indexes
CREATE INDEX idx_citizen_pwd_id ON citizen_records(pwd_id_number);
CREATE INDEX idx_citizen_barangay ON citizen_records(barangay);
CREATE INDEX idx_citizen_status ON citizen_records(verification_status);
CREATE INDEX idx_citizen_pwd_status ON citizen_records(pwd_id_status);
CREATE INDEX idx_citizen_expiry ON citizen_records(pwd_id_expiry_date);

-- Application indexes
CREATE INDEX idx_applications_reference ON applications(reference_number);
CREATE INDEX idx_applications_citizen ON applications(citizen_id);
CREATE INDEX idx_applications_service ON applications(service_id);
CREATE INDEX idx_applications_status ON applications(status);
CREATE INDEX idx_applications_sla ON applications(sla_due_date);

-- Document indexes
CREATE INDEX idx_documents_user ON documents(user_id);
CREATE INDEX idx_documents_citizen ON documents(citizen_id);
CREATE INDEX idx_documents_status ON documents(verification_status);

-- Notification indexes
CREATE INDEX idx_notifications_user ON notifications(user_id);
CREATE INDEX idx_notifications_unread ON notifications(user_id, is_read);
CREATE INDEX idx_notifications_type ON notifications(type);

-- Activity logs indexes
CREATE INDEX idx_activity_user ON activity_logs(user_id);
CREATE INDEX idx_activity_date ON activity_logs(created_at);
CREATE INDEX idx_activity_action ON activity_logs(action);

-- ============================================================================
-- VIEWS FOR REPORTING
-- ============================================================================

-- Dashboard metrics view
CREATE VIEW dashboard_metrics AS
SELECT 
    (SELECT COUNT(*) FROM citizen_records WHERE verification_status = 'verified') as total_verified_pwd,
    (SELECT COUNT(*) FROM citizen_records WHERE verification_status = 'pending') as pending_verifications,
    (SELECT COUNT(*) FROM applications WHERE status IN ('submitted', 'in_review')) as active_requests,
    (SELECT COUNT(*) FROM users WHERE role_id > 1 AND status = 'active') as active_partners;

-- Recent applications view
CREATE VIEW recent_applications AS
SELECT 
    a.reference_number,
    CONCAT(c.first_name, ' ', c.last_name) as citizen_name,
    s.service_name,
    sect.sector_name,
    a.submitted_at,
    a.status,
    CONCAT(u.first_name, ' ', u.last_name) as assigned_to_name
FROM applications a
JOIN citizen_records c ON a.citizen_id = c.citizen_id
JOIN services s ON a.service_id = s.service_id
JOIN sectors sect ON s.sector_id = sect.sector_id
LEFT JOIN users u ON a.assigned_to = u.user_id
ORDER BY a.submitted_at DESC
LIMIT 20;