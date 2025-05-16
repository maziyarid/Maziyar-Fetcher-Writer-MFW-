<?php
/**
 * URL Field Template
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
<div class="mfw-url-field {{ $field['class'] ?? '' }}">
    <div class="url-wrapper">
        @if(!empty($field['show_protocol']))
            <select class="url-protocol" tabindex="-1">
                <option value="http://">http://</option>
                <option value="https://" selected>https://</option>
                <option value="ftp://">ftp://</option>
                <option value="sftp://">sftp://</option>
                <option value="//">//</option>
            </select>
        @endif

        <input type="url"
            id="{{ $field['id'] }}"
            name="{{ $field['name'] ?? $field['id'] }}"
            value="{{ $value }}"
            class="url-input"
            @if(!empty($field['placeholder']))
                placeholder="{{ $field['placeholder'] }}"
            @endif
            @if(!empty($field['pattern']))
                pattern="{{ $field['pattern'] }}"
            @endif
            @if(!empty($field['required']))
                required
            @endif
            @if(!empty($field['readonly']))
                readonly
            @endif
            @if(!empty($field['disabled']))
                disabled
            @endif
            {!! $field['attributes'] ?? '' !!}
        >

        @if(!empty($field['show_validate']))
            <button type="button" class="button validate-url" tabindex="-1">
                <span class="dashicons dashicons-yes-alt"></span>
            </button>
        @endif

        @if(!empty($field['show_qr']))
            <button type="button" class="button show-qr" tabindex="-1">
                <span class="dashicons dashicons-qr"></span>
            </button>
        @endif

        @if(!empty($field['show_visit']))
            <button type="button" class="button visit-url" tabindex="-1" target="_blank">
                <span class="dashicons dashicons-external"></span>
            </button>
        @endif
    </div>

    @if(!empty($field['show_preview']))
        <div class="url-preview">
            <div class="preview-content">
                <!-- Preview content will be loaded here -->
            </div>
            <div class="preview-loading">
                <span class="spinner"></span>
            </div>
            <div class="preview-error" style="display: none;">
                Failed to load preview
            </div>
        </div>
    @endif
</div>