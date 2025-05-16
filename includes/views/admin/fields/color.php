<?php
/**
 * Color Picker Field Template
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
<div class="mfw-color-field {{ $field['class'] ?? '' }}">
    <input type="text"
        id="{{ $field['id'] }}"
        name="{{ $field['name'] ?? $field['id'] }}"
        value="{{ $value }}"
        class="mfw-color-picker"
        data-alpha="{{ !empty($field['alpha']) ? 'true' : 'false' }}"
        data-default-color="{{ $field['default'] ?? '' }}"
        data-palette="{{ json_encode($field['palette'] ?? true) }}"
        @if(!empty($field['required']))
            required
        @endif
        @if(!empty($field['disabled']))
            disabled
        @endif
        {!! $field['attributes'] ?? '' !!}
    >

    @if(!empty($field['show_clear']))
        <button type="button" class="button button-small clear-color">
            Clear
        </button>
    @endif

    @if(!empty($field['show_default']))
        <button type="button" class="button button-small default-color">
            Default
        </button>
    @endif
</div>