<?php
/**
 * Multi-select Field Template
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
<div class="mfw-multiselect-field {{ $field['class'] ?? '' }}"
    data-search="{{ !empty($field['search']) ? 'true' : 'false' }}"
    data-max-items="{{ $field['max_items'] ?? 0 }}"
>
    <select
        id="{{ $field['id'] }}"
        name="{{ $field['name'] ?? $field['id'] }}[]"
        multiple
        @if(!empty($field['required']))
            required
        @endif
        @if(!empty($field['disabled']))
            disabled
        @endif
        {!! $field['attributes'] ?? '' !!}
    >
        @foreach($field['options'] as $option)
            <option 
                value="{{ $option['value'] }}"
                @if(in_array($option['value'], (array)$value))
                    selected
                @endif
                @if(!empty($option['disabled']))
                    disabled
                @endif
                {!! $option['attributes'] ?? '' !!}
            >
                {{ $option['label'] }}
            </option>
        @endforeach
    </select>

    @if(!empty($field['show_selected_count']))
        <div class="selected-count">
            Selected: <span class="count">0</span>
        </div>
    @endif

    @if(!empty($field['show_actions']))
        <div class="multiselect-actions">
            <button type="button" class="button select-all">Select All</button>
            <button type="button" class="button clear-all">Clear All</button>
        </div>
    @endif
</div>