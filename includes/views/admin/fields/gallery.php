<?php
/**
 * Gallery Field Template
 * Advanced gallery management with AI image generation and optimization
 * 
 * @package MFW
 * @subpackage Views
 * @since 1.0.0
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

$value = is_array($value) ? $value : [];
$max_images = $field['max_images'] ?? 0;
$min_images = $field['min_images'] ?? 0;
$allowed_types = $field['allowed_types'] ?? ['image/jpeg', 'image/png', 'image/webp'];
$layout = $field['layout'] ?? 'grid';
?>
<div class="mfw-gallery-field {{ $field['class'] ?? '' }}"
    data-max-images="{{ $max_images }}"
    data-min-images="{{ $min_images }}"
    data-allowed-types="{{ implode(',', $allowed_types) }}"
    data-layout="{{ $layout }}"
>
    <!-- Gallery Header -->
    <div class="gallery-header">
        <div class="gallery-info">
            <h3>{{ $field['title'] ?? 'Gallery' }}</h3>
            <span class="image-count">
                {{ count($value) }} {{ $max_images ? 'of ' . $max_images : '' }} images
            </span>
        </div>

        <!-- Gallery Actions -->
        <div class="gallery-actions">
            @if(!empty($field['enable_ai']))
                <div class="ai-image-generator">
                    <button type="button" class="generate-images" title="Generate Images with AI">
                        <span class="dashicons dashicons-admin-customizer"></span>
                        AI Generate
                    </button>
                    <div class="ai-options" style="display: none;">
                        <div class="prompt-input">
                            <textarea 
                                class="ai-prompt"
                                placeholder="Describe the images you want to generate..."
                            ></textarea>
                        </div>
                        <div class="generation-options">
                            <div class="option-group">
                                <label>Style</label>
                                <select class="ai-style">
                                    <option value="realistic">Realistic</option>
                                    <option value="artistic">Artistic</option>
                                    <option value="cartoon">Cartoon</option>
                                    <option value="sketch">Sketch</option>
                                </select>
                            </div>
                            <div class="option-group">
                                <label>Size</label>
                                <select class="ai-size">
                                    <option value="1024x1024">Square (1024x1024)</option>
                                    <option value="1024x768">Landscape (1024x768)</option>
                                    <option value="768x1024">Portrait (768x1024)</option>
                                    <option value="custom">Custom Size</option>
                                </select>
                            </div>
                            <div class="option-group">
                                <label>Number of Images</label>
                                <input type="number"
                                    class="ai-count"
                                    value="1"
                                    min="1"
                                    max="{{ $max_images ? min(4, $max_images) : 4 }}"
                                >
                            </div>
                        </div>
                        <button type="button" class="button button-primary start-generation">
                            Generate
                        </button>
                    </div>
                </div>
            @endif

            <!-- Upload/Import Actions -->
            <div class="upload-actions">
                <button type="button" class="upload-images" title="Upload Images">
                    <span class="dashicons dashicons-upload"></span>
                    Upload
                </button>
                @if(!empty($field['enable_import']))
                    <button type="button" class="import-images" title="Import Images">
                        <span class="dashicons dashicons-download"></span>
                        Import
                    </button>
                @endif
            </div>

            <!-- View Options -->
            <div class="view-options">
                <button type="button" class="view-grid {{ $layout === 'grid' ? 'active' : '' }}"
                    title="Grid View"
                >
                    <span class="dashicons dashicons-grid-view"></span>
                </button>
                <button type="button" class="view-list {{ $layout === 'list' ? 'active' : '' }}"
                    title="List View"
                >
                    <span class="dashicons dashicons-list-view"></span>
                </button>
            </div>
        </div>
    </div>

    <!-- Gallery Container -->
    <div class="gallery-container {{ $layout }}-view">
        <div class="images-grid" data-sortable="true">
            @foreach($value as $image_id)
                <?php
                $image_url = wp_get_attachment_image_url($image_id, 'thumbnail');
                $image_data = wp_get_attachment_metadata($image_id);
                $image_alt = get_post_meta($image_id, '_wp_attachment_image_alt', true);
                ?>
                <div class="gallery-item" data-id="{{ $image_id }}">
                    <div class="item-preview">
                        <img src="{{ $image_url }}" alt="{{ $image_alt }}">
                        <div class="item-actions">
                            <button type="button" class="edit-image" title="Edit Image">
                                <span class="dashicons dashicons-edit"></span>
                            </button>
                            <button type="button" class="remove-image" title="Remove Image">
                                <span class="dashicons dashicons-trash"></span>
                            </button>
                        </div>
                        @if(!empty($field['show_meta']))
                            <div class="image-meta">
                                <span class="dimensions">
                                    {{ $image_data['width'] }}x{{ $image_data['height'] }}
                                </span>
                                <span class="size">
                                    {{ size_format($image_data['filesize'] ?? 0) }}
                                </span>
                            </div>
                        @endif
                    </div>
                    @if($layout === 'list')
                        <div class="item-details">
                            <input type="text"
                                class="image-title"
                                value="{{ get_the_title($image_id) }}"
                                placeholder="Image title"
                            >
                            <input type="text"
                                class="image-alt"
                                value="{{ $image_alt }}"
                                placeholder="Alt text"
                            >
                            @if(!empty($field['enable_captions']))
                                <textarea
                                    class="image-caption"
                                    placeholder="Image caption"
                                >{{ wp_get_attachment_caption($image_id) }}</textarea>
                            @endif
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    <!-- AI Generation Preview -->
    <div class="ai-preview" style="display: none;">
        <div class="preview-header">
            <h4>Generated Images</h4>
            <button type="button" class="close-preview">
                <span class="dashicons dashicons-no"></span>
            </button>
        </div>
        <div class="preview-grid"></div>
        <div class="preview-actions">
            <button type="button" class="button regenerate">
                Regenerate
            </button>
            <button type="button" class="button button-primary add-selected">
                Add Selected Images
            </button>
        </div>
    </div>

    <!-- Image Editor Modal -->
    <div class="image-editor-modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Image</h3>
                <button type="button" class="close-modal">
                    <span class="dashicons dashicons-no-alt"></span>
                </button>
            </div>
            <div class="modal-body">
                <div class="editor-container">
                    <div class="image-preview"></div>
                    <div class="editor-tools">
                        <div class="tool-group">
                            <button type="button" class="crop-tool">
                                <span class="dashicons dashicons-image-crop"></span>
                                Crop
                            </button>
                            <button type="button" class="rotate-tool">
                                <span class="dashicons dashicons-image-rotate"></span>
                                Rotate
                            </button>
                        </div>
                        @if(!empty($field['enable_filters']))
                            <div class="filter-group">
                                <select class="image-filter">
                                    <option value="">No Filter</option>
                                    <option value="grayscale">Grayscale</option>
                                    <option value="sepia">Sepia</option>
                                    <option value="blur">Blur</option>
                                </select>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="button cancel-edit">Cancel</button>
                <button type="button" class="button button-primary save-image">
                    Save Changes
                </button>
            </div>
        </div>
    </div>

    <!-- Hidden Input -->
    <input type="hidden"
        name="{{ $field['name'] }}"
        value="{{ implode(',', $value) }}"
        class="gallery-value"
    >
</div>