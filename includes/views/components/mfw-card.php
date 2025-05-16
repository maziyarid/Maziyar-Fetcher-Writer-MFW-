<?php
/**
 * Card Component Template
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
<div class="mfw-card {{ $attributes['class'] ?? '' }}">
    @if(!empty($title))
        <div class="mfw-card-header">
            <h3 class="mfw-card-title">{{ $title }}</h3>
            @if(!empty($actions))
                <div class="mfw-card-actions">
                    {{ $actions }}
                </div>
            @endif
        </div>
    @endif

    <div class="mfw-card-body">
        {{ $slot }}
    </div>

    @if(!empty($footer))
        <div class="mfw-card-footer">
            {{ $footer }}
        </div>
    @endif
</div>