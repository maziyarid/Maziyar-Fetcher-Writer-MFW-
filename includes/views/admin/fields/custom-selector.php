<?php
/**
 * Custom Selector Field Template
 * 
 * @package MFW
 * @subpackage Views
 * @since 1.0.0
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

$multiple = !empty($field['multiple']);
$options = $field['options'] ?? [];
$selected = (array)$value;
$display_type = $field['display'] ?? 'dropdown'; // dropdown, radio, button, image, color
?>
<div class="mfw-custom-selector {{ $field['class'] ?? '' }}"
    data-type="{{ $display_type }}"
    data-multiple="{{ $multiple ? 'true' : 'false' }}"
>
    @if($display_type === 'dropdown')
        <select
            name="{{ $field['name'] }}{{ $multiple ? '[]' : '' }}"
            @if($multiple)
                multiple
                size="{{ $field['size'] ?? 5 }}"
            @endif
            @if(!empty($field['required']))
                required
            @endif
        >
            @if(!$multiple && !empty($field['placeholder']))
                <option value="">{{ $field['placeholder'] }}</option>
            @endif

            @foreach($options as $option_value => $option)
                <option value="{{ $option_value }}"
                    @if(in_array($option_value, $selected))
                        selected
                    @endif
                    @if(!empty($option['disabled']))
                        disabled
                    @endif
                    @if(!empty($option['data']))
                        @foreach($option['data'] as $data_key => $data_value)
                            data-{{ $data_key }}="{{ $data_value }}"
                        @endforeach
                    @endif
                >
                    {{ $option['label'] ?? $option_value }}
                </option>
            @endforeach
        </select>

    @elseif($display_type === 'radio')
        <div class="radio-options">
            @foreach($options as $option_value => $option)
                <label class="radio-option">
                    <input type="radio"
                        name="{{ $field['name'] }}"
                        value="{{ $option_value }}"
                        @if(in_array($option_value, $selected))
                            checked
                        @endif
                        @if(!empty($option['disabled']))
                            disabled
                        @endif
                        @if(!empty($field['required']))
                            required
                        @endif
                    >
                    @if(!empty($option['icon']))
                        <span class="option-icon">
                            <span class="dashicons dashicons-{{ $option['icon'] }}"></span>
                        </span>
                    @endif
                    <span class="option-label">{{ $option['label'] ?? $option_value }}</span>
                    @if(!empty($option['description']))
                        <span class="option-description">{{ $option['description'] }}</span>
                    @endif
                </label>
            @endforeach
        </div>

    @elseif($display_type === 'button')
        <div class="button-options">
            @foreach($options as $option_value => $option)
                <button type="button"
                    class="option-button {{ in_array($option_value, $selected) ? 'selected' : '' }}"
                    data-value="{{ $option_value }}"
                    @if(!empty($option['disabled']))
                        disabled
                    @endif
                >
                    @if(!empty($option['icon']))
                        <span class="dashicons dashicons-{{ $option['icon'] }}"></span>
                    @endif
                    {{ $option['label'] ?? $option_value }}
                </button>
            @endforeach
        </div>
        <input type="hidden"
            name="{{ $field['name'] }}{{ $multiple ? '[]' : '' }}"
            value="{{ implode(',', $selected) }}"
        >

    @elseif($display_type === 'image')
        <div class="image-options">
            @foreach($options as $option_value => $option)
                <label class="image-option {{ in_array($option_value, $selected) ? 'selected' : '' }}">
                    <input type="{{ $multiple ? 'checkbox' : 'radio' }}"
                        name="{{ $field['name'] }}{{ $multiple ? '[]' : '' }}"
                        value="{{ $option_value }}"
                        @if(in_array($option_value, $selected))
                            checked
                        @endif
                        @if(!empty($option['disabled']))
                            disabled
                        @endif
                    >
                    <img src="{{ $option['image'] }}" 
                        alt="{{ $option['label'] ?? $option_value }}"
                        @if(!empty($option['srcset']))
                            srcset="{{ $option['srcset'] }}"
                        @endif
                    >
                    <span class="image-label">{{ $option['label'] ?? $option_value }}</span>
                </label>
            @endforeach
        </div>

    @elseif($display_type === 'color')
        <div class="color-options">
            @foreach($options as $option_value => $option)
                <label class="color-option {{ in_array($option_value, $selected) ? 'selected' : '' }}"
                    title="{{ $option['label'] ?? $option_value }}"
                >
                    <input type="{{ $multiple ? 'checkbox' : 'radio' }}"
                        name="{{ $field['name'] }}{{ $multiple ? '[]' : '' }}"
                        value="{{ $option_value }}"
                        @if(in_array($option_value, $selected))
                            checked
                        @endif
                        @if(!empty($option['disabled']))
                            disabled
                        @endif
                    >
                    <span class="color-swatch" style="background-color: {{ $option['color'] }};">
                        @if(in_array($option_value, $selected))
                            <span class="dashicons dashicons-yes"></span>
                        @endif
                    </span>
                    @if(!empty($field['show_labels']))
                        <span class="color-label">{{ $option['label'] ?? $option_value }}</span>
                    @endif
                </label>
            @endforeach
        </div>
    @endif

    @if(!empty($field['show_search']) && count($options) > 10)
        <div class="selector-search">
            <input type="text"
                class="search-input"
                placeholder="{{ $field['search_placeholder'] ?? 'Search options...' }}"
            >
        </div>
    @endif

    @if($multiple && !empty($field['show_controls']))
        <div class="selector-controls">
            <button type="button" class="button select-all">
                Select All
            </button>
            <button type="button" class="button clear-all">
                Clear All
            </button>
        </div>
    @endif

    @if(!empty($field['show_selected_count']) && $multiple)
        <div class="selected-count">
            Selected: <span class="count">{{ count($selected) }}</span>
            @if(!empty($field['max_selections']))
                / <span class="max">{{ $field['max_selections'] }}</span>
            @endif
        </div>
    @endif
</div>