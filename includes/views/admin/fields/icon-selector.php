<?php
/**
 * Icon Selector Field Template
 * Advanced icon picker with multiple icon libraries support
 * 
 * @package MFW
 * @subpackage Views
 * @since 1.0.0
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

$selected_icon = $value ?: ($field['default'] ?? '');
$selected_library = explode('-', $selected_icon)[0] ?? 'dashicons';
$libraries = $field['libraries'] ?? ['dashicons', 'fontawesome', 'material'];
?>
<div class="mfw-icon-selector {{ $field['class'] ?? '' }}"
    data-selected="{{ $selected_icon }}"
>
    <!-- Library Selector -->
    <div class="icon-libraries">
        @foreach($libraries as $library)
            <button type="button" 
                class="library-tab {{ $library === $selected_library ? 'active' : '' }}"
                data-library="{{ $library }}"
            >
                <span class="library-icon {{ $library }}"></span>
                {{ ucfirst($library) }}
            </button>
        @endforeach
    </div>

    <!-- Search and Categories -->
    <div class="icon-controls">
        <div class="search-wrapper">
            <input type="text"
                class="icon-search"
                placeholder="Search icons..."
            >
            <span class="dashicons dashicons-search"></span>
        </div>

        @if(!empty($field['show_categories']))
            <div class="category-filter">
                <select class="icon-category">
                    <option value="">All Categories</option>
                </select>
            </div>
        @endif
    </div>

    <!-- Icons Grid -->
    <div class="icons-grid">
        <div class="loading-icons">
            <span class="spinner"></span>
            Loading icons...
        </div>
    </div>

    <!-- Selected Icon Preview -->
    <div class="selected-icon">
        <div class="preview-area">
            @if($selected_icon)
                <span class="{{ $selected_icon }}"></span>
            @else
                <span class="no-icon">No icon selected</span>
            @endif
        </div>
        <div class="icon-details">
            <input type="text"
                class="icon-class"
                value="{{ $selected_icon }}"
                readonly
            >
            @if(!empty($field['show_copy']))
                <button type="button" class="copy-class" title="Copy class name">
                    <span class="dashicons dashicons-clipboard"></span>
                </button>
            @endif
        </div>
    </div>

    <!-- Icon Settings -->
    @if(!empty($field['show_settings']))
        <div class="icon-settings">
            <button type="button" class="toggle-settings">
                <span class="dashicons dashicons-admin-generic"></span>
                Icon Settings
            </button>
            <div class="settings-panel" style="display: none;">
                <!-- Size -->
                <div class="setting-group">
                    <label>Size</label>
                    <input type="range"
                        class="icon-size"
                        min="12"
                        max="64"
                        value="{{ $field['size'] ?? 24 }}"
                    >
                    <span class="size-value">{{ $field['size'] ?? 24 }}px</span>
                </div>

                <!-- Color -->
                <div class="setting-group">
                    <label>Color</label>
                    <input type="color"
                        class="icon-color"
                        value="{{ $field['color'] ?? '#000000' }}"
                    >
                </div>

                <!-- Rotation -->
                <div class="setting-group">
                    <label>Rotation</label>
                    <input type="number"
                        class="icon-rotation"
                        min="0"
                        max="360"
                        value="{{ $field['rotation'] ?? 0 }}"
                    >
                </div>
            </div>
        </div>
    @endif

    <!-- Hidden Input -->
    <input type="hidden"
        name="{{ $field['name'] }}"
        value="{{ $selected_icon }}"
        class="icon-value"
    >
</div>