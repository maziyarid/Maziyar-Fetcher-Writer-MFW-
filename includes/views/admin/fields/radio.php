<?php
/**
 * Radio Field Template
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
<div class="mfw-radio-group {{ $field['class'] ?? '' }}">
    @foreach($field['options'] as $option)
        <label class="mfw-radio-label">
            <input type="radio"
                name="{{ $field['name'] ?? $field['id'] }}"
                value="{{ $option['value'] }}"
                @if($option['value'] == $value)
                    checked
                @endif
                @if(!empty($field['required']))
                    required
                @endif
                @if(!empty($option['disabled']))
                    disabled
                @endif
                {!! $option['attributes'] ?? '' !!}
            >
            <span class="radio-text">{{ $option['label'] }}</span>
            @if(!empty($option['description']))
                <span class="radio-description">{{ $option['description'] }}</span>
            @endif
        </label>
    @endforeach
</div>