<?php
/**
 * Range Slider Field Template
 * Advanced range slider with multiple handles, steps, and visual markers
 * 
 * @package MFW
 * @subpackage Views
 * @since 1.0.0
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

$value = wp_parse_args($value, [
    'start' => $field['min'] ?? 0,
    'end' => $field['max'] ?? 100,
    'custom_values' => []
]);

$min = $field['min'] ?? 0;
$max = $field['max'] ?? 100;
$step = $field['step'] ?? 1;
$is_range = !empty($field['range']);
$show_labels = !empty($field['show_labels']);
$show_tooltips = !empty($field['show_tooltips']);
$show_input = !empty($field['show_input']);
$is_vertical = !empty($field['vertical']);
$connect = $field['connect'] ?? true;
$unit = $field['unit'] ?? '';
$decimal_places = $field['decimal_places'] ?? 0;
?>
<div class="mfw-range-slider {{ $field['class'] ?? '' }}"
    data-min="{{ $min }}"
    data-max="{{ $max }}"
    data-step="{{ $step }}"
    data-range="{{ $is_range ? 'true' : 'false' }}"
    data-vertical="{{ $is_vertical ? 'true' : 'false' }}"
    data-connect="{{ $connect ? 'true' : 'false' }}"
    data-unit="{{ $unit }}"
    data-decimals="{{ $decimal_places }}"
>
    <!-- Slider Container -->
    <div class="slider-container {{ $is_vertical ? 'vertical' : 'horizontal' }}">
        @if($show_labels)
            <div class="slider-labels">
                @if(!empty($field['custom_labels']))
                    @foreach($field['custom_labels'] as $value => $label)
                        <span class="label-marker" style="left: {{ ($value - $min) / ($max - $min) * 100 }}%">
                            {{ $label }}
                        </span>
                    @endforeach
                @else
                    <span class="label-min">{{ $min }}{{ $unit }}</span>
                    <span class="label-max">{{ $max }}{{ $unit }}</span>
                @endif
            </div>
        @endif

        <div class="slider-track">
            <!-- Visual Markers -->
            @if(!empty($field['markers']))
                <div class="track-markers">
                    @foreach($field['markers'] as $marker)
                        <span class="track-marker" 
                            style="left: {{ ($marker - $min) / ($max - $min) * 100 }}%"
                            data-value="{{ $marker }}"
                        ></span>
                    @endforeach
                </div>
            @endif

            <!-- Color Zones -->
            @if(!empty($field['zones']))
                <div class="track-zones">
                    @foreach($field['zones'] as $zone)
                        <div class="track-zone"
                            style="
                                left: {{ ($zone['start'] - $min) / ($max - $min) * 100 }}%;
                                width: {{ ($zone['end'] - $zone['start']) / ($max - $min) * 100 }}%;
                                background-color: {{ $zone['color'] }};"
                            title="{{ $zone['label'] ?? '' }}"
                        ></div>
                    @endforeach
                </div>
            @endif

            <!-- Actual Slider -->
            <div class="slider-element"></div>

            @if($show_tooltips)
                <div class="slider-tooltips">
                    <div class="tooltip start-tooltip"></div>
                    @if($is_range)
                        <div class="tooltip end-tooltip"></div>
                    @endif
                </div>
            @endif
        </div>

        @if(!empty($field['show_scale']))
            <div class="slider-scale">
                @for($i = $min; $i <= $max; $i += $field['scale_step'] ?? ($max - $min) / 10)
                    <span class="scale-marker" style="left: {{ ($i - $min) / ($max - $min) * 100 }}%">
                        {{ number_format($i, $decimal_places) }}{{ $unit }}
                    </span>
                @endfor
            </div>
        @endif
    </div>

    <!-- Input Controls -->
    @if($show_input)
        <div class="slider-inputs">
            <div class="input-group start-input">
                <label>{{ $field['start_label'] ?? 'Start' }}</label>
                <div class="input-wrapper">
                    <input type="number"
                        name="{{ $field['name'] }}[start]"
                        value="{{ $value['start'] }}"
                        min="{{ $min }}"
                        max="{{ $max }}"
                        step="{{ $step }}"
                    >
                    @if($unit)
                        <span class="unit-label">{{ $unit }}</span>
                    @endif
                </div>
            </div>

            @if($is_range)
                <div class="input-group end-input">
                    <label>{{ $field['end_label'] ?? 'End' }}</label>
                    <div class="input-wrapper">
                        <input type="number"
                            name="{{ $field['name'] }}[end]"
                            value="{{ $value['end'] }}"
                            min="{{ $min }}"
                            max="{{ $max }}"
                            step="{{ $step }}"
                        >
                        @if($unit)
                            <span class="unit-label">{{ $unit }}</span>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    @endif

    <!-- Preset Values -->
    @if(!empty($field['presets']))
        <div class="slider-presets">
            @foreach($field['presets'] as $preset => $preset_value)
                <button type="button" 
                    class="preset-button"
                    data-value="{{ is_array($preset_value) ? json_encode($preset_value) : $preset_value }}"
                >
                    {{ $preset }}
                </button>
            @endforeach
        </div>
    @endif

    <!-- Custom Values -->
    @if(!empty($field['allow_custom']))
        <div class="custom-values">
            <button type="button" class="add-custom-value">
                <span class="dashicons dashicons-plus"></span>
                Add Custom Value
            </button>
            <div class="custom-values-list">
                @foreach($value['custom_values'] as $custom_value)
                    <div class="custom-value-item">
                        <input type="number"
                            name="{{ $field['name'] }}[custom_values][]"
                            value="{{ $custom_value }}"
                            min="{{ $min }}"
                            max="{{ $max }}"
                            step="{{ $step }}"
                        >
                        <button type="button" class="remove-custom-value">
                            <span class="dashicons dashicons-no"></span>
                        </button>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- Advanced Options -->
    @if(!empty($field['show_advanced']))
        <div class="advanced-options">
            <button type="button" class="toggle-advanced">
                Advanced Options
                <span class="dashicons dashicons-arrow-down-alt2"></span>
            </button>
            <div class="advanced-panel" style="display: none;">
                <div class="option-group">
                    <label>
                        <input type="checkbox"
                            class="option-smooth"
                            @if(!empty($field['smooth']))
                                checked
                            @endif
                        >
                        Smooth Sliding
                    </label>
                </div>
                <div class="option-group">
                    <label>Animation Speed</label>
                    <input type="range"
                        class="option-animation"
                        min="0"
                        max="1000"
                        step="50"
                        value="{{ $field['animation_speed'] ?? 300 }}"
                    >
                </div>
            </div>
        </div>
    @endif

    <!-- Hidden Inputs for Form Submission -->
    @if(!$show_input)
        <input type="hidden"
            name="{{ $field['name'] }}[start]"
            value="{{ $value['start'] }}"
            class="hidden-start"
        >
        @if($is_range)
            <input type="hidden"
                name="{{ $field['name'] }}[end]"
                value="{{ $value['end'] }}"
                class="hidden-end"
            >
        @endif
    @endif
</div>