<?php
/**
 * Alert Component Template
 * 
 * @package MFW
 * @subpackage Views
 * @since 1.0.0
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

$type = $attributes['type'] ?? 'info';
$dismissible = $attributes['dismissible'] ?? false;
?>
<div 
    class="mfw-alert mfw-alert-{{ $type }} {{ $dismissible ? 'mfw-alert-dismissible' : '' }} {{ $attributes['class'] ?? '' }}"
    role="alert"
>
    @if(!empty($title))
        <div class="mfw-alert-title">
            @if(!empty($icon))
                <span class="mfw-alert-icon">
                    {!! $icon !!}
                </span>
            @endif
            <h4>{{ $title }}</h4>
        </div>
    @endif

    <div class="mfw-alert-content">
        {{ $slot }}
    </div>

    @if($dismissible)
        <button type="button" class="mfw-alert-close" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    @endif
</div>