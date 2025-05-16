<?php
/**
 * Range Slider Field Template
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
<div class="mfw-range-field {{ $field['class'] ?? '' }}">
    <div class="range-slider-wrapper">
        <input type="range"
            id="{{ $field['id'] }}"
            name="{{ $field['name'] ?? $field['id'] }}"
            value="{{ $value }}"
            class="mfw-range-slider"
            @if(isset($field['min']))
                min="{{ $field['min'] }}"
            @endif
            @if(isset($field['max']))
                max="{{ $field['max'] }}"
            @endif
            @if(isset($field['step']))
                step="{{ $field['step'] }}"
            @endif
            @if(!empty($field['required']))
                required
            @endif
            @if(!empty($field['disabled']))
                disabled
            @endif
            {!! $field['attributes'] ?? '' !!}
        >

        @if(!empty($field['show_value']))
            <input type="number"
                class="range-value"
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

    @if(!empty($field['show_labels']))
        <div class="range-labels">
            <span class="min-label">{{ $field['min_label'] ?? $field['min'] }}</span>
            @if(!empty($field['mid_label']))
                <span class="mid-label">{{ $field['mid_label'] }}</span>
            @endif
            <span class="max-label">{{ $field['max_label'] ?? $field['max'] }}</span>
        </div>
    @endif
</div>