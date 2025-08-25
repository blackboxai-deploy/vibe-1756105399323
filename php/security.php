<?php
/**
 * Security Functions
 * ACCESS (Automated Community and Citizen E-Records Service System)
 * PWD Affair Office - LGU Malasiqui
 */

/**
 * Sanitize input data
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    
    return $data;
}

/**
 * Validate email format
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate phone number (Philippine format)
 */
function isValidPhoneNumber($phone) {
    // Remove spaces and special characters
    $phone = preg_replace('/[^\d]/', '', $phone);
    
    // Check if it matches Philippine phone number patterns
    $patterns = [
        '/^(\+63|63|0)?[89]\d{9}$/',  // Mobile numbers
        '/^(\+63|63|0)?[2-8]\d{7}$/', // Landline numbers
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $phone)) {
            return true;
        }
    }
    
    return false;
}

/**
 * Generate secure random token
 */
function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * Generate reference number
 */
function generateReferenceNumber($prefix = 'REF') {
    return $prefix . '-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
}

/**
 * Validate password strength
 */
function validatePassword($password) {
    $errors = [];
    
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long';
    }
    
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Password must contain at least one uppercase letter';
    }
    
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'Password must contain at least one lowercase letter';
    }
    
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password must contain at least one number';
    }
    
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = 'Password must contain at least one special character';
    }
    
    return $errors;
}

/**
 * Check for SQL injection patterns
 */
function detectSQLInjection($input) {
    $sqlPatterns = [
        '/(\b(SELECT|INSERT|UPDATE|DELETE|DROP|CREATE|ALTER|EXEC|UNION|SCRIPT)\b)/i',
        '/(\b(OR|AND)\b.*=.*)/i',
        '/(\b(OR|AND)\b.*(\d+|\'\d+\'|\"\d+\")\s*=\s*(\d+|\'\d+\'|\"\d+\"))/i',
        '/(\'|\")(\s)*(OR|AND)(\s)*(\d+|\'\d+\'|\"\d+\")\s*=\s*(\d+|\'\d+\'|\"\d+\")/i'
    ];
    
    foreach ($sqlPatterns as $pattern) {
        if (preg_match($pattern, $input)) {
            return true;
        }
    }
    
    return false;
}

/**
 * Check for XSS patterns
 */
function detectXSS($input) {
    $xssPatterns = [
        '/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi',
        '/<iframe\b[^<]*(?:(?!<\/iframe>)<[^<]*)*<\/iframe>/mi',
        '/javascript:/i',
        '/on\w+\s*=/i',
        '/<\s*\w*\s*[^>]*\s*on\w+\s*=/i'
    ];
    
    foreach ($xssPatterns as $pattern) {
        if (preg_match($pattern, $input)) {
            return true;
        }
    }
    
    return false;
}

/**
 * Log security events
 */
