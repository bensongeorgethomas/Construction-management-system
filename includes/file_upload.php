<?php
/**
 * File Upload Validation Functions
 * Provides secure file upload validation and handling
 */

/**
 * Validate uploaded file
 * @param array $file The $_FILES array element
 * @param int $maxSize Maximum file size in bytes (default: 5MB)
 * @return array ['success' => bool, 'message' => string, 'sanitized_name' => string]
 */
function validateFileUpload($file, $maxSize = 5242880) {
    // Allowed extensions and MIME types
    $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];
    $allowed_mime = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'application/pdf'
    ];
    
    // Check if file was uploaded
    if (!isset($file['error']) || is_array($file['error'])) {
        return ['success' => false, 'message' => 'Invalid file upload'];
    }
    
    // Check for upload errors
    switch ($file['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return ['success' => false, 'message' => 'File exceeds maximum size'];
        case UPLOAD_ERR_NO_FILE:
            return ['success' => false, 'message' => 'No file uploaded'];
        default:
            return ['success' => false, 'message' => 'Upload failed'];
    }
    
    // Validate file size
    if ($file['size'] > $maxSize) {
        return ['success' => false, 'message' => 'File size exceeds ' . ($maxSize / 1024 / 1024) . 'MB limit'];
    }
    
    // Validate extension
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed_ext)) {
        return ['success' => false, 'message' => 'Invalid file type. Allowed: ' . implode(', ', $allowed_ext)];
    }
    
    // Validate MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime, $allowed_mime)) {
        return ['success' => false, 'message' => 'Invalid file format'];
    }
    
    // Generate safe filename
    $sanitized_name = generateSafeFilename($file['name']);
    
    return [
        'success' => true,
        'message' => 'File validated successfully',
        'sanitized_name' => $sanitized_name,
        'extension' => $ext,
        'mime_type' => $mime,
        'size' => $file['size']
    ];
}

/**
 * Generate a safe, unique filename
 * @param string $original_name Original filename
 * @return string Safe filename with timestamp and random string
 */
function generateSafeFilename($original_name) {
    // Get extension
    $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
    
    // Remove extension and sanitize base name
    $base = pathinfo($original_name, PATHINFO_FILENAME);
    $base = preg_replace('/[^a-zA-Z0-9_-]/', '_', $base);
    $base = substr($base, 0, 50); // Limit length
    
    // Generate unique identifier
    $timestamp = time();
    $random = bin2hex(random_bytes(8));
    
    return $base . '_' . $timestamp . '_' . $random . '.' . $ext;
}

/**
 * Move uploaded file to secure location
 * @param string $tmp_name Temporary file path
 * @param string $destination Destination directory
 * @param string $filename Sanitized filename
 * @return array ['success' => bool, 'message' => string, 'path' => string]
 */
function saveUploadedFile($tmp_name, $destination, $filename) {
    // Ensure destination directory exists
    if (!is_dir($destination)) {
        if (!mkdir($destination, 0755, true)) {
            return ['success' => false, 'message' => 'Failed to create upload directory'];
        }
    }
    
    // Create .htaccess to prevent PHP execution in uploads directory
    $htaccess = $destination . '/.htaccess';
    if (!file_exists($htaccess)) {
        file_put_contents($htaccess, "php_flag engine off\nOptions -Indexes");
    }
    
    $filepath = $destination . '/' . $filename;
    
    // Move file
    if (move_uploaded_file($tmp_name, $filepath)) {
        // Set appropriate permissions
        chmod($filepath, 0644);
        
        return [
            'success' => true,
            'message' => 'File uploaded successfully',
            'path' => $filepath,
            'filename' => $filename
        ];
    }
    
    return ['success' => false, 'message' => 'Failed to save file'];
}
?>
