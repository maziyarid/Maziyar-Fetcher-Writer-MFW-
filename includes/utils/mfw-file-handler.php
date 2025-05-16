<?php
/**
 * File Handler Class
 * 
 * Manages file operations including uploads, downloads, and processing.
 * Includes security checks and validation for file handling.
 *
 * @package MFW
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_File_Handler {
    /**
     * Current timestamp
     */
    private $current_time = '2025-05-13 18:27:29';

    /**
     * Current user
     */
    private $current_user = 'maziyarid';

    /**
     * Allowed file types
     */
    private $allowed_types;

    /**
     * Maximum file size (in bytes)
     */
    private $max_file_size;

    /**
     * Upload directory
     */
    private $upload_dir;

    /**
     * Initialize file handler
     */
    public function __construct() {
        // Load settings
        $this->allowed_types = get_option('mfw_allowed_file_types', [
            'jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx'
        ]);
        $this->max_file_size = get_option('mfw_max_file_size', 5 * 1024 * 1024); // 5MB default
        
        // Set upload directory
        $wp_upload_dir = wp_upload_dir();
        $this->upload_dir = $wp_upload_dir['basedir'] . '/mfw-uploads';

        // Create upload directory if it doesn't exist
        if (!file_exists($this->upload_dir)) {
            wp_mkdir_p($this->upload_dir);
        }

        // Add AJAX handlers
        add_action('wp_ajax_mfw_upload_file', [$this, 'handle_file_upload']);
        add_action('wp_ajax_mfw_delete_file', [$this, 'handle_file_delete']);
    }

    /**
     * Upload file
     *
     * @param array $file File data ($_FILES array element)
     * @param array $options Upload options
     * @return array|bool File info on success, false on failure
     */
    public function upload_file($file, $options = []) {
        try {
            // Parse options
            $options = wp_parse_args($options, [
                'subfolder' => '',
                'filename' => '',
                'allowed_types' => $this->allowed_types,
                'max_size' => $this->max_file_size
            ]);

            // Validate file
            $this->validate_file($file, $options);

            // Prepare upload directory
            $upload_path = $this->upload_dir;
            if ($options['subfolder']) {
                $upload_path .= '/' . trim($options['subfolder'], '/');
                wp_mkdir_p($upload_path);
            }

            // Generate filename
            $filename = $this->generate_filename($file, $options['filename']);
            $filepath = $upload_path . '/' . $filename;

            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $filepath)) {
                throw new Exception(__('Failed to move uploaded file', 'mfw'));
            }

            // Set correct file permissions
            chmod($filepath, 0644);

            // Generate file info
            $file_info = [
                'name' => $filename,
                'path' => str_replace($this->upload_dir, '', $filepath),
                'url' => str_replace(
                    $this->upload_dir,
                    wp_upload_dir()['baseurl'] . '/mfw-uploads',
                    $filepath
                ),
                'type' => $file['type'],
                'size' => filesize($filepath),
                'extension' => pathinfo($filename, PATHINFO_EXTENSION)
            ];

            // Log upload
            $this->log_file_operation('upload', $file_info);

            return $file_info;

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('File upload failed: %s', $e->getMessage()),
                'file_handler',
                'error'
            );
            return false;
        }
    }

    /**
     * Delete file
     *
     * @param string $filepath File path relative to upload directory
     * @return bool Success status
     */
    public function delete_file($filepath) {
        try {
            $full_path = $this->upload_dir . '/' . ltrim($filepath, '/');

            // Validate file path
            if (!$this->is_valid_file_path($full_path)) {
                throw new Exception(__('Invalid file path', 'mfw'));
            }

            // Check if file exists
            if (!file_exists($full_path)) {
                throw new Exception(__('File not found', 'mfw'));
            }

            // Delete file
            if (!unlink($full_path)) {
                throw new Exception(__('Failed to delete file', 'mfw'));
            }

            // Log deletion
            $this->log_file_operation('delete', [
                'path' => $filepath
            ]);

            return true;

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('File deletion failed: %s', $e->getMessage()),
                'file_handler',
                'error'
            );
            return false;
        }
    }

    /**
     * Handle file upload via AJAX
     */
    public function handle_file_upload() {
        try {
            // Verify nonce
            check_ajax_referer('mfw_file_upload', 'nonce');

            // Check file
            if (!isset($_FILES['file'])) {
                throw new Exception(__('No file uploaded', 'mfw'));
            }

            // Get options
            $options = isset($_POST['options']) ? (array)$_POST['options'] : [];

            // Upload file
            $file_info = $this->upload_file($_FILES['file'], $options);
            if (!$file_info) {
                throw new Exception(__('Upload failed', 'mfw'));
            }

            wp_send_json_success($file_info);

        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Handle file deletion via AJAX
     */
    public function handle_file_delete() {
        try {
            // Verify nonce
            check_ajax_referer('mfw_file_delete', 'nonce');

            // Get file path
            $filepath = sanitize_text_field($_POST['filepath'] ?? '');
            if (!$filepath) {
                throw new Exception(__('No file specified', 'mfw'));
            }

            // Delete file
            if (!$this->delete_file($filepath)) {
                throw new Exception(__('Deletion failed', 'mfw'));
            }

            wp_send_json_success(__('File deleted successfully', 'mfw'));

        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Validate file
     *
     * @param array $file File data
     * @param array $options Validation options
     * @throws Exception If validation fails
     */
    private function validate_file($file, $options) {
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception($this->get_upload_error_message($file['error']));
        }

        // Check file size
        if ($file['size'] > $options['max_size']) {
            throw new Exception(sprintf(
                __('File size exceeds maximum limit of %s', 'mfw'),
                size_format($options['max_size'])
            ));
        }

        // Check file type
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $options['allowed_types'])) {
            throw new Exception(__('File type not allowed', 'mfw'));
        }

        // Validate file content
        if (!$this->is_valid_file_content($file)) {
            throw new Exception(__('Invalid file content', 'mfw'));
        }
    }

    /**
     * Generate unique filename
     *
     * @param array $file File data
     * @param string $custom_filename Custom filename
     * @return string Generated filename
     */
    private function generate_filename($file, $custom_filename = '') {
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if ($custom_filename) {
            $filename = sanitize_file_name($custom_filename);
        } else {
            $filename = sanitize_file_name($file['name']);
            $filename = pathinfo($filename, PATHINFO_FILENAME);
        }

        $filename = $filename . '-' . uniqid() . '.' . $extension;
        return $filename;
    }

    /**
     * Check if file path is valid
     *
     * @param string $path File path
     * @return bool Validity status
     */
    private function is_valid_file_path($path) {
        // Check for directory traversal
        $real_path = realpath($path);
        if ($real_path === false) {
            return false;
        }

        // Check if path is within upload directory
        return strpos($real_path, realpath($this->upload_dir)) === 0;
    }

    /**
     * Check if file content is valid
     *
     * @param array $file File data
     * @return bool Validity status
     */
    private function is_valid_file_content($file) {
        // Check MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        // List of allowed MIME types
        $allowed_mimes = [
            'image/jpeg',
            'image/png',
            'image/gif',
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        ];

        return in_array($mime_type, $allowed_mimes);
    }

    /**
     * Get upload error message
     *
     * @param int $error_code Upload error code
     * @return string Error message
     */
    private function get_upload_error_message($error_code) {
        switch ($error_code) {
            case UPLOAD_ERR_INI_SIZE:
                return __('The uploaded file exceeds the upload_max_filesize directive in php.ini', 'mfw');
            case UPLOAD_ERR_FORM_SIZE:
                return __('The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form', 'mfw');
            case UPLOAD_ERR_PARTIAL:
                return __('The uploaded file was only partially uploaded', 'mfw');
            case UPLOAD_ERR_NO_FILE:
                return __('No file was uploaded', 'mfw');
            case UPLOAD_ERR_NO_TMP_DIR:
                return __('Missing a temporary folder', 'mfw');
            case UPLOAD_ERR_CANT_WRITE:
                return __('Failed to write file to disk', 'mfw');
            case UPLOAD_ERR_EXTENSION:
                return __('File upload stopped by extension', 'mfw');
            default:
                return __('Unknown upload error', 'mfw');
        }
    }

    /**
     * Log file operation
     *
     * @param string $operation Operation type
     * @param array $file_info File information
     */
    private function log_file_operation($operation, $file_info) {
        try {
            global $wpdb;

            $wpdb->insert(
                $wpdb->prefix . 'mfw_files_log',
                [
                    'operation' => $operation,
                    'file_info' => json_encode($file_info),
                    'created_by' => $this->current_user,
                    'created_at' => $this->current_time
                ],
                ['%s', '%s', '%s', '%s']
            );

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to log file operation: %s', $e->getMessage()),
                'file_handler',
                'error'
            );
        }
    }
}