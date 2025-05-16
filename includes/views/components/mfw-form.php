<?php
/**
 * Form Component Template
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
<form 
    method="{{ $method }}" 
    action="{{ $action }}"
    class="mfw-form {{ $attributes['class'] ?? '' }}"
    {!! $attributes['id'] ? "id=\"{$attributes['id']}\"" : '' !!}
>
    {!! $csrf !!}

    @if(!empty($errors))
        <div class="mfw-form-errors">
            @foreach($errors as $error)
                <div class="mfw-error-message">
                    {{ $error['message'] }}
                </div>
            @endforeach
        </div>
    @endif

    {{ $slot }}
</form>