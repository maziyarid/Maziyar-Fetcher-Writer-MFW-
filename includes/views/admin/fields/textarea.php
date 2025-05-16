<?php
/**
 * Textarea Field Template
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
<textarea
    id="{{ $field['id'] }}"
    name="{{ $field['name'] ?? $field['id'] }}"
    class="mfw-textarea-field {{ $field['class'] ?? '' }}"
    @if(!empty($field['placeholder']))
        placeholder="{{ $field['placeholder'] }}"
    @endif
    @if(!empty($field['rows']))
        rows="{{ $field['rows'] }}"
    @endif
    @if(!empty($field['cols']))
        cols="{{ $field['cols'] }}"
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
    {!! $field['attributes'] ?? '' !!}
>{{ $value }}</textarea>