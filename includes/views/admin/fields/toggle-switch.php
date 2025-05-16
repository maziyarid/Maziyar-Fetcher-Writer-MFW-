<?php
/**
 * Toggle Switch Field Template
 * Modern toggle switch with customizable states and animations
 * 
 * @package MFW
 * @subpackage Views
 * @since 1.0.0
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

$value = (bool) $value;
$on_text = $field['on_text'] ?? 'On';
$off_text = $field['off_text'] ?? 'Off';
$size = $field['size'] ?? 'medium';
$style = $field['style'] ?? 'default';
?>
<div class="mfw-toggle-switch {{ $field['class'] ?? '' }} size-{{ $size }} style-{{ $style }}"
    data-on-text="{{ $on_text }}"
    data-off-text="{{ $off_text }}"
>
    <label class="switch-wrapper">
        <input type="checkbox"
            name="{{ $field['name'] }}"
            value="1"
            @if($value)
                checked
            @endif
            @if(!empty($field['disabled']))
                disabled
            @endif
        >
        <span class="switch-track">
            <span class="switch-handle"></span>
            @if(!empty($field['show_icons']))
                <span class="switch-icons">
                    <span class="icon-on">
                        <span class="dashicons dashicons-{{ $field['icon_on'] ?? 'yes' }}"></span>
                    </span>
                    <span class="icon-off">
                        <span class="dashicons dashicons-{{ $field['icon_off'] ?? 'no' }}"></span>
                    </span>
                </span>
            @endif
        </span>
        @if(!empty($field['show_labels']))
            <span class="switch-labels">
                <span class="label-on">{{ $on_text }}</span>
                <span class="label-off">{{ $off_text }}</span>
            </span>
        @endif
    </label>

    @if(!empty($field['show_description']))
        <div class="switch-description">
            <span class="description-on" {!! !$value ? 'style="display: none;"' : '' !!}>
                {{ $field['description_on'] ?? '' }}
            </span>
            <span class="description-off" {!! $value ? 'style="display: none;"' : '' !!}>
                {{ $field['description_off'] ?? '' }}
            </span>
        </div>
    @endif

    @if(!empty($field['show_dependent_fields']))
        <div class="dependent-fields">
            <div class="fields-on" {!! !$value ? 'style="display: none;"' : '' !!}>
                {!! $field['fields_on'] ?? '' !!}
            </div>
            <div class="fields-off" {!! $value ? 'style="display: none;"' : '' !!}>
                {!! $field['fields_off'] ?? '' !!}
            </div>
        </div>
    @endif
</div>