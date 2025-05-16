<?php
/**
 * Dimensions Field Template
 * 
 * @package MFW
 * @subpackage Views
 * @since 1.0.0
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

$units = $field['units'] ?? ['px', '%', 'em', 'rem', 'vw', 'vh'];
$dimensions = $field['dimensions'] ?? ['top', 'right', 'bottom', 'left'];
$linked = !empty($value['linked']) && $value['linked'] === 'true';
?>
<div class="mfw-dimensions-field {{ $field['class'] ?? '' }}"
    data-default-unit="{{ $field['default_unit'] ?? 'px' }}"
>
    <div class="dimensions-header">
        @if(!empty($field['show_label']))
            <div class="dimensions-label">
                <span class="dashicons dashicons-{{ $field['icon'] ?? 'editor-expand' }}"></span>
                {{ $field['dimension_label'] ?? 'Dimensions' }}
            </div>
        @endif

        <div class="dimensions-controls">
            @if(!empty($field['show_units']))
                <select class="dimension-unit">
                    @foreach($units as $unit)
                        <option value="{{ $unit }}"
                            @if(($value['unit'] ?? $field['default_unit'] ?? 'px') === $unit)
                                selected
                            @endif
                        >
                            {{ $unit }}
                        </option>
                    @endforeach
                </select>
            @endif

            @if(!empty($field['allow_linking']))
                <button type="button" 
                    class="link-values {{ $linked ? 'linked' : '' }}"
                    title="{{ $linked ? 'Unlink values' : 'Link values' }}"
                >
                    <span class="dashicons dashicons-{{ $linked ? 'admin-links' : 'editor-unlink' }}"></span>
                </button>
            @endif
        </div>
    </div>

    <div class="dimensions-inputs">
        @foreach($dimensions as $dimension)
            <div class="dimension-group">
                <input type="number"
                    class="dimension-input"
                    data-dimension="{{ $dimension }}"
                    value="{{ $value[$dimension] ?? '' }}"
                    @if(isset($field['min']))
                        min="{{ $field['min'] }}"
                    @endif
                    @if(isset($field['max']))
                        max="{{ $field['max'] }}"
                    @endif
                    @if(isset($field['step']))
                        step="{{ $field['step'] }}"
                    @endif
                    @if(!empty($field['required']))
                        required
                    @endif
                    @if(!empty($field['disabled']))
                        disabled
                    @endif
                >
                <label class="dimension-label">{{ ucfirst($dimension) }}</label>
            </div>
        @endforeach

        @if(!empty($field['show_center']))
            <div class="dimension-center">
                <span class="dashicons dashicons-marker"></span>
            </div>
        @endif
    </div>

    <!-- Hidden inputs for form submission -->
    @foreach($dimensions as $dimension)
        <input type="hidden"
            name="{{ $field['name'] }}[{{ $dimension }}]"
            value="{{ $value[$dimension] ?? '' }}"
        >
    @endforeach

    <input type="hidden"
        name="{{ $field['name'] }}[unit]"
        value="{{ $value['unit'] ?? $field['default_unit'] ?? 'px' }}"
    >

    @if(!empty($field['allow_linking']))
        <input type="hidden"
            name="{{ $field['name'] }}[linked]"
            value="{{ $linked ? 'true' : 'false' }}"
        >
    @endif

    @if(!empty($field['show_presets']))
        <div class="dimensions-presets">
            <div class="presets-label">
                {{ $field['presets_label'] ?? 'Presets' }}
            </div>
            <div class="presets-list">
                @foreach($field['presets'] as $preset)
                    <button type="button" 
                        class="preset-button"
                        data-values="{{ json_encode($preset['values']) }}"
                        title="{{ $preset['label'] }}"
                    >
                        @if(!empty($preset['icon']))
                            <span class="dashicons dashicons-{{ $preset['icon'] }}"></span>
                        @endif
                        {{ $preset['label'] }}
                    </button>
                @endforeach
            </div>
        </div>
    @endif

    @if(!empty($field['show_reset']))
        <button type="button" class="button reset-dimensions">
            <span class="dashicons dashicons-image-rotate"></span>
            Reset to Default
        </button>
    @endif
</div>