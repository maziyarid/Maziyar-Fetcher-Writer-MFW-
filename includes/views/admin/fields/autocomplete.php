<?php
/**
 * Autocomplete Field Template
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
<div class="mfw-autocomplete-field {{ $field['class'] ?? '' }}"
    data-min-length="{{ $field['min_length'] ?? 2 }}"
    data-max-items="{{ $field['max_items'] ?? 10 }}"
    data-delay="{{ $field['delay'] ?? 300 }}"
    data-source="{{ $field['source'] ?? '' }}"
    data-multiple="{{ !empty($field['multiple']) ? 'true' : 'false' }}"
>
    <div class="autocomplete-wrapper">
        <input type="text"
            class="autocomplete-input"
            placeholder="{{ $field['placeholder'] ?? '' }}"
            @if(!empty($field['disabled']))
                disabled
            @endif
        >

        <input type="hidden"
            id="{{ $field['id'] }}"
            name="{{ $field['name'] ?? $field['id'] }}{{ !empty($field['multiple']) ? '[]' : '' }}"
            value="{{ is_array($value) ? implode(',', $value) : $value }}"
            @if(!empty($field['required']))
                required
            @endif
            {!! $field['attributes'] ?? '' !!}
        >

        @if(!empty($field['multiple']))
            <div class="selected-items">
                @if(!empty($value))
                    @foreach((array)$value as $item)
                        <div class="selected-item" data-value="{{ $item['value'] }}">
                            @if(!empty($item['image']))
                                <img src="{{ $item['image'] }}" alt="{{ $item['label'] }}">
                            @endif
                            <span class="item-label">{{ $item['label'] }}</span>
                            <button type="button" class="remove-item">
                                <span class="dashicons dashicons-no"></span>
                            </button>
                        </div>
                    @endforeach
                @endif
            </div>
        @endif
    </div>

    @if(!empty($field['show_loading']))
        <div class="loading-indicator" style="display: none;">
            <span class="spinner"></span>
        </div>
    @endif
</div>