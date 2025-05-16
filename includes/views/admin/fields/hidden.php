<?php
/**
 * Hidden Field Template
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
<input type="hidden"
    id="{{ $field['id'] }}"
    name="{{ $field['name'] ?? $field['id'] }}"
    value="{{ $value }}"
    {!! $field['attributes'] ?? '' !!}
>