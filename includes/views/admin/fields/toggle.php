<?php
/**
 * Toggle Switch Field Template
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
<label class="mfw-toggle-switch {{ $field['class'] ?? '' }}">
    <input type="checkbox"
        id="{{ $field['id'] }}"
        name="{{ $field['name'] ?? $field['id'] }}"
        value="{{ $field['value'] ?? '1' }}"
        @if($value)
            checked
        @endif
        @if(!empty($field['required']))
            required
        @endif
        @if(!empty($field['disabled']))
            disabled
        @endif
        {!! $field['attributes'] ?? '' !!}
    >
    <span class="toggle-slider">
        @if(!empty($field['icons']))
            <span class="toggle-on">
                <span class="dashicons dashicons-{{ $field['icons']['on'] }}"></span>
            </span>
            <span class="toggle-off">
                <span class="dashicons dashicons-{{ $field['icons']['off'] }}"></span>
            </span>
        @endif
    </span>
    @if(!empty($field['labels']))
        <span class="toggle-labels">
            <span class="label-on">{{ $field['labels']['on'] }}</span>
            <span class="label-off">{{ $field['labels']['off'] }}</span>
        </span>
    @endif
</label>