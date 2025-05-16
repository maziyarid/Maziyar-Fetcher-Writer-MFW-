<?php
/**
 * Checkbox Field Template
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
@if(!empty($field['options']))
    <div class="mfw-checkbox-group {{ $field['class'] ?? '' }}">
        @foreach($field['options'] as $option)
            <label class="mfw-checkbox-label">
                <input type="checkbox"
                    name="{{ $field['name'] ?? $field['id'] }}[]"
                    value="{{ $option['value'] }}"
                    @if(in_array($option['value'], (array)$value))
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
                <span class="checkbox-text">{{ $option['label'] }}</span>
            </label>
        @endforeach
    </div>
@else
    <label class="mfw-checkbox-label">
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
        @if(!empty($field['label_text']))
            <span class="checkbox-text">{{ $field['label_text'] }}</span>
        @endif
    </label>
@endif