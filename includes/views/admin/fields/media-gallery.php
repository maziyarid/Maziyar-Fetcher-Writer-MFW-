<?php
/**
 * Media Gallery Field Template
 * 
 * @package MFW
 * @subpackage Views
 * @since 1.0.0
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

$multiple = !empty($field['multiple']);
$allowed_types = $field['allowed_types'] ?? ['image'];
$max_files = $field['max_files'] ?? 0;
$min_files = $field['min_files'] ?? 0;
?>
<div class="mfw-media-gallery {{ $field['class'] ?? '' }}"
    data-multiple="{{ $multiple ? 'true' : 'false' }}"
    data-max-files="{{ $max_files }}"
    data-min-files="{{ $min_files }}"
    data-allowed-types="{{ implode(',', $allowed_types) }}"
    data-max-size="{{ $field['max_size'] ?? wp_max_upload_size() }}"
>
    <div class="gallery-preview">
        @if(!empty($value))
            @foreach((array)$value as $attachment_id)
                <?php $attachment = get_post($attachment_id); ?>
                @if($attachment)
                    <div class="gallery-item" data-id="{{ $attachment_id }}">
                        <div class="item-preview">
                            @if(wp_attachment_is('image', $attachment))
                                <?php $thumb = wp_get_attachment_image_src($attachment_id, 'thumbnail'); ?>
                                <img src="{{ $thumb[0] }}" 
                                    alt="{{ get_post_meta($attachment_id, '_wp_attachment_image_alt', true) }}"
                                >
                            @else
                                <div class="file-preview">
                                    <span class="dashicons dashicons-{{ get_post_mime_type_icon($attachment->post_mime_type) }}"></span>
                                    <span class="file-name">{{ $attachment->post_title }}</span>
                                </div>
                            @endif
                        </div>

                        <div class="item-tools">
                            <button type="button" class="edit-item" title="Edit">
                                <span class="dashicons dashicons-edit"></span>
                            </button>
                            <button type="button" class="remove-item" title="Remove">
                                <span class="dashicons dashicons-no"></span>
                            </button>
                            @if(!empty($field['show_move']))
                                <button type="button" class="move-item" title="Move">
                                    <span class="dashicons dashicons-move"></span>
                                </button>
                            @endif
                        </div>

                        <div class="item-meta">
                            @if(!empty($field['show_title']))
                                <input type="text"
                                    class="item-title"
                                    name="{{ $field['name'] }}[{{ $attachment_id }}][title]"
                                    value="{{ $attachment->post_title }}"
                                    placeholder="Title"
                                >
                            @endif
                            @if(!empty($field['show_caption']))
                                <textarea
                                    class="item-caption"
                                    name="{{ $field['name'] }}[{{ $attachment_id }}][caption]"
                                    placeholder="Caption"
                                >{{ $attachment->post_excerpt }}</textarea>
                            @endif
                            @if(!empty($field['show_alt']))
                                <input type="text"
                                    class="item-alt"
                                    name="{{ $field['name'] }}[{{ $attachment_id }}][alt]"
                                    value="{{ get_post_meta($attachment_id, '_wp_attachment_image_alt', true) }}"
                                    placeholder="Alt Text"
                                >
                            @endif
                        </div>

                        <input type="hidden"
                            name="{{ $field['name'] }}[{{ $attachment_id }}][id]"
                            value="{{ $attachment_id }}"
                        >
                    </div>
                @endif
            @endforeach
        @endif

        @if(empty($field['max_files']) || count((array)$value) < $field['max_files'])
            <div class="gallery-upload">
                <div class="upload-area"
                    @if(!empty($field['dropzone']))
                        data-dropzone="true"
                    @endif
                >
                    <span class="dashicons dashicons-plus-alt2"></span>
                    <span class="upload-text">
                        {{ $field['upload_text'] ?? 'Add Media' }}
                    </span>
                    @if(!empty($field['dropzone']))
                        <span class="drop-text">
                            {{ $field['drop_text'] ?? 'or drop files here' }}
                        </span>
                    @endif
                </div>
            </div>
        @endif
    </div>

    <div class="gallery-tools">
        <div class="left-tools">
            @if(!empty($field['show_select_all']))
                <button type="button" class="button select-all">
                    <span class="dashicons dashicons-yes-alt"></span>
                    Select All
                </button>
            @endif
            
            @if(!empty($field['show_clear']))
                <button type="button" class="button clear-gallery">
                    <span class="dashicons dashicons-trash"></span>
                    Clear All
                </button>
            @endif
        </div>

        <div class="right-tools">
            @if(!empty($field['show_sort']))
                <select class="sort-items">
                    <option value="">Sort by...</option>
                    <option value="date-asc">Date ↑</option>
                    <option value="date-desc">Date ↓</option>
                    <option value="name-asc">Name ↑</option>
                    <option value="name-desc">Name ↓</option>
                    <option value="custom">Custom Order</option>
                </select>
            @endif

            @if(!empty($field['show_view']))
                <button type="button" class="button toggle-view" title="Toggle view">
                    <span class="dashicons dashicons-grid-view"></span>
                </button>
            @endif
        </div>
    </div>

    @if(!empty($field['show_count']))
        <div class="gallery-count">
            Items: <span class="count">{{ count((array)$value) }}</span>
            @if(!empty($field['max_files']))
                / <span class="max">{{ $field['max_files'] }}</span>
            @endif
        </div>
    @endif

    <!-- Hidden input for storing the final value -->
    <input type="hidden"
        id="{{ $field['id'] }}"
        name="{{ $field['name'] }}_ids"
        value="{{ is_array($value) ? implode(',', $value) : $value }}"
        @if(!empty($field['required']))
            required
        @endif
        {!! $field['attributes'] ?? '' !!}
    >
</div>