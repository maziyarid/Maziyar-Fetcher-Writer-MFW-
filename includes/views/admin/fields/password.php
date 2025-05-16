<?php
/**
 * Password Field Template
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
<div class="mfw-password-field {{ $field['class'] ?? '' }}">
    <div class="password-wrapper">
        <input type="password"
            id="{{ $field['id'] }}"
            name="{{ $field['name'] ?? $field['id'] }}"
            value="{{ $value }}"
            class="password-input"
            @if(!empty($field['placeholder']))
                placeholder="{{ $field['placeholder'] }}"
            @endif
            @if(!empty($field['maxlength']))
                maxlength="{{ $field['maxlength'] }}"
            @endif
            @if(!empty($field['minlength']))
                minlength="{{ $field['minlength'] }}"
            @endif
            @if(!empty($field['pattern']))
                pattern="{{ $field['pattern'] }}"
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
            autocomplete="{{ $field['autocomplete'] ?? 'new-password' }}"
            {!! $field['attributes'] ?? '' !!}
        >

        @if(!empty($field['show_toggle']))
            <button type="button" class="toggle-password" title="Toggle password visibility">
                <span class="dashicons dashicons-visibility"></span>
            </button>
        @endif
    </div>

    @if(!empty($field['show_strength']))
        <div class="password-strength">
            <div class="strength-meter">
                <div class="strength-meter-fill" data-strength="0"></div>
            </div>
            <div class="strength-text"></div>
        </div>
    @endif

    @if(!empty($field['requirements']))
        <div class="password-requirements">
            <ul>
                @foreach($field['requirements'] as $req)
                    <li class="requirement" data-requirement="{{ $req['id'] }}">
                        <span class="dashicons"></span>
                        {{ $req['text'] }}
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if(!empty($field['generate_password']))
        <button type="button" class="button generate-password">
            Generate Strong Password
        </button>
    @endif
</div>