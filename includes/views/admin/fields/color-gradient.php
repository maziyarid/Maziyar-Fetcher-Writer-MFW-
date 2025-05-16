<?php
/**
 * Color Gradient Field Template
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
    'type' => 'linear',
    'angle' => '90',
    'radial_position' => 'center center',
    'stops' => [
        ['color' => '#000000', 'position' => '0'],
        ['color' => '#ffffff', 'position' => '100']
    ]
]);
?>
<div class="mfw-color-gradient {{ $field['class'] ?? '' }}"
    data-min-stops="{{ $field['min_stops'] ?? 2 }}"
    data-max-stops="{{ $field['max_stops'] ?? 5 }}"
>
    <div class="gradient-preview">
        <div class="preview-box" style="
            background: {{ $value['type'] === 'linear' 
                ? "linear-gradient({$value['angle']}deg, " 
                : "radial-gradient(at {$value['radial_position']}, "
            }}
            @foreach($value['stops'] as $stop)
                {{ $stop['color'] }} {{ $stop['position'] }}%
                {{ !$loop->last ? ',' : '' }}
            @endforeach
            );"
        ></div>
    </div>

    <div class="gradient-controls">
        <!-- Gradient Type -->
        <div class="control-group type-control">
            <label>Gradient Type</label>
            <div class="button-group">
                <button type="button" 
                    class="button {{ $value['type'] === 'linear' ? 'active' : '' }}"
                    data-type="linear"
                >
                    <span class="dashicons dashicons-image-rotate-left"></span>
                    Linear
                </button>
                <button type="button"
                    class="button {{ $value['type'] === 'radial' ? 'active' : '' }}"
                    data-type="radial"
                >
                    <span class="dashicons dashicons-marker"></span>
                    Radial
                </button>
            </div>
            <input type="hidden"
                name="{{ $field['name'] }}[type]"
                value="{{ $value['type'] }}"
            >
        </div>

        <!-- Angle Control (Linear) -->
        <div class="control-group angle-control"
            @if($value['type'] !== 'linear')
                style="display: none;"
            @endif
        >
            <label>Angle</label>
            <div class="angle-slider-wrapper">
                <input type="range"
                    class="angle-slider"
                    min="0"
                    max="360"
                    value="{{ $value['angle'] }}"
                >
                <div class="angle-preview">
                    <div class="angle-indicator" style="transform: rotate({{ $value['angle'] }}deg)">
                        <span class="dashicons dashicons-arrow-right-alt"></span>
                    </div>
                </div>
                <input type="number"
                    name="{{ $field['name'] }}[angle]"
                    value="{{ $value['angle'] }}"
                    class="angle-input"
                    min="0"
                    max="360"
                >
            </div>
        </div>

        <!-- Position Control (Radial) -->
        <div class="control-group position-control"
            @if($value['type'] !== 'radial')
                style="display: none;"
            @endif
        >
            <label>Center Position</label>
            <div class="position-grid">
                @foreach(['top left', 'top center', 'top right',
                         'center left', 'center center', 'center right',
                         'bottom left', 'bottom center', 'bottom right'] as $position)
                    <button type="button"
                        class="position-button {{ $value['radial_position'] === $position ? 'active' : '' }}"
                        data-position="{{ $position }}"
                        title="{{ ucwords($position) }}"
                    ></button>
                @endforeach
            </div>
            <input type="hidden"
                name="{{ $field['name'] }}[radial_position]"
                value="{{ $value['radial_position'] }}"
            >
        </div>

        <!-- Color Stops -->
        <div class="control-group stops-control">
            <label>Color Stops</label>
            <div class="stops-container">
                <div class="stops-track">
                    <div class="stops-gradient" style="
                        background: {{ $value['type'] === 'linear' 
                            ? "linear-gradient(to right, " 
                            : "linear-gradient(to right, "
                        }}
                        @foreach($value['stops'] as $stop)
                            {{ $stop['color'] }} {{ $stop['position'] }}%
                            {{ !$loop->last ? ',' : '' }}
                        @endforeach
                        );"
                    ></div>
                    <div class="stops-markers">
                        @foreach($value['stops'] as $index => $stop)
                            <div class="color-stop" 
                                data-index="{{ $index }}"
                                style="left: {{ $stop['position'] }}%;"
                            >
                                <div class="stop-handle" 
                                    style="background-color: {{ $stop['color'] }};"
                                ></div>
                                <input type="hidden"
                                    name="{{ $field['name'] }}[stops][{{ $index }}][color]"
                                    value="{{ $stop['color'] }}"
                                    class="stop-color"
                                >
                                <input type="hidden"
                                    name="{{ $field['name'] }}[stops][{{ $index }}][position]"
                                    value="{{ $stop['position'] }}"
                                    class="stop-position"
                                >
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="active-stop-editor" style="display: none;">
                    <div class="stop-color-picker">
                        <input type="text"
                            class="color-picker"
                            value=""
                            data-alpha="{{ !empty($field['show_alpha']) ? 'true' : 'false' }}"
                        >
                    </div>
                    <div class="stop-position-input">
                        <input type="number"
                            class="position-input"
                            min="0"
                            max="100"
                            step="1"
                        >
                        <span class="unit">%</span>
                    </div>
                    <button type="button" class="remove-stop" title="Remove color stop">
                        <span class="dashicons dashicons-no"></span>
                    </button>
                </div>
            </div>

            @if(empty($field['max_stops']) || count($value['stops']) < $field['max_stops'])
                <button type="button" class="button add-stop">
                    <span class="dashicons dashicons-plus"></span>
                    Add Color Stop
                </button>
            @endif
        </div>
    </div>

    @if(!empty($field['show_presets']))
        <div class="gradient-presets">
            <label>Presets</label>
            <div class="presets-grid">
                @foreach($field['presets'] ?? [] as $preset)
                    <button type="button"
                        class="preset-button"
                        data-preset="{{ json_encode($preset) }}"
                        title="{{ $preset['name'] }}"
                    >
                        <div class="preset-preview" style="
                            background: {{ $preset['type'] === 'linear' 
                                ? "linear-gradient({$preset['angle']}deg, " 
                                : "radial-gradient(at {$preset['radial_position']}, "
                            }}
                            @foreach($preset['stops'] as $stop)
                                {{ $stop['color'] }} {{ $stop['position'] }}%
                                {{ !$loop->last ? ',' : '' }}
                            @endforeach
                            );"
                        ></div>
                    </button>
                @endforeach
            </div>
        </div>
    @endif

    @if(!empty($field['show_css']))
        <div class="gradient-css">
            <label>CSS Code</label>
            <textarea class="css-output" readonly></textarea>
            <button type="button" class="button copy-css">
                <span class="dashicons dashicons-clipboard"></span>
                Copy CSS
            </button>
        </div>
    @endif
</div>