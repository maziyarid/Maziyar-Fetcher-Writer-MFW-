<?php
/**
 * Typography Field Template
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
    'font_family' => '',
    'font_variant' => 'regular',
    'font_size' => '',
    'font_size_unit' => 'px',
    'line_height' => '',
    'line_height_unit' => '',
    'letter_spacing' => '',
    'letter_spacing_unit' => 'px',
    'text_align' => '',
    'text_transform' => '',
    'text_decoration' => [],
    'font_style' => 'normal',
    'color' => '',
    'backup_font' => '',
    'load_variants' => []
]);

$units = [
    'px' => 'px',
    'em' => 'em',
    'rem' => 'rem',
    '%' => '%',
    'vw' => 'vw'
];
?>
<div class="mfw-typography-field {{ $field['class'] ?? '' }}"
    data-font-api="{{ $field['font_api'] ?? 'google' }}"
    data-api-key="{{ $field['api_key'] ?? '' }}"
>
    <div class="typography-preview">
        <div class="preview-text" style="
            @if($value['font_family'])
                font-family: '{{ $value['font_family'] }}', {{ $value['backup_font'] ?: 'sans-serif' }};
            @endif
            @if($value['font_size'])
                font-size: {{ $value['font_size'] . $value['font_size_unit'] }};
            @endif
            @if($value['line_height'])
                line-height: {{ $value['line_height'] . $value['line_height_unit'] }};
            @endif
            @if($value['letter_spacing'])
                letter-spacing: {{ $value['letter_spacing'] . $value['letter_spacing_unit'] }};
            @endif
            @if($value['text_align'])
                text-align: {{ $value['text_align'] }};
            @endif
            @if($value['text_transform'])
                text-transform: {{ $value['text_transform'] }};
            @endif
            @if($value['font_style'])
                font-style: {{ $value['font_style'] }};
            @endif
            @if($value['color'])
                color: {{ $value['color'] }};
            @endif
            @if(!empty($value['text_decoration']))
                text-decoration: {{ implode(' ', $value['text_decoration']) }};
            @endif
        ">
            {{ $field['preview_text'] ?? 'The quick brown fox jumps over the lazy dog' }}
        </div>
    </div>

    <div class="typography-controls">
        <!-- Font Family -->
        <div class="control-group font-family-group">
            <label>Font Family</label>
            <select name="{{ $field['name'] }}[font_family]" class="font-family-select">
                <option value="">Default</option>
                <!-- Font options will be populated via JS -->
            </select>

            @if(!empty($field['show_backup_font']))
                <input type="text"
                    name="{{ $field['name'] }}[backup_font]"
                    value="{{ $value['backup_font'] }}"
                    placeholder="Backup font family"
                    class="backup-font"
                >
            @endif
        </div>

        <!-- Font Variant -->
        <div class="control-group font-variant-group">
            <label>Font Weight & Style</label>
            <select name="{{ $field['name'] }}[font_variant]" class="font-variant-select">
                <option value="regular">Regular</option>
                <!-- Variants will be populated based on selected font -->
            </select>

            @if(!empty($field['show_load_variants']))
                <div class="load-variants">
                    <label>Load Variants:</label>
                    <div class="variant-checkboxes">
                        <!-- Variant checkboxes will be populated via JS -->
                    </div>
                </div>
            @endif
        </div>

        <!-- Font Size -->
        <div class="control-group font-size-group">
            <label>Font Size</label>
            <div class="size-input-group">
                <input type="number"
                    name="{{ $field['name'] }}[font_size]"
                    value="{{ $value['font_size'] }}"
                    class="font-size-input"
                    step="any"
                >
                <select name="{{ $field['name'] }}[font_size_unit]" class="unit-select">
                    @foreach($units as $unit => $label)
                        <option value="{{ $unit }}"
                            @if($value['font_size_unit'] === $unit)
                                selected
                            @endif
                        >{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <!-- Line Height -->
        <div class="control-group line-height-group">
            <label>Line Height</label>
            <div class="size-input-group">
                <input type="number"
                    name="{{ $field['name'] }}[line_height]"
                    value="{{ $value['line_height'] }}"
                    class="line-height-input"
                    step="any"
                >
                <select name="{{ $field['name'] }}[line_height_unit]" class="unit-select">
                    <option value="">Unit-less</option>
                    @foreach($units as $unit => $label)
                        <option value="{{ $unit }}"
                            @if($value['line_height_unit'] === $unit)
                                selected
                            @endif
                        >{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <!-- Letter Spacing -->
        <div class="control-group letter-spacing-group">
            <label>Letter Spacing</label>
            <div class="size-input-group">
                <input type="number"
                    name="{{ $field['name'] }}[letter_spacing]"
                    value="{{ $value['letter_spacing'] }}"
                    class="letter-spacing-input"
                    step="any"
                >
                <select name="{{ $field['name'] }}[letter_spacing_unit]" class="unit-select">
                    @foreach($units as $unit => $label)
                        <option value="{{ $unit }}"
                            @if($value['letter_spacing_unit'] === $unit)
                                selected
                            @endif
                        >{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <!-- Text Align -->
        <div class="control-group text-align-group">
            <label>Text Alignment</label>
            <div class="button-group">
                @foreach(['left', 'center', 'right', 'justify'] as $align)
                    <button type="button" 
                        class="align-button {{ $value['text_align'] === $align ? 'active' : '' }}"
                        data-align="{{ $align }}"
                        title="{{ ucfirst($align) }}"
                    >
                        <span class="dashicons dashicons-align-{{ $align }}"></span>
                    </button>
                @endforeach
            </div>
            <input type="hidden"
                name="{{ $field['name'] }}[text_align]"
                value="{{ $value['text_align'] }}"
            >
        </div>

        <!-- Text Transform -->
        <div class="control-group text-transform-group">
            <label>Text Transform</label>
            <select name="{{ $field['name'] }}[text_transform]">
                <option value="">None</option>
                @foreach(['capitalize', 'uppercase', 'lowercase'] as $transform)
                    <option value="{{ $transform }}"
                        @if($value['text_transform'] === $transform)
                            selected
                        @endif
                    >{{ ucfirst($transform) }}</option>
                @endforeach
            </select>
        </div>

        <!-- Text Decoration -->
        <div class="control-group text-decoration-group">
            <label>Text Decoration</label>
            <div class="checkbox-group">
                @foreach(['underline', 'overline', 'line-through'] as $decoration)
                    <label>
                        <input type="checkbox"
                            name="{{ $field['name'] }}[text_decoration][]"
                            value="{{ $decoration }}"
                            @if(in_array($decoration, $value['text_decoration']))
                                checked
                            @endif
                        >
                        {{ ucfirst(str_replace('-', ' ', $decoration)) }}
                    </label>
                @endforeach
            </div>
        </div>

        <!-- Font Style -->
        <div class="control-group font-style-group">
            <label>Font Style</label>
            <select name="{{ $field['name'] }}[font_style]">
                @foreach(['normal', 'italic', 'oblique'] as $style)
                    <option value="{{ $style }}"
                        @if($value['font_style'] === $style)
                            selected
                        @endif
                    >{{ ucfirst($style) }}</option>
                @endforeach
            </select>
        </div>

        <!-- Font Color -->
        @if(!empty($field['show_color']))
            <div class="control-group color-group">
                <label>Color</label>
                <input type="text"
                    name="{{ $field['name'] }}[color]"
                    value="{{ $value['color'] }}"
                    class="color-picker"
                    data-alpha="{{ !empty($field['show_alpha']) ? 'true' : 'false' }}"
                >
            </div>
        @endif
    </div>

    @if(!empty($field['show_reset']))
        <div class="typography-footer">
            <button type="button" class="button reset-typography">
                <span class="dashicons dashicons-image-rotate"></span>
                Reset to Default
            </button>
        </div>
    @endif
</div>