function logSecurityEvent($event_type, $details, $user_id = null) {
    try {
        $sql = "INSERT INTO activity_logs (user_id, action, table_name, record_id, new_values, ip_address, user_agent) 
                VALUES (?, ?, 'security_events', NULL, ?, ?, ?)";
        
        executeQuery($sql, [
            $user_id,
            $event_type,
            json_encode($details),
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
    } catch (Exception $e) {
        error_log("Security Log Error: " . $e->getMessage());
    }
}

/**
 * Rate limiting for login attempts
 */
function checkRateLimit($identifier, $max_attempts = 5, $time_window = 300) {
    try {
        // Clean old attempts
        $cleanSql = "DELETE FROM activity_logs WHERE action = 'failed_login' AND created_at < DATE_SUB(NOW(), INTERVAL ? SECOND)";
        executeQuery($cleanSql, [$time_window]);
        
        // Count recent attempts
        $countSql = "SELECT COUNT(*) FROM activity_logs 
                     WHERE action = 'failed_login' 
                     AND ip_address = ? 
                     AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)";
        
        $attempts = fetchCount($countSql, [$identifier, $time_window]);
        
        return $attempts < $max_attempts;
    } catch (Exception $e) {
        error_log("Rate Limit Check Error: " . $e->getMessage());
        return true; // Allow on error
    }
}

/**
 * Validate file upload
 */
function validateFileUpload($file, $allowedTypes = [], $maxSize = 524288000) {
    $errors = [];
    
    // Check if file was uploaded
    if ($file['error'] !== UPLOAD_ERR_OK) {
        switch ($file['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $errors[] = 'File size exceeds the maximum allowed size';
                break;
            case UPLOAD_ERR_PARTIAL:
                $errors[] = 'File was only partially uploaded';
                break;
            case UPLOAD_ERR_NO_FILE:
                $errors[] = 'No file was uploaded';
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                $errors[] = 'Missing temporary folder';
                break;
            case UPLOAD_ERR_CANT_WRITE:
                $errors[] = 'Failed to write file to disk';
                break;
            case UPLOAD_ERR_EXTENSION:
                $errors[] = 'File upload stopped by extension';
                break;
            default:
                $errors[] = 'Unknown upload error';
                break;
        }
        return $errors;
    }
    
    // Check file size
    if ($file['size'] > $maxSize) {
        $errors[] = 'File size exceeds the maximum allowed size of ' . formatBytes($maxSize);
    }
    
    // Check file type
    if (!empty($allowedTypes)) {
        $fileInfo = pathinfo($file['name']);
        $extension = strtolower($fileInfo['extension'] ?? '');
        
        if (!in_array($extension, $allowedTypes)) {
            $errors[] = 'File type not allowed. Allowed types: ' . implode(', ', $allowedTypes);
        }
        
        // Additional MIME type check
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        $allowedMimeTypes = [
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
            'pdf' => ['application/pdf'],
            'doc' => ['application/msword'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document']
        ];
        
        if (isset($allowedMimeTypes[$extension]) && !in_array($mimeType, $allowedMimeTypes[$extension])) {
            $errors[] = 'File type does not match the extension';
        }
    }
    
    // Check for malicious content
    $content = file_get_contents($file['tmp_name'], false, null, 0, 1024); // Read first 1KB
    if (detectXSS($content) || detectSQLInjection($content)) {
        $errors[] = 'File contains potentially malicious content';
    }
    
    return $errors;
}

/**
 * Generate secure filename
 */
function generateSecureFilename($originalName, $userId) {
    $fileInfo = pathinfo($originalName);
    $extension = strtolower($fileInfo['extension'] ?? '');
    $timestamp = time();
    $random = bin2hex(random_bytes(8));
    
    return "user_{$userId}_{$timestamp}_{$random}.{$extension}";
}

/**
 * Format bytes to human readable format
 */
function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

/**
 * Log user activity
 */
function logActivity($userId, $action, $tableName = null, $recordId = null, $oldValues = null, $newValues = null) {
    try {
        $sql = "INSERT INTO activity_logs (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        executeQuery($sql, [
            $userId,
            $action,
            $tableName,
            $recordId,
            $oldValues ? json_encode($oldValues) : null,
            $newValues ? json_encode($newValues) : null,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
    } catch (Exception $e) {
        error_log("Activity Log Error: " . $e->getMessage());
    }
}

/**
 * Create notification
 */
function createNotification($userId, $type, $title, $message, $relatedId = null, $relatedType = null, $priority = 'medium') {
    try {
        $sql = "INSERT INTO notifications (user_id, type, title, message, related_id, related_type, priority) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        executeQuery($sql, [$userId, $type, $title, $message, $relatedId, $relatedType, $priority]);
    } catch (Exception $e) {
        error_log("Notification Error: " . $e->getMessage());
    }
}

/**
 * Upload document
 */
function uploadDocument($userId, $citizenId, $docTypeId, $file) {
    try {
        // Validate file
        $allowedTypes = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'];
        $errors = validateFileUpload($file, $allowedTypes);
        
        if (!empty($errors)) {
            throw new Exception(implode(', ', $errors));
        }
        
        // Create upload directory
        $uploadDir = '../uploads/' . date('Y/m/d') . '/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Generate secure filename
        $filename = generateSecureFilename($file['name'], $userId);
        $filepath = $uploadDir . $filename;
        
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            throw new Exception('Failed to save uploaded file');
        }
        
        // Insert document record
        $sql = "INSERT INTO documents (user_id, citizen_id, doc_type_id, original_filename, stored_filename, 
                file_path, file_size, file_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $documentId = insertRecord($sql, [
            $userId,
            $citizenId,
            $docTypeId,
            $file['name'],
            $filename,
            $filepath,
            $file['size'],
            $file['type']
        ]);
        
        // Log activity
        logActivity($userId, 'upload_document', 'documents', $documentId);
        
        return [
            'success' => true,
            'document_id' => $documentId,
            'filename' => $filename
        ];
        
    } catch (Exception $e) {
        error_log("Document Upload Error: " . $e->getMessage());
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}
?>