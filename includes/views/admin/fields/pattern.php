<?php
/**
 * Pattern Field Template
 * Allows users to create and customize repeatable patterns
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
    'pattern_type' => 'custom',
    'pattern' => '',
    'replacements' => [],
    'custom_css' => '',
    'responsive_rules' => []
]);

$pattern_types = [
    'custom' => 'Custom Pattern',
    'grid' => 'Grid Pattern',
    'stripe' => 'Stripe Pattern',
    'dots' => 'Dots Pattern',
    'waves' => 'Waves Pattern',
    'geometric' => 'Geometric Pattern'
];
?>
<div class="mfw-pattern-field {{ $field['class'] ?? '' }}">
    <!-- Pattern Type Selector -->
    <div class="pattern-type-selector">
        <label>Pattern Type</label>
        <select name="{{ $field['name'] }}[pattern_type]" class="pattern-type">
            @foreach($pattern_types as $type => $label)
                <option value="{{ $type }}"
                    @if($value['pattern_type'] === $type)
                        selected
                    @endif
                >{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <!-- Pattern Preview -->
    <div class="pattern-preview">
        <div class="preview-box">
            <div class="pattern-display"></div>
        </div>
        <div class="preview-controls">
            <button type="button" class="button refresh-preview">
                <span class="dashicons dashicons-update"></span>
                Refresh Preview
            </button>
            @if(!empty($field['show_export']))
                <button type="button" class="button export-pattern">
                    <span class="dashicons dashicons-download"></span>
                    Export Pattern
                </button>
            @endif
        </div>
    </div>

    <!-- Pattern Editor -->
    <div class="pattern-editor">
        <!-- Custom Pattern -->
        <div class="pattern-section custom-pattern"
            @if($value['pattern_type'] !== 'custom')
                style="display: none;"
            @endif
        >
            <textarea
                name="{{ $field['name'] }}[pattern]"
                class="pattern-code"
                placeholder="Enter custom pattern code"
            >{{ $value['pattern'] }}</textarea>
        </div>

        <!-- Grid Pattern -->
        <div class="pattern-section grid-pattern"
            @if($value['pattern_type'] !== 'grid')
                style="display: none;"
            @endif
        >
            <div class="grid-controls">
                <div class="control-group">
                    <label>Columns</label>
                    <input type="number"
                        name="{{ $field['name'] }}[grid][columns]"
                        value="{{ $value['grid']['columns'] ?? 3 }}"
                        min="1"
                        max="12"
                    >
                </div>
                <div class="control-group">
                    <label>Gap</label>
                    <input type="number"
                        name="{{ $field['name'] }}[grid][gap]"
                        value="{{ $value['grid']['gap'] ?? 20 }}"
                        min="0"
                    >
                    <select name="{{ $field['name'] }}[grid][gap_unit]">
                        @foreach(['px', 'em', 'rem', '%'] as $unit)
                            <option value="{{ $unit }}"
                                @if(($value['grid']['gap_unit'] ?? 'px') === $unit)
                                    selected
                                @endif
                            >{{ $unit }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <!-- Pattern Variables -->
        <div class="pattern-variables">
            <label>Pattern Variables</label>
            <div class="variables-list">
                @foreach($value['replacements'] as $key => $replacement)
                    <div class="variable-item">
                        <input type="text"
                            name="{{ $field['name'] }}[replacements][{{ $key }}][key]"
                            value="{{ $replacement['key'] }}"
                            placeholder="Variable name"
                            class="variable-key"
                        >
                        <input type="text"
                            name="{{ $field['name'] }}[replacements][{{ $key }}][value]"
                            value="{{ $replacement['value'] }}"
                            placeholder="Value"
                            class="variable-value"
                        >
                        <button type="button" class="remove-variable">
                            <span class="dashicons dashicons-no"></span>
                        </button>
                    </div>
                @endforeach
            </div>
            <button type="button" class="button add-variable">
                <span class="dashicons dashicons-plus"></span>
                Add Variable
            </button>
        </div>

        <!-- Custom CSS -->
        @if(!empty($field['show_custom_css']))
            <div class="custom-css-section">
                <label>Custom CSS</label>
                <textarea
                    name="{{ $field['name'] }}[custom_css]"
                    class="custom-css"
                    placeholder="Enter custom CSS"
                >{{ $value['custom_css'] }}</textarea>
            </div>
        @endif

        <!-- Responsive Rules -->
        @if(!empty($field['show_responsive']))
            <div class="responsive-rules">
                <label>Responsive Rules</label>
                <div class="breakpoints-list">
                    @foreach(['desktop', 'tablet', 'mobile'] as $device)
                        <div class="breakpoint-item">
                            <div class="breakpoint-header">
                                <span class="device-icon">
                                    <span class="dashicons dashicons-{{ $device }}"></span>
                                </span>
                                <span class="device-label">{{ ucfirst($device) }}</span>
                            </div>
                            <textarea
                                name="{{ $field['name'] }}[responsive_rules][{{ $device }}]"
                                placeholder="Enter CSS for {{ $device }}"
                            >{{ $value['responsive_rules'][$device] ?? '' }}</textarea>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</div>