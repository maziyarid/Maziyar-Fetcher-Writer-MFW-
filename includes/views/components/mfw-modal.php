<?php
/**
 * Modal Component Template
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
<div 
    class="mfw-modal {{ $attributes['class'] ?? '' }}" 
    id="{{ $attributes['id'] ?? 'mfw-modal' }}"
    role="dialog"
    aria-labelledby="{{ $attributes['id'] ?? 'mfw-modal' }}-title"
    aria-hidden="true"
>
    <div class="mfw-modal-overlay"></div>
    
    <div class="mfw-modal-container">
        <div class="mfw-modal-header">
            <h2 class="mfw-modal-title" id="{{ $attributes['id'] ?? 'mfw-modal' }}-title">
                {{ $title }}
            </h2>
            <button type="button" class="mfw-modal-close" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>

        <div class="mfw-modal-body">
            {{ $slot }}
        </div>

        @if(!empty($footer))
            <div class="mfw-modal-footer">
                {{ $footer }}
            </div>
        @endif
    </div>
</div>