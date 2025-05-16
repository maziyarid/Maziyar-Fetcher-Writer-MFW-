<?php
/**
 * Icon Picker Field Template
 * 
 * @package MFW
 * @subpackage Views
 * @since 1.0.0
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="mfw-icon-picker {{ $field['class'] ?? '' }}"
    data-sets="{{ json_encode($field['icon_sets'] ?? ['dashicons']) }}"
>
    <div class="icon-picker-preview">
        <div class="selected-icon">
            @if(!empty($value))
                @if(strpos($value, 'dashicons-') === 0)
                    <span class="dashicons {{ $value }}"></span>
                @elseif(strpos($value, 'fa-') === 0)
                    <i class="fa {{ $value }}"></i>
                @else
                    <img src="{{ $value }}" alt="Selected icon">
                @endif
            @else
                <span class="no-icon">{{ $field['placeholder'] ?? 'Select an icon' }}</span>
            @endif
        </div>

        <input type="hidden"
            id="{{ $field['id'] }}"
            name="{{ $field['name'] ?? $field['id'] }}"
            value="{{ $value }}"
            @if(!empty($field['required']))
                required
            @endif
            {!! $field['attributes'] ?? '' !!}
        >

        <div class="preview-actions">
            <button type="button" class="button select-icon">
                <span class="dashicons dashicons-database"></span>
                {{ $field['button_text'] ?? 'Choose Icon' }}
            </button>
            
            @if(!empty($value))
                <button type="button" class="button clear-icon">
                    <span class="dashicons dashicons-no"></span>
                </button>
            @endif
        </div>
    </div>

    <div class="icon-picker-modal" style="display: none;">
        <div class="modal-header">
            <h3>Select Icon</h3>
            <button type="button" class="close-modal">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        </div>

        <div class="modal-toolbar">
            @if(count($field['icon_sets'] ?? ['dashicons']) > 1)
                <select class="icon-set-selector">
                    @foreach($field['icon_sets'] as $set)
                        <option value="{{ $set }}">
                            {{ ucfirst($set) }}
                        </option>
                    @endforeach
                </select>
            @endif

            <div class="search-wrapper">
                <input type="text" 
                    class="icon-search" 
                    placeholder="Search icons..."
                >
                <span class="dashicons dashicons-search"></span>
            </div>

            @if(!empty($field['show_categories']))
                <select class="category-filter">
                    <option value="">All Categories</option>
                    <!-- Categories will be populated dynamically -->
                </select>
            @endif
        </div>

        <div class="modal-content">
            <div class="icon-grid">
                <!-- Icons will be populated dynamically -->
                <div class="loading">
                    <span class="spinner"></span>
                </div>
            </div>

            <div class="no-results" style="display: none;">
                No icons found matching your search.
            </div>
        </div>

        <div class="modal-footer">
            <div class="selected-info">
                <span class="icon-name"></span>
                <span class="icon-code"></span>
            </div>
            
            @if(!empty($field['show_custom']))
                <button type="button" class="button custom-icon">
                    <span class="dashicons dashicons-upload"></span>
                    Custom Icon
                </button>
            @endif

            <div class="action-buttons">
                <button type="button" class="button cancel-selection">
                    Cancel
                </button>
                <button type="button" class="button button-primary confirm-selection">
                    Select Icon
                </button>
            </div>
        </div>
    </div>

    @if(!empty($field['show_custom']))
        <div class="custom-icon-modal" style="display: none;">
            <div class="modal-header">
                <h3>Custom Icon</h3>
                <button type="button" class="close-modal">
                    <span class="dashicons dashicons-no-alt"></span>
                </button>
            </div>

            <div class="modal-content">
                <div class="upload-area">
                    <input type="file" 
                        class="custom-icon-upload" 
                        accept="image/svg+xml,image/png"
                    >
                    <div class="upload-message">
                        <span class="dashicons dashicons-upload"></span>
                        <p>Drop SVG or PNG file here<br>or click to upload</p>
                    </div>
                </div>

                <div class="icon-preview" style="display: none;">
                    <img src="" alt="Custom icon preview">
                    <button type="button" class="button remove-custom">
                        Remove
                    </button>
                </div>

                <div class="icon-settings">
                    <label>
                        <span>Width:</span>
                        <input type="number" class="icon-width" min="1" max="1000">
                        px
                    </label>
                    <label>
                        <span>Height:</span>
                        <input type="number" class="icon-height" min="1" max="1000">
                        px
                    </label>
                    <label>
                        <input type="checkbox" class="maintain-ratio" checked>
                        Maintain aspect ratio
                    </label>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="button cancel-custom">
                    Cancel
                </button>
                <button type="button" class="button button-primary save-custom">
                    Save Custom Icon
                </button>
            </div>
        </div>
    @endif
</div>