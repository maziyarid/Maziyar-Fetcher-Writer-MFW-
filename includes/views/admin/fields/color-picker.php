<?php
/**
 * Color Picker Field Template
 * Advanced color selection with AI palette generation and color scheme management
 * 
 * @package MFW
 * @subpackage Views
 * @since 1.0.0
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

$value = $value ?: ($field['default'] ?? '');
$format = $field['format'] ?? 'hex';
$alpha = !empty($field['alpha']);
$swatches = $field['swatches'] ?? [];
$enable_palette = !empty($field['enable_palette']);
?>
<div class="mfw-color-picker {{ $field['class'] ?? '' }}"
    data-format="{{ $format }}"
    data-alpha="{{ $alpha ? 'true' : 'false' }}"
>
    <!-- Color Display -->
    <div class="color-display">
        <div class="color-preview"
            style="background-color: {{ $value }}"
        ></div>
        <input type="text"
            class="color-value"
            value="{{ $value }}"
            placeholder="{{ $field['placeholder'] ?? 'Select color...' }}"
        >
        @if(!empty($field['show_copy']))
            <button type="button" class="copy-color" title="Copy color value">
                <span class="dashicons dashicons-clipboard"></span>
            </button>
        @endif
    </div>

    <!-- Color Picker Interface -->
    <div class="picker-interface" style="display: none;">
        <!-- Color Selection Area -->
        <div class="selection-area">
            <div class="color-field"></div>
            <div class="hue-slider"></div>
            @if($alpha)
                <div class="alpha-slider"></div>
            @endif
        </div>

        <!-- Color Information -->
        <div class="color-info">
            <!-- Format Switcher -->
            <div class="format-switcher">
                <button type="button" class="format-hex {{ $format === 'hex' ? 'active' : '' }}">
                    HEX
                </button>
                <button type="button" class="format-rgb {{ $format === 'rgb' ? 'active' : '' }}">
                    RGB
                </button>
                <button type="button" class="format-hsl {{ $format === 'hsl' ? 'active' : '' }}">
                    HSL
                </button>
            </div>

            <!-- Color Values -->
            <div class="color-values">
                <div class="value-group hex-group" {{ $format !== 'hex' ? 'style="display: none;"' : '' }}>
                    <label>HEX</label>
                    <input type="text" class="hex-value" maxlength="9">
                </div>
                <div class="value-group rgb-group" {{ $format !== 'rgb' ? 'style="display: none;"' : '' }}>
                    <div class="rgb-inputs">
                        <div class="input-wrapper">
                            <label>R</label>
                            <input type="number" class="rgb-r" min="0" max="255">
                        </div>
                        <div class="input-wrapper">
                            <label>G</label>
                            <input type="number" class="rgb-g" min="0" max="255">
                        </div>
                        <div class="input-wrapper">
                            <label>B</label>
                            <input type="number" class="rgb-b" min="0" max="255">
                        </div>
                        @if($alpha)
                            <div class="input-wrapper">
                                <label>A</label>
                                <input type="number" class="rgb-a" min="0" max="1" step="0.01">
                            </div>
                        @endif
                    </div>
                </div>
                <div class="value-group hsl-group" {{ $format !== 'hsl' ? 'style="display: none;"' : '' }}>
                    <div class="hsl-inputs">
                        <div class="input-wrapper">
                            <label>H</label>
                            <input type="number" class="hsl-h" min="0" max="360">
                        </div>
                        <div class="input-wrapper">
                            <label>S</label>
                            <input type="number" class="hsl-s" min="0" max="100">
                        </div>
                        <div class="input-wrapper">
                            <label>L</label>
                            <input type="number" class="hsl-l" min="0" max="100">
                        </div>
                        @if($alpha)
                            <div class="input-wrapper">
                                <label>A</label>
                                <input type="number" class="hsl-a" min="0" max="1" step="0.01">
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Color Swatches -->
        @if(!empty($swatches))
            <div class="color-swatches">
                <div class="swatches-header">
                    <span>Swatches</span>
                    @if(!empty($field['enable_custom_swatches']))
                        <button type="button" class="add-swatch" title="Add to swatches">
                            <span class="dashicons dashicons-plus"></span>
                        </button>
                    @endif
                </div>
                <div class="swatches-grid">
                    @foreach($swatches as $swatch)
                        <button type="button"
                            class="swatch-color"
                            data-color="{{ $swatch['value'] }}"
                            title="{{ $swatch['label'] ?? '' }}"
                            style="background-color: {{ $swatch['value'] }}"
                        ></button>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- AI Color Features -->
        @if(!empty($field['enable_ai']))
            <div class="ai-color-features">
                <div class="ai-actions">
                    <button type="button" class="generate-palette" title="Generate Color Palette">
                        <span class="dashicons dashicons-admin-customizer"></span>
                        Generate Palette
                    </button>
                    <button type="button" class="suggest-variations" title="Suggest Variations">
                        <span class="dashicons dashicons-art"></span>
                        Variations
                    </button>
                </div>
                <div class="ai-suggestions" style="display: none;">
                    <div class="suggestion-header">
                        <h4>AI Suggestions</h4>
                        <button type="button" class="close-suggestions">
                            <span class="dashicons dashicons-no"></span>
                        </button>
                    </div>
                    <div class="suggestion-content"></div>
                    <div class="suggestion-actions">
                        <button type="button" class="button regenerate-suggestions">
                            Regenerate
                        </button>
                        <button type="button" class="button save-suggestions">
                            Save to Swatches
                        </button>
                    </div>
                </div>
            </div>
        @endif
    </div>

    <!-- Hidden Input -->
    <input type="hidden"
        name="{{ $field['name'] }}"
        value="{{ $value }}"
        class="color-input"
    >
</div>