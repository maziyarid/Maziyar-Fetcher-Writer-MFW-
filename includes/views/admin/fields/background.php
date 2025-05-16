<?php
/**
 * Background Field Template
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
    'type' => 'color',
    'color' => '',
    'gradient' => [
        'type' => 'linear',
        'angle' => '90',
        'colors' => []
    ],
    'image' => [
        'id' => '',
        'url' => '',
        'size' => 'cover',
        'repeat' => 'no-repeat',
        'position' => 'center center',
        'attachment' => 'scroll'
    ],
    'video' => [
        'id' => '',
        'url' => '',
        'fallback_id' => '',
        'fallback_url' => '',
        'loop' => true,
        'mute' => true,
        'playback_speed' => 1
    ]
]);
?>
<div class="mfw-background-field {{ $field['class'] ?? '' }}">
    <div class="background-type-selector">
        <select name="{{ $field['name'] }}[type]" class="background-type">
            @foreach($field['types'] ?? ['color', 'gradient', 'image', 'video'] as $type)
                <option value="{{ $type }}"
                    @if($value['type'] === $type)
                        selected
                    @endif
                >
                    {{ ucfirst($type) }}
                </option>
            @endforeach
        </select>
    </div>

    <!-- Color Background -->
    <div class="background-section color-section" 
        @if($value['type'] !== 'color')
            style="display: none;"
        @endif
    >
        <div class="color-picker-wrapper">
            <input type="text"
                class="color-picker"
                name="{{ $field['name'] }}[color]"
                value="{{ $value['color'] }}"
                data-alpha="{{ !empty($field['show_alpha']) ? 'true' : 'false' }}"
            >
        </div>
    </div>

    <!-- Gradient Background -->
    <div class="background-section gradient-section"
        @if($value['type'] !== 'gradient')
            style="display: none;"
        @endif
    >
        <div class="gradient-type">
            <select name="{{ $field['name'] }}[gradient][type]">
                <option value="linear"
                    @if($value['gradient']['type'] === 'linear')
                        selected
                    @endif
                >Linear</option>
                <option value="radial"
                    @if($value['gradient']['type'] === 'radial')
                        selected
                    @endif
                >Radial</option>
            </select>
        </div>

        <div class="gradient-angle"
            @if($value['gradient']['type'] !== 'linear')
                style="display: none;"
            @endif
        >
            <label>Angle</label>
            <input type="number"
                name="{{ $field['name'] }}[gradient][angle]"
                value="{{ $value['gradient']['angle'] }}"
                min="0"
                max="360"
                step="1"
            >
        </div>

        <div class="gradient-colors">
            <div class="color-stops">
                @foreach($value['gradient']['colors'] as $index => $stop)
                    <div class="color-stop" data-position="{{ $stop['position'] }}">
                        <input type="text"
                            class="color-picker"
                            name="{{ $field['name'] }}[gradient][colors][{{ $index }}][color]"
                            value="{{ $stop['color'] }}"
                            data-alpha="true"
                        >
                        <input type="number"
                            class="stop-position"
                            name="{{ $field['name'] }}[gradient][colors][{{ $index }}][position]"
                            value="{{ $stop['position'] }}"
                            min="0"
                            max="100"
                            step="1"
                        >
                        <button type="button" class="remove-stop">
                            <span class="dashicons dashicons-no"></span>
                        </button>
                    </div>
                @endforeach
            </div>
            <button type="button" class="button add-color-stop">
                Add Color Stop
            </button>
        </div>

        <div class="gradient-preview"></div>
    </div>

    <!-- Image Background -->
    <div class="background-section image-section"
        @if($value['type'] !== 'image')
            style="display: none;"
        @endif
    >
        <div class="image-preview">
            @if(!empty($value['image']['id']))
                <?php $image = wp_get_attachment_image_src($value['image']['id'], 'medium'); ?>
                @if($image)
                    <img src="{{ $image[0] }}" alt="Background preview">
                @endif
            @endif
        </div>

        <div class="image-controls">
            <button type="button" class="button select-image">
                {{ empty($value['image']['id']) ? 'Select Image' : 'Change Image' }}
            </button>
            @if(!empty($value['image']['id']))
                <button type="button" class="button remove-image">
                    Remove Image
                </button>
            @endif
        </div>

        <input type="hidden"
            name="{{ $field['name'] }}[image][id]"
            value="{{ $value['image']['id'] }}"
        >
        <input type="hidden"
            name="{{ $field['name'] }}[image][url]"
            value="{{ $value['image']['url'] }}"
        >

        <div class="image-settings">
            <div class="setting-group">
                <label>Size</label>
                <select name="{{ $field['name'] }}[image][size]">
                    @foreach(['auto', 'cover', 'contain'] as $size)
                        <option value="{{ $size }}"
                            @if($value['image']['size'] === $size)
                                selected
                            @endif
                        >{{ ucfirst($size) }}</option>
                    @endforeach
                </select>
            </div>

            <div class="setting-group">
                <label>Repeat</label>
                <select name="{{ $field['name'] }}[image][repeat]">
                    @foreach(['no-repeat', 'repeat', 'repeat-x', 'repeat-y'] as $repeat)
                        <option value="{{ $repeat }}"
                            @if($value['image']['repeat'] === $repeat)
                                selected
                            @endif
                        >{{ ucfirst(str_replace('-', ' ', $repeat)) }}</option>
                    @endforeach
                </select>
            </div>

            <div class="setting-group">
                <label>Position</label>
                <select name="{{ $field['name'] }}[image][position]">
                    @foreach([
                        'left top', 'center top', 'right top',
                        'left center', 'center center', 'right center',
                        'left bottom', 'center bottom', 'right bottom'
                    ] as $position)
                        <option value="{{ $position }}"
                            @if($value['image']['position'] === $position)
                                selected
                            @endif
                        >{{ ucwords($position) }}</option>
                    @endforeach
                </select>
            </div>

            <div class="setting-group">
                <label>Attachment</label>
                <select name="{{ $field['name'] }}[image][attachment]">
                    @foreach(['scroll', 'fixed'] as $attachment)
                        <option value="{{ $attachment }}"
                            @if($value['image']['attachment'] === $attachment)
                                selected
                            @endif
                        >{{ ucfirst($attachment) }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <!-- Video Background -->
    <div class="background-section video-section"
        @if($value['type'] !== 'video')
            style="display: none;"
        @endif
    >
        <div class="video-preview">
            @if(!empty($value['video']['url']))
                <video
                    src="{{ $value['video']['url'] }}"
                    @if($value['video']['loop'])
                        loop
                    @endif
                    @if($value['video']['mute'])
                        muted
                    @endif
                    playsinline
                >
                    @if(!empty($value['video']['fallback_url']))
                        <source src="{{ $value['video']['fallback_url'] }}" type="video/mp4">
                    @endif
                </video>
            @endif
        </div>

        <div class="video-controls">
            <button type="button" class="button select-video">
                {{ empty($value['video']['id']) ? 'Select Video' : 'Change Video' }}
            </button>
            @if(!empty($value['video']['id']))
                <button type="button" class="button remove-video">
                    Remove Video
                </button>
            @endif
        </div>

        <input type="hidden"
            name="{{ $field['name'] }}[video][id]"
            value="{{ $value['video']['id'] }}"
        >
        <input type="hidden"
            name="{{ $field['name'] }}[video][url]"
            value="{{ $value['video']['url'] }}"
        >

        <div class="video-settings">
            <div class="setting-group">
                <label>
                    <input type="checkbox"
                        name="{{ $field['name'] }}[video][loop]"
                        value="1"
                        @if($value['video']['loop'])
                            checked
                        @endif
                    >
                    Loop video
                </label>
            </div>

            <div class="setting-group">
                <label>
                    <input type="checkbox"
                        name="{{ $field['name'] }}[video][mute]"
                        value="1"
                        @if($value['video']['mute'])
                            checked
                        @endif
                    >
                    Mute video
                </label>
            </div>

            <div class="setting-group">
                <label>Playback Speed</label>
                <input type="number"
                    name="{{ $field['name'] }}[video][playback_speed]"
                    value="{{ $value['video']['playback_speed'] }}"
                    min="0.25"
                    max="2"
                    step="0.25"
                >
            </div>

            <div class="setting-group">
                <label>Fallback Image</label>
                <div class="fallback-preview">
                    @if(!empty($value['video']['fallback_id']))
                        <?php $fallback = wp_get_attachment_image_src($value['video']['fallback_id'], 'medium'); ?>
                        @if($fallback)
                            <img src="{{ $fallback[0] }}" alt="Fallback preview">
                        @endif
                    @endif
                </div>
                <button type="button" class="button select-fallback">
                    {{ empty($value['video']['fallback_id']) ? 'Select Fallback' : 'Change Fallback' }}
                </button>
                @if(!empty($value['video']['fallback_id']))
                    <button type="button" class="button remove-fallback">
                        Remove Fallback
                    </button>
                @endif
                <input type="hidden"
                    name="{{ $field['name'] }}[video][fallback_id]"
                    value="{{ $value['video']['fallback_id'] }}"
                >
                <input type="hidden"
                    name="{{ $field['name'] }}[video][fallback_url]"
                    value="{{ $value['video']['fallback_url'] }}"
                >
            </div>
        </div>
    </div>
</div>