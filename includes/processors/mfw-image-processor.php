<?php
/**
 * Image Processor Class
 *
 * Handles image generation, optimization, and management.
 *
 * @package MFW
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_Image_Processor {
    /**
     * Current timestamp
     */
    private $current_time = '2025-05-13 17:10:43';

    /**
     * Current user
     */
    private $current_user = 'maziyarid';

    /**
     * Plugin settings
     *
     * @var array
     */
    private $settings;

    /**
     * AI service instance
     *
     * @var MFW_Gemini_Service|MFW_Deepseek_Service
     */
    private $ai_service;

    /**
     * Constructor
     */
    public function __construct() {
        $this->settings = get_option(MFW_SETTINGS_OPTION, []);
        $this->initialize_ai_service();
    }

    /**
     * Initialize AI service
     */
    private function initialize_ai_service() {
        $service_type = $this->settings['default_ai_service'] ?? MFW_AI_GEMINI;
        
        switch ($service_type) {
            case MFW_AI_DEEPSEEK:
                $this->ai_service = new MFW_Deepseek_Service();
                break;
            default:
                $this->ai_service = new MFW_Gemini_Service();
                break;
        }
    }

    /**
     * Generate image from prompt
     *
     * @param string $prompt Image generation prompt
     * @param array $options Generation options
     * @return array Generation result
     */
    public function generate_image($prompt, $options = []) {
        try {
            // Merge options with defaults
            $options = wp_parse_args($options, [
                'width' => 1024,
                'height' => 1024,
                'style' => 'natural', // natural, artistic, minimalist
                'format' => 'jpg',
                'quality' => 90
            ]);

            // Enhance prompt for better results
            $enhanced_prompt = $this->enhance_image_prompt($prompt);

            // Generate image using AI service
            $response = $this->ai_service->generate_image($enhanced_prompt, $options);

            if (empty($response['image_data'])) {
                throw new Exception(__('No image data received from AI service.', 'mfw'));
            }

            // Save image to temporary file
            $temp_file = $this->save_temp_image($response['image_data']);

            // Optimize image if enabled
            if ($this->settings['enable_image_optimization'] ?? false) {
                $this->optimize_image($temp_file);
            }

            return [
                'success' => true,
                'image_path' => $temp_file,
                'mime_type' => 'image/' . $options['format'],
                'width' => $options['width'],
                'height' => $options['height']
            ];

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Image generation failed: %s', $e->getMessage()),
                'image_processor'
            );

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Enhance image generation prompt
     *
     * @param string $prompt Original prompt
     * @return string Enhanced prompt
     */
    private function enhance_image_prompt($prompt) {
        try {
            $enhancement_prompt = sprintf(
                'Enhance the following image generation prompt to create a more detailed and visually appealing result: %s',
                $prompt
            );

            $enhanced = $this->ai_service->generate_text($enhancement_prompt);
            return !empty($enhanced) ? $enhanced : $prompt;

        } catch (Exception $e) {
            return $prompt;
        }
    }

    /**
     * Save image data to temporary file
     *
     * @param string $image_data Base64 or binary image data
     * @return string Path to temporary file
     */
    private function save_temp_image($image_data) {
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/mfw-temp';

        // Create temp directory if it doesn't exist
        if (!file_exists($temp_dir)) {
            wp_mkdir_p($temp_dir);
        }

        // Generate unique filename
        $filename = sprintf(
            'mfw-image-%s-%s.jpg',
            $this->current_user,
            uniqid()
        );

        $temp_file = $temp_dir . '/' . $filename;

        // Save image data
        if (strpos($image_data, 'base64,') !== false) {
            // Handle base64 data
            list(, $image_data) = explode('base64,', $image_data);
            $image_data = base64_decode($image_data);
        }

        if (file_put_contents($temp_file, $image_data) === false) {
            throw new Exception(__('Failed to save temporary image file.', 'mfw'));
        }

        return $temp_file;
    }

    /**
     * Optimize image
     *
     * @param string $file_path Path to image file
     */
    private function optimize_image($file_path) {
        // Skip if optimization is disabled
        if (!$this->settings['enable_image_optimization']) {
            return;
        }

        try {
            $image = wp_get_image_editor($file_path);
            
            if (is_wp_error($image)) {
                throw new Exception($image->get_error_message());
            }

            // Resize if too large
            $max_size = 2048;
            $size = $image->get_size();

            if ($size['width'] > $max_size || $size['height'] > $max_size) {
                $image->resize($max_size, $max_size, false);
            }

            // Set quality
            $quality = $this->settings['image_quality'] ?? 90;
            $image->set_quality($quality);

            // Save optimized image
            $image->save($file_path);

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Image optimization failed: %s', $e->getMessage()),
                'image_processor'
            );
        }
    }

    /**
     * Attach image to post
     *
     * @param string $image_path Path to image file
     * @param int $post_id Post ID
     * @param string $title Image title
     * @return int|false Attachment ID or false on failure
     */
    public function attach_image_to_post($image_path, $post_id, $title = '') {
        try {
            // Check if file exists
            if (!file_exists($image_path)) {
                throw new Exception(__('Image file not found.', 'mfw'));
            }

            // Prepare file for upload
            $wp_upload_dir = wp_upload_dir();
            $filename = basename($image_path);

            // Copy file to uploads directory
            $new_file = $wp_upload_dir['path'] . '/' . $filename;
            copy($image_path, $new_file);

            // Prepare attachment data
            $wp_filetype = wp_check_filetype($filename);
            $attachment = [
                'post_mime_type' => $wp_filetype['type'],
                'post_title' => $title ?: preg_replace('/\.[^.]+$/', '', $filename),
                'post_content' => '',
                'post_status' => 'inherit'
            ];

            // Insert attachment
            $attach_id = wp_insert_attachment($attachment, $new_file, $post_id);
            if (is_wp_error($attach_id)) {
                throw new Exception($attach_id->get_error_message());
            }

            // Generate attachment metadata
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            $attach_data = wp_generate_attachment_metadata($attach_id, $new_file);
            wp_update_attachment_metadata($attach_id, $attach_data);

            // Set as featured image if this is the first image
            if (!has_post_thumbnail($post_id)) {
                set_post_thumbnail($post_id, $attach_id);
            }

            // Clean up temporary file
            @unlink($image_path);

            return $attach_id;

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to attach image: %s', $e->getMessage()),
                'image_processor'
            );
            return false;
        }
    }

    /**
     * Generate alt text for image
     *
     * @param string $image_path Path to image file
     * @param string $context Context for the image
     * @return string Generated alt text
     */
    public function generate_alt_text($image_path, $context = '') {
        try {
            // Use computer vision API or AI to analyze image
            $prompt = sprintf(
                'Generate a descriptive, SEO-friendly alt text for an image in the context of: %s',
                $context
            );

            $alt_text = $this->ai_service->generate_text($prompt);
            return !empty($alt_text) ? $alt_text : '';

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Alt text generation failed: %s', $e->getMessage()),
                'image_processor'
            );
            return '';
        }
    }

    /**
     * Clean up temporary files
     */
    public function cleanup_temp_files() {
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/mfw-temp';

        if (!is_dir($temp_dir)) {
            return;
        }

        // Delete files older than 24 hours
        $files = glob($temp_dir . '/mfw-image-*');
        $now = time();

        foreach ($files as $file) {
            if ($now - filemtime($file) > 86400) {
                @unlink($file);
            }
        }
    }
}