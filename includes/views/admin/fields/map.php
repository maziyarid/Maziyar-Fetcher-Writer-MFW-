<?php
/**
 * Map Field Template
 * Advanced map field with location search, geocoding, and multi-marker support
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
    'center' => [
        'lat' => $field['default_lat'] ?? 0,
        'lng' => $field['default_lng'] ?? 0
    ],
    'zoom' => $field['default_zoom'] ?? 12,
    'markers' => []
]);

$map_provider = $field['provider'] ?? 'google';
$enable_drawing = !empty($field['enable_drawing']);
$enable_clusters = !empty($field['enable_clusters']);
$multiple_markers = !empty($field['multiple_markers']);
?>
<div class="mfw-map-field {{ $field['class'] ?? '' }}"
    data-provider="{{ $map_provider }}"
    data-multiple="{{ $multiple_markers ? 'true' : 'false' }}"
    data-drawing="{{ $enable_drawing ? 'true' : 'false' }}"
    data-clusters="{{ $enable_clusters ? 'true' : 'false' }}"
>
    <!-- Map Controls -->
    <div class="map-controls">
        <div class="search-wrapper">
            <input type="text"
                class="location-search"
                placeholder="Search location..."
            >
            <button type="button" class="search-location">
                <span class="dashicons dashicons-search"></span>
            </button>
        </div>

        <div class="control-buttons">
            @if(!empty($field['enable_current_location']))
                <button type="button" class="current-location" title="Get Current Location">
                    <span class="dashicons dashicons-location"></span>
                </button>
            @endif

            @if($enable_drawing)
                <div class="drawing-tools">
                    <button type="button" class="toggle-drawing" title="Toggle Drawing Tools">
                        <span class="dashicons dashicons-edit"></span>
                    </button>
                    <div class="drawing-options" style="display: none;">
                        <button type="button" class="draw-marker active" data-tool="marker">
                            <span class="dashicons dashicons-location"></span>
                            Marker
                        </button>
                        <button type="button" class="draw-polygon" data-tool="polygon">
                            <span class="dashicons dashicons-forms"></span>
                            Polygon
                        </button>
                        <button type="button" class="draw-circle" data-tool="circle">
                            <span class="dashicons dashicons-marker"></span>
                            Circle
                        </button>
                    </div>
                </div>
            @endif

            <button type="button" class="reset-map" title="Reset Map">
                <span class="dashicons dashicons-image-rotate"></span>
            </button>
        </div>
    </div>

    <!-- Map Container -->
    <div class="map-container">
        <div id="{{ $field['id'] }}_map" class="map-canvas"></div>

        <!-- Loading Overlay -->
        <div class="map-loading" style="display: none;">
            <div class="loading-spinner"></div>
            <div class="loading-text">Loading map...</div>
        </div>

        <!-- Map Attribution -->
        <div class="map-attribution">
            @switch($map_provider)
                @case('google')
                    <span>Powered by Google Maps</span>
                    @break
                @case('mapbox')
                    <span>© Mapbox © OpenStreetMap</span>
                    @break
                @default
                    <span>© OpenStreetMap contributors</span>
            @endswitch
        </div>
    </div>

    <!-- Location Details -->
    <div class="location-details">
        @if($multiple_markers)
            <!-- Markers List -->
            <div class="markers-list">
                <div class="list-header">
                    <h4>Locations</h4>
                    <button type="button" class="add-marker" title="Add Location">
                        <span class="dashicons dashicons-plus"></span>
                    </button>
                </div>
                <div class="markers-container">
                    @foreach($value['markers'] as $index => $marker)
                        <div class="marker-item" data-index="{{ $index }}">
                            <div class="marker-header">
                                <span class="marker-icon">
                                    <span class="dashicons dashicons-location"></span>
                                </span>
                                <input type="text"
                                    class="marker-title"
                                    value="{{ $marker['title'] ?? '' }}"
                                    placeholder="Location name"
                                >
                                <div class="marker-actions">
                                    <button type="button" class="edit-marker" title="Edit">
                                        <span class="dashicons dashicons-edit"></span>
                                    </button>
                                    <button type="button" class="remove-marker" title="Remove">
                                        <span class="dashicons dashicons-trash"></span>
                                    </button>
                                </div>
                            </div>
                            <div class="marker-content" style="display: none;">
                                <div class="coordinate-group">
                                    <input type="number"
                                        class="marker-lat"
                                        value="{{ $marker['lat'] }}"
                                        step="any"
                                        placeholder="Latitude"
                                    >
                                    <input type="number"
                                        class="marker-lng"
                                        value="{{ $marker['lng'] }}"
                                        step="any"
                                        placeholder="Longitude"
                                    >
                                </div>
                                @if(!empty($field['enable_marker_content']))
                                    <textarea
                                        class="marker-description"
                                        placeholder="Location description"
                                    >{{ $marker['description'] ?? '' }}</textarea>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @else
            <!-- Single Location Details -->
            <div class="single-location">
                <div class="coordinate-display">
                    <div class="coordinate-group">
                        <label>Latitude</label>
                        <input type="number"
                            class="location-lat"
                            value="{{ $value['center']['lat'] }}"
                            step="any"
                        >
                    </div>
                    <div class="coordinate-group">
                        <label>Longitude</label>
                        <input type="number"
                            class="location-lng"
                            value="{{ $value['center']['lng'] }}"
                            step="any"
                        >
                    </div>
                </div>
                @if(!empty($field['show_address']))
                    <div class="address-display">
                        <textarea
                            class="location-address"
                            placeholder="Address"
                            readonly
                        >{{ $value['address'] ?? '' }}</textarea>
                    </div>
                @endif
            </div>
        @endif
    </div>

    <!-- Map Settings -->
    @if(!empty($field['show_settings']))
        <div class="map-settings">
            <button type="button" class="toggle-settings">
                <span class="dashicons dashicons-admin-generic"></span>
                Map Settings
            </button>
            <div class="settings-panel" style="display: none;">
                <div class="setting-group">
                    <label>Map Type</label>
                    <select class="map-type">
                        <option value="roadmap">Roadmap</option>
                        <option value="satellite">Satellite</option>
                        <option value="hybrid">Hybrid</option>
                        <option value="terrain">Terrain</option>
                    </select>
                </div>
                <div class="setting-group">
                    <label>Zoom Level</label>
                    <input type="range"
                        class="zoom-level"
                        min="1"
                        max="20"
                        value="{{ $value['zoom'] }}"
                    >
                </div>
                @if($enable_clusters)
                    <div class="setting-group">
                        <label>
                            <input type="checkbox"
                                class="enable-clustering"
                                @if(!empty($value['clustering']))
                                    checked
                                @endif
                            >
                            Enable Clustering
                        </label>
                    </div>
                @endif
            </div>
        </div>
    @endif

    <!-- Hidden Input -->
    <input type="hidden"
        name="{{ $field['name'] }}"
        value="{{ json_encode($value) }}"
        class="map-value"
    >
</div>