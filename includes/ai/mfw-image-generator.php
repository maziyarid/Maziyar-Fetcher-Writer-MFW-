<?php
/**
 * ImageGenerator - AI Image Creation and Processing System
 * Handles AI-powered image generation, enhancement, and optimization
 * 
 * @package MFW
 * @subpackage AI
 * @since 1.0.0
 */

namespace MFW\AI;

class ImageGenerator {
    private $ai_service;
    private $image_processor;
    private $cache;
    private $settings;

    /**
     * Initialize the image generation system
     */
    public function __construct() {
        $this->ai_service = new AIService();
        $this->image_processor = new ImageProcessor();
        $this->cache = new AICache();
        $this->settings = get_option('mfw_image_settings', []);
    }

    /**
     * Generate featured image for content
     */
    public function create_featured_image($args = []) {
        $default_args = [
            'style' => 'natural',
            'size' => '1024x1024',
            'format' => 'jpg',
            'quality' => 'high'
        ];

        $args = wp_parse_args($args, $default_args);
        
        try {
            $prompt = $this->build_image_prompt($args);
            $image = $this->ai_service->generate_image($prompt, [
                'size' => $args['size'],
                'quality' => $args['quality'],
                'style' => $args['style']
            ]);

            return $this->process_generated_image($image, $args);
        } catch (\Exception $e) {
            $this->log_error('Featured image generation failed', $e);
            return false;
        }
    }

    /**
     * Generate multiple product images
     */
    public function create_product_images($product_data, $count = 4) {
        $images = [];
        $angles = ['front', 'side', 'back', 'detail'];

        try {
            for ($i = 0; $i < $count; $i++) {
                $prompt = $this->build_product_image_prompt($product_data, $angles[$i] ?? 'variant');
                $image = $this->ai_service->generate_image($prompt, [
                    'size' => '1024x1024',
                    'quality' => 'high',
                    'style' => 'product'
                ]);
                
                $images[] = $this->process_generated_image($image, [
                    'type' => 'product',
                    'angle' => $angles[$i] ?? 'variant'
                ]);
            }

            return $images;
        } catch (\Exception $e) {
            $this->log_error('Product images generation failed', $e);
            return false;
        }
    }

    /**
     * Generate gallery images based on content
     */
    public function create_gallery_images($content_data) {
        $gallery = [];
        $image_suggestions = $this->analyze_content_for_images($content_data);

        try {
            foreach ($image_suggestions as $suggestion) {
                $image = $this->ai_service->generate_image($suggestion['prompt'], [
                    'size' => $suggestion['size'],
                    'style' => $suggestion['style'],
                    'quality' => 'high'
                ]);

                $gallery[] = $this->process_generated_image($image, [
                    'type' => 'gallery',
                    'caption' => $suggestion['caption']
                ]);
            }

            return $gallery;
        } catch (\Exception $e) {
            $this->log_error('Gallery images generation failed', $e);
            return false;
        }
    }

    /**
     * Generate variations of an image
     */
    public function create_image_variations($image, $count = 3) {
        try {
            $variations = [];
            $base_params = $this->extract_image_params($image);

            for ($i = 0; $i < $count; $i++) {
                $params = $this->modify_variation_params($base_params, $i);
                $variation = $this->ai_service->generate_image($params['prompt'], $params);
                $variations[] = $this->process_generated_image($variation, [
                    'type' => 'variation',
                    'variation_number' => $i + 1
                ]);
            }

            return $variations;
        } catch (\Exception $e) {
            $this->log_error('Image variation generation failed', $e);
            return false;
        }
    }

    /**
     * Enhance and optimize existing image
     */
    public function enhance_image($image_data, $enhancements = []) {
        $default_enhancements = [
            'quality' => true,
            'lighting' => true,
            'color' => true,
            'sharpness' => true
        ];

        $enhancements = wp_parse_args($enhancements, $default_enhancements);

        try {
            $enhanced = $this->ai_service->enhance_image($image_data, $enhancements);
            return $this->image_processor->optimize($enhanced);
        } catch (\Exception $e) {
            $this->log_error('Image enhancement failed', $e);
            return false;
        }
    }

    /**
     * Build optimized image prompt
     */
    private function build_image_prompt($args) {
        $prompt_parts = [
            'subject' => $args['subject'] ?? '',
            'style' => $args['style'] ?? 'natural',
            'mood' => $args['mood'] ?? 'neutral',
            'lighting' => $args['lighting'] ?? 'natural',
            'composition' => $args['composition'] ?? 'centered'
        ];

        $prompt = array_filter($prompt_parts);
        return implode(', ', $prompt);
    }

    /**
     * Build product-specific image prompt
     */
    private function build_product_image_prompt($product_data, $angle) {
        $prompt_parts = [
            'product' => $product_data['name'],
            'description' => $product_data['description'],
            'angle' => $angle,
            'setting' => 'professional studio lighting',
            'background' => 'clean white background',
            'style' => 'professional product photography'
        ];

        return implode(', ', $prompt_parts);
    }

    /**
     * Analyze content to suggest appropriate images
     */
    private function analyze_content_for_images($content_data) {
        $suggestions = [];
        $sections = $this->parse_content_sections($content_data);

        foreach ($sections as $section) {
            if ($this->section_needs_image($section)) {
                $suggestions[] = [
                    'prompt' => $this->generate_section_image_prompt($section),
                    'size' => $this->determine_optimal_image_size($section),
                    'style' => $this->determine_image_style($section),
                    'caption' => $this->generate_image_caption($section)
                ];
            }
        }

        return $suggestions;
    }

    /**
     * Process and optimize generated image
     */
    private function process_generated_image($image, $args) {
        try {
            // Download image if URL provided
            if (is_string($image) && filter_var($image, FILTER_VALIDATE_URL)) {
                $image = $this->download_image($image);
            }

            // Process image
            $processed = $this->image_processor->process($image, [
                'format' => $args['format'] ?? 'jpg',
                'quality' => $args['quality'] ?? 85,
                'resize' => $this->calculate_optimal_dimensions($args),
                'metadata' => $this->prepare_image_metadata($args)
            ]);

            // Generate responsive versions
            if (!empty($this->settings['generate_responsive'])) {
                $processed['responsive'] = $this->generate_responsive_images($processed['path']);
            }

            return $processed;
        } catch (\Exception $e) {
            $this->log_error('Image processing failed', $e);
            return false;
        }
    }

    /**
     * Log errors for monitoring
     */
    private function log_error($message, $exception) {
        error_log(sprintf(
            '[MFW ImageGenerator Error] %s: %s',
            $message,
            $exception->getMessage()
        ));
    }
}