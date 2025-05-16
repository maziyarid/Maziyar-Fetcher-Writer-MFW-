<?php
/**
 * Social Media Field Template
 * 
 * @package MFW
 * @subpackage Views
 * @since 1.0.0
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

$networks = $field['networks'] ?? [
    'facebook' => [
        'label' => 'Facebook',
        'icon' => 'facebook',
        'pattern' => 'https?:\/\/(www\.)?facebook\.com\/.*'
    ],
    'twitter' => [
        'label' => 'Twitter',
        'icon' => 'twitter',
        'pattern' => 'https?:\/\/(www\.)?twitter\.com\/.*'
    ],
    'instagram' => [
        'label' => 'Instagram',
        'icon' => 'instagram',
        'pattern' => 'https?:\/\/(www\.)?instagram\.com\/.*'
    ],
    'linkedin' => [
        'label' => 'LinkedIn',
        'icon' => 'linkedin',
        'pattern' => 'https?:\/\/(www\.)?linkedin\.com\/.*'
    ],
    'youtube' => [
        'label' => 'YouTube',
        'icon' => 'youtube',
        'pattern' => 'https?:\/\/(www\.)?youtube\.com\/.*'
    ]
];

$value = is_array($value) ? $value : [];
?>
<div class="mfw-social-media-field {{ $field['class'] ?? '' }}">
    <div class="social-networks">
        @foreach($networks as $network_id => $network)
            <div class="social-network-item">
                <div class="network-header">
                    <div class="network-icon">
                        <span class="dashicons dashicons-{{ $network['icon'] }}"></span>
                    </div>
                    <div class="network-info">
                        <span class="network-label">{{ $network['label'] }}</span>
                        @if(!empty($value[$network_id]['url']))
                            <span class="network-url">{{ $value[$network_id]['url'] }}</span>
                        @endif
                    </div>
                    <div class="network-actions">
                        <button type="button" 
                            class="toggle-network"
                            title="{{ empty($value[$network_id]) ? 'Add profile' : 'Edit profile' }}"
                        >
                            <span class="dashicons dashicons-{{ empty($value[$network_id]) ? 'plus' : 'edit' }}"></span>
                        </button>
                    </div>
                </div>

                <div class="network-details" 
                    @if(empty($value[$network_id]))
                        style="display: none;"
                    @endif
                >
                    <div class="url-input-group">
                        <input type="url"
                            name="{{ $field['name'] }}[{{ $network_id }}][url]"
                            value="{{ $value[$network_id]['url'] ?? '' }}"
                            class="network-url-input"
                            placeholder="Enter {{ $network['label'] }} profile URL"
                            @if(!empty($network['pattern']))
                                pattern="{{ $network['pattern'] }}"
                            @endif
                        >
                        <button type="button" class="validate-url" title="Validate URL">
                            <span class="dashicons dashicons-yes-alt"></span>
                        </button>
                    </div>

                    @if(!empty($field['show_labels']))
                        <input type="text"
                            name="{{ $field['name'] }}[{{ $network_id }}][label]"
                            value="{{ $value[$network_id]['label'] ?? $network['label'] }}"
                            class="network-label-input"
                            placeholder="Display label"
                        >
                    @endif

                    @if(!empty($field['show_icons']))
                        <div class="icon-selector">
                            <label>Icon:</label>
                            <select name="{{ $field['name'] }}[{{ $network_id }}][icon]">
                                <option value="">Default</option>
                                <option value="color"
                                    @if(($value[$network_id]['icon'] ?? '') === 'color')
                                        selected
                                    @endif
                                >Color</option>
                                <option value="monochrome"
                                    @if(($value[$network_id]['icon'] ?? '') === 'monochrome')
                                        selected
                                    @endif
                                >Monochrome</option>
                                <option value="custom"
                                    @if(($value[$network_id]['icon'] ?? '') === 'custom')
                                        selected
                                    @endif
                                >Custom</option>
                            </select>
                        </div>
                    @endif

                    @if(!empty($field['show_advanced']))
                        <div class="advanced-settings">
                            <button type="button" class="toggle-advanced">
                                <span class="dashicons dashicons-arrow-down-alt2"></span>
                                Advanced Settings
                            </button>
                            <div class="advanced-options" style="display: none;">
                                <label>
                                    <input type="checkbox"
                                        name="{{ $field['name'] }}[{{ $network_id }}][new_tab]"
                                        value="1"
                                        @if(!empty($value[$network_id]['new_tab']))
                                            checked
                                        @endif
                                    >
                                    Open in new tab
                                </label>
                                <label>
                                    <input type="checkbox"
                                        name="{{ $field['name'] }}[{{ $network_id }}][nofollow]"
                                        value="1"
                                        @if(!empty($value[$network_id]['nofollow']))
                                            checked
                                        @endif
                                    >
                                    Add nofollow
                                </label>
                                <input type="text"
                                    name="{{ $field['name'] }}[{{ $network_id }}][class]"
                                    value="{{ $value[$network_id]['class'] ?? '' }}"
                                    placeholder="Custom CSS class"
                                >
                            </div>
                        </div>
                    @endif

                    <div class="network-footer">
                        <button type="button" class="button remove-network">
                            <span class="dashicons dashicons-trash"></span>
                            Remove
                        </button>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    @if(!empty($field['allow_custom']))
        <div class="add-custom-network">
            <button type="button" class="button add-network">
                <span class="dashicons dashicons-plus"></span>
                Add Custom Network
            </button>
        </div>

        <!-- Custom Network Template -->
        <script type="text/template" class="custom-network-template">
            <div class="social-network-item custom-network">
                <div class="network-header">
                    <div class="network-icon">
                        <span class="dashicons dashicons-share"></span>
                    </div>
                    <div class="network-info">
                        <input type="text"
                            name="{{ $field['name'] }}[custom_{{index}}][name]"
                            class="custom-network-name"
                            placeholder="Network Name"
                        >
                    </div>
                    <div class="network-actions">
                        <button type="button" class="remove-custom-network">
                            <span class="dashicons dashicons-no"></span>
                        </button>
                    </div>
                </div>

                <div class="network-details">
                    <div class="url-input-group">
                        <input type="url"
                            name="{{ $field['name'] }}[custom_{{index}}][url]"
                            class="network-url-input"
                            placeholder="Enter profile URL"
                        >
                    </div>

                    <div class="icon-selector">
                        <label>Icon:</label>
                        <select name="{{ $field['name'] }}[custom_{{index}}][icon]">
                            <option value="share">Share</option>
                            <option value="admin-site">Website</option>
                            <option value="admin-links">Link</option>
                            <option value="custom">Custom</option>
                        </select>
                    </div>

                    @if(!empty($field['show_advanced']))
                        <div class="advanced-settings">
                            <button type="button" class="toggle-advanced">
                                <span class="dashicons dashicons-arrow-down-alt2"></span>
                                Advanced Settings
                            </button>
                            <div class="advanced-options" style="display: none;">
                                <label>
                                    <input type="checkbox"
                                        name="{{ $field['name'] }}[custom_{{index}}][new_tab]"
                                        value="1"
                                    >
                                    Open in new tab
                                </label>
                                <label>
                                    <input type="checkbox"
                                        name="{{ $field['name'] }}[custom_{{index}}][nofollow]"
                                        value="1"
                                    >
                                    Add nofollow
                                </label>
                                <input type="text"
                                    name="{{ $field['name'] }}[custom_{{index}}][class]"
                                    placeholder="Custom CSS class"
                                >
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </script>
    @endif

    @if(!empty($field['show_sort']))
        <div class="sort-networks">
            <button type="button" class="button toggle-sort">
                <span class="dashicons dashicons-sort"></span>
                Sort Networks
            </button>
        </div>
    @endif
</div>