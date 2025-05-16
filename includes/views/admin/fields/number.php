<?php
/**
 * Number Field Template
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
<div class="mfw-number-field {{ $field['class'] ?? '' }}">
    <div class="number-wrapper">
        @if(!empty($field['show_controls']))
            <button type="button" class="number-control decrease" tabindex="-1">
                <span class="dashicons dashicons-minus"></span>
            </button>
        @endif

        <input type="number"
            id="{{ $field['id'] }}"
            name="{{ $field['name'] ?? $field['id'] }}"
            value="{{ $value }}"
            class="number-input"
            @if(isset($field['min']))
                min="{{ $field['min'] }}"
            @endif
            @if(isset($field['max']))
                max="{{ $field['max'] }}"
            @endif
            @if(isset($field['step']))
                step="{{ $field['step'] }}"
            @endif
            @if(!empty($field['placeholder']))
                placeholder="{{ $field['placeholder'] }}"
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

        @if(!empty($field['show_controls']))
            <button type="button" class="number-control increase" tabindex="-1">
                <span class="dashicons dashicons-plus"></span>
            </button>
        @endif
    </div>

    @if(!empty($field['unit']))
        <span class="number-unit">{{ $field['unit'] }}</span>
    @endif

    @if(!empty($field['show_slider']))
        <input type="range"
            class="number-slider"
            value="{{ $value }}"
            @if(isset($field['min']))
                min="{{ $field['min'] }}"
            @endif
            @if(isset($field['max']))
                max="{{ $field['max'] }}"
            @endif
            @if(isset($field['step']))
                step="{{ $field['step'] }}"
            @endif
            @if(!empty($field['disabled']))
                disabled
            @endif
        >
    @endif
</div>