<?php
/**
 * Time Zone Field Template
 * Advanced timezone selector with search, map visualization and local time display
 * 
 * @package MFW
 * @subpackage Views
 * @since 1.0.0
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

$value = $value ?: wp_timezone_string();
$regions = [
    'Africa' => DateTimeZone::AFRICA,
    'America' => DateTimeZone::AMERICA,
    'Antarctica' => DateTimeZone::ANTARCTICA,
    'Arctic' => DateTimeZone::ARCTIC,
    'Asia' => DateTimeZone::ASIA,
    'Atlantic' => DateTimeZone::ATLANTIC,
    'Australia' => DateTimeZone::AUSTRALIA,
    'Europe' => DateTimeZone::EUROPE,
    'Indian' => DateTimeZone::INDIAN,
    'Pacific' => DateTimeZone::PACIFIC
];

$current_time = new DateTime('now', new DateTimeZone($value));
$utc_offset = $current_time->format('P');
?>
<div class="mfw-timezone-field {{ $field['class'] ?? '' }}"
    data-current-zone="{{ $value }}"
>
    <!-- Timezone Display -->
    <div class="timezone-display">
        <div class="current-time">
            <span class="time">{{ $current_time->format('H:i:s') }}</span>
            <span class="date">{{ $current_time->format('Y-m-d') }}</span>
            <span class="timezone">{{ $value }}</span>
            <span class="utc-offset">(UTC {{ $utc_offset }})</span>
        </div>
    </div>

    <!-- Search and Select Interface -->
    <div class="timezone-selector">
        @if(!empty($field['show_search']))
            <div class="search-wrapper">
                <input type="text"
                    class="timezone-search"
                    placeholder="Search timezone..."
                >
                <span class="dashicons dashicons-search"></span>
            </div>
        @endif

        <div class="selection-interface">
            @if(!empty($field['show_map']))
                <!-- Interactive Map -->
                <div class="timezone-map">
                    <div class="map-container"></div>
                    <div class="map-overlay">
                        <div class="current-selection">
                            <span class="selection-marker"></span>
                            <span class="selection-info"></span>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Region-based Selection -->
            <div class="timezone-regions">
                @foreach($regions as $region => $mask)
                    <div class="region-group">
                        <div class="region-header">
                            <span class="region-name">{{ $region }}</span>
                            <button type="button" class="toggle-region">
                                <span class="dashicons dashicons-arrow-down-alt2"></span>
                            </button>
                        </div>
                        <div class="region-zones">
                            <?php $zones = DateTimeZone::listIdentifiers($mask); ?>
                            @foreach($zones as $zone)
                                <?php 
                                $zone_time = new DateTime('now', new DateTimeZone($zone));
                                $zone_offset = $zone_time->format('P');
                                ?>
                                <div class="zone-item {{ $value === $zone ? 'active' : '' }}"
                                    data-zone="{{ $zone }}"
                                    data-offset="{{ $zone_offset }}"
                                >
                                    <div class="zone-info">
                                        <span class="zone-name">
                                            {{ str_replace(['_', '/'], [' ', ' / '], $zone) }}
                                        </span>
                                        <span class="zone-time">
                                            {{ $zone_time->format('H:i') }}
                                        </span>
                                    </div>
                                    <span class="zone-offset">
                                        UTC {{ $zone_offset }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <!-- Quick Options -->
    @if(!empty($field['show_quick_select']))
        <div class="quick-select">
            <button type="button" class="quick-option" data-zone="UTC">
                UTC
            </button>
            <button type="button" class="quick-option" data-zone="local">
                Browser Time
            </button>
            <button type="button" class="quick-option" data-zone="site">
                Site Default
            </button>
        </div>
    @endif

    <!-- Format Preview -->
    @if(!empty($field['show_format_preview']))
        <div class="format-preview">
            <label>Preview:</label>
            <div class="preview-list">
                @foreach([
                    'full' => 'l, F j, Y g:i:s A T',
                    'long' => 'F j, Y g:i A',
                    'medium' => 'M j, Y g:i A',
                    'short' => 'Y-m-d H:i'
                ] as $format_key => $format)
                    <div class="preview-item">
                        <span class="format-label">{{ ucfirst($format_key) }}:</span>
                        <span class="format-output" data-format="{{ $format }}">
                            {{ $current_time->format($format) }}
                        </span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- Advanced Options -->
    @if(!empty($field['show_advanced']))
        <div class="advanced-options">
            <button type="button" class="toggle-advanced">
                <span class="dashicons dashicons-admin-generic"></span>
                Advanced Options
            </button>
            <div class="advanced-panel" style="display: none;">
                <div class="option-group">
                    <label>
                        <input type="checkbox"
                            name="{{ $field['name'] }}[dst]"
                            @if(!empty($value['dst']))
                                checked
                            @endif
                        >
                        Observe Daylight Saving Time
                    </label>
                </div>
                <div class="option-group">
                    <label>
                        <input type="checkbox"
                            name="{{ $field['name'] }}[auto_update]"
                            @if(!empty($value['auto_update']))
                                checked
                            @endif
                        >
                        Automatically update time
                    </label>
                </div>
                <div class="option-group">
                    <label>Default Format:</label>
                    <select name="{{ $field['name'] }}[format]">
                        <option value="site">Site Default</option>
                        <option value="custom">Custom</option>
                    </select>
                </div>
            </div>
        </div>
    @endif

    <!-- Hidden Input -->
    <input type="hidden"
        name="{{ $field['name'] }}"
        value="{{ $value }}"
        class="timezone-value"
    >
</div>

<!-- Templates -->
<script type="text/template" id="zone-info-template">
    <div class="zone-info-popup">
        <div class="info-header">
            <h4>{{ zone_name }}</h4>
            <span class="current-time">{{ current_time }}</span>
        </div>
        <div class="info-details">
            <div class="detail-item">
                <span class="label">UTC Offset:</span>
                <span class="value">{{ utc_offset }}</span>
            </div>
            <div class="detail-item">
                <span class="label">DST:</span>
                <span class="value">{{ dst_status }}</span>
            </div>
            <div class="detail-item">
                <span class="label">Region:</span>
                <span class="value">{{ region }}</span>
            </div>
        </div>
    </div>
</script>