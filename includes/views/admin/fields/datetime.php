<?php
/**
 * Date/Time Picker Field Template
 * 
 * @package MFW
 * @subpackage Views
 * @since 1.0.0
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

$type = $field['type'] ?? 'datetime-local';
$format = $field['format'] ?? ($type === 'date' ? 'Y-m-d' : 'Y-m-d\TH:i');
?>
<div class="mfw-datetime-field {{ $field['class'] ?? '' }}">
    <input type="{{ $type }}"
        id="{{ $field['id'] }}"
        name="{{ $field['name'] ?? $field['id'] }}"
        value="{{ $value ? date($format, strtotime($value)) : '' }}"
        class="mfw-datetime-picker"
        data-format="{{ $field['display_format'] ?? '' }}"
        @if(!empty($field['min']))
            min="{{ date($format, strtotime($field['min'])) }}"
        @endif
        @if(!empty($field['max']))
            max="{{ date($format, strtotime($field['max'])) }}"
        @endif
        @if(!empty($field['step']))
            step="{{ $field['step'] }}"
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

    @if(!empty($field['show_clear']))
        <button type="button" class="button button-small clear-datetime">
            Clear
        </button>
    @endif

    @if(!empty($field['show_now']))
        <button type="button" class="button button-small set-now">
            Now
        </button>
    @endif
</div>