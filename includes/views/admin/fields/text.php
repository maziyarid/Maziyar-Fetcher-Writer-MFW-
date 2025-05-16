<?php
/**
 * Text Field Template
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
<input type="text"
    id="{{ $field['id'] }}"
    name="{{ $field['name'] ?? $field['id'] }}"
    value="{{ $value }}"
    class="mfw-text-field {{ $field['class'] ?? '' }}"
    @if(!empty($field['placeholder']))
        placeholder="{{ $field['placeholder'] }}"
    @endif
    @if(!empty($field['pattern']))
        pattern="{{ $field['pattern'] }}"
    @endif
    @if(!empty($field['maxlength']))
        maxlength="{{ $field['maxlength'] }}"
    @endif
    @if(!empty($field['minlength']))
        minlength="{{ $field['minlength'] }}"
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
    @if(!empty($field['autocomplete']))
        autocomplete="{{ $field['autocomplete'] }}"
    @endif
    {!! $field['attributes'] ?? '' !!}
>