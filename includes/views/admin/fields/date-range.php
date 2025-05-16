<?php
/**
 * Date Range Field Template
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
    'start' => '',
    'end' => '',
    'start_time' => '',
    'end_time' => ''
]);

$show_time = !empty($field['show_time']);
$time_24hr = !empty($field['time_24hr']);
$show_weeks = !empty($field['show_weeks']);
$show_iso = !empty($field['show_iso']);
$disable_past = !empty($field['disable_past']);
$disable_future = !empty($field['disable_future']);
$min_date = $field['min_date'] ?? '';
$max_date = $field['max_date'] ?? '';
$min_days = $field['min_days'] ?? 0;
$max_days = $field['max_days'] ?? 0;
?>
<div class="mfw-date-range {{ $field['class'] ?? '' }}"
    data-format="{{ $field['date_format'] ?? 'Y-m-d' }}"
    data-time-format="{{ $field['time_format'] ?? ($time_24hr ? 'H:i' : 'h:i A') }}"
    data-show-time="{{ $show_time ? 'true' : 'false' }}"
    data-time-24hr="{{ $time_24hr ? 'true' : 'false' }}"
    data-show-weeks="{{ $show_weeks ? 'true' : 'false' }}"
    data-show-iso="{{ $show_iso ? 'true' : 'false' }}"
    data-disable-past="{{ $disable_past ? 'true' : 'false' }}"
    data-disable-future="{{ $disable_future ? 'true' : 'false' }}"
    data-min-date="{{ $min_date }}"
    data-max-date="{{ $max_date }}"
    data-min-days="{{ $min_days }}"
    data-max-days="{{ $max_days }}"
>
    <div class="date-inputs">
        <!-- Start Date -->
        <div class="date-group start-date">
            <label>{{ $field['start_label'] ?? 'Start Date' }}</label>
            <div class="input-wrapper">
                <input type="text"
                    class="date-input start-date-input"
                    name="{{ $field['name'] }}[start]"
                    value="{{ $value['start'] }}"
                    placeholder="{{ $field['start_placeholder'] ?? 'Select start date' }}"
                    @if(!empty($field['required']))
                        required
                    @endif
                    readonly
                >
                <span class="input-icon">
                    <span class="dashicons dashicons-calendar-alt"></span>
                </span>
                @if($show_time)
                    <input type="text"
                        class="time-input start-time-input"
                        name="{{ $field['name'] }}[start_time]"
                        value="{{ $value['start_time'] }}"
                        placeholder="{{ $time_24hr ? '00:00' : '12:00 AM' }}"
                        readonly
                    >
                    <span class="input-icon">
                        <span class="dashicons dashicons-clock"></span>
                    </span>
                @endif
            </div>
        </div>

        <!-- End Date -->
        <div class="date-group end-date">
            <label>{{ $field['end_label'] ?? 'End Date' }}</label>
            <div class="input-wrapper">
                <input type="text"
                    class="date-input end-date-input"
                    name="{{ $field['name'] }}[end]"
                    value="{{ $value['end'] }}"
                    placeholder="{{ $field['end_placeholder'] ?? 'Select end date' }}"
                    @if(!empty($field['required']))
                        required
                    @endif
                    readonly
                >
                <span class="input-icon">
                    <span class="dashicons dashicons-calendar-alt"></span>
                </span>
                @if($show_time)
                    <input type="text"
                        class="time-input end-time-input"
                        name="{{ $field['name'] }}[end_time]"
                        value="{{ $value['end_time'] }}"
                        placeholder="{{ $time_24hr ? '00:00' : '12:00 AM' }}"
                        readonly
                    >
                    <span class="input-icon">
                        <span class="dashicons dashicons-clock"></span>
                    </span>
                @endif
            </div>
        </div>
    </div>

    @if(!empty($field['show_presets']))
        <div class="date-presets">
            @foreach($field['presets'] ?? [] as $preset => $label)
                <button type="button" 
                    class="preset-button"
                    data-preset="{{ $preset }}"
                >
                    {{ $label }}
                </button>
            @endforeach
        </div>
    @endif

    <div class="range-info" style="display: none;">
        <div class="range-duration">
            Duration: <span class="duration-value"></span>
        </div>
        @if($show_weeks)
            <div class="range-weeks">
                Weeks: <span class="weeks-value"></span>
            </div>
        @endif
        @if($show_iso)
            <div class="range-iso">
                ISO: <span class="iso-value"></span>
            </div>
        @endif
    </div>

    @if(!empty($field['show_calendar']))
        <div class="inline-calendar"></div>
    @endif

    @if(!empty($field['show_clear']))
        <button type="button" class="clear-dates">
            <span class="dashicons dashicons-dismiss"></span>
            Clear Dates
        </button>
    @endif

    @if(!empty($field['description']))
        <div class="field-description">
            {{ $field['description'] }}
        </div>
    @endif

    @if(!empty($field['show_errors']))
        <div class="validation-errors" style="display: none;">
            @if($min_days)
                <div class="error min-days-error">
                    Minimum duration is {{ $min_days }} days
                </div>
            @endif
            @if($max_days)
                <div class="error max-days-error">
                    Maximum duration is {{ $max_days }} days
                </div>
            @endif
            <div class="error overlap-error">
                Selected dates overlap with existing range
            </div>
            <div class="error invalid-range-error">
                End date must be after start date
            </div>
        </div>
    @endif

    @if(!empty($field['excluded_dates']))
        <input type="hidden"
            class="excluded-dates"
            value="{{ json_encode($field['excluded_dates']) }}"
        >
    @endif

    @if(!empty($field['disabled_ranges']))
        <input type="hidden"
            class="disabled-ranges"
            value="{{ json_encode($field['disabled_ranges']) }}"
        >
    @endif

    <!-- Hidden inputs for additional data -->
    <input type="hidden"
        class="range-timestamp-start"
        name="{{ $field['name'] }}[timestamp_start]"
        value="{{ strtotime($value['start']) }}"
    >
    <input type="hidden"
        class="range-timestamp-end"
        name="{{ $field['name'] }}[timestamp_end]"
        value="{{ strtotime($value['end']) }}"
    >
    @if($show_iso)
        <input type="hidden"
            class="range-iso-start"
            name="{{ $field['name'] }}[iso_start]"
            value="{{ date('c', strtotime($value['start'])) }}"
        >
        <input type="hidden"
            class="range-iso-end"
            name="{{ $field['name'] }}[iso_end]"
            value="{{ date('c', strtotime($value['end'])) }}"
        >
    @endif
</div>