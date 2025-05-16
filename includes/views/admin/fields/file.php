<?php
/**
 * File Upload Field Template
 * 
 * @package MFW
 * @subpackage Views
 * @since 1.0.0
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="mfw-file-upload {{ $field['class'] ?? '' }}">
    <div class="file-upload-area"
        data-max-size="{{ $field['max_size'] ?? '2048' }}"
        data-allowed-types="{{ implode(',', $field['allowed_types'] ?? ['image/*']) }}"
        data-multiple="{{ !empty($field['multiple']) ? 'true' : 'false' }}"
    >
        <input type="file"
            id="{{ $field['id'] }}"
            name="{{ $field['name'] ?? $field['id'] }}{{ !empty($field['multiple']) ? '[]' : '' }}"
            class="file-input"
            @if(!empty($field['multiple']))
                multiple
            @endif
            @if(!empty($field['accept']))
                accept="{{ $field['accept'] }}"
            @endif
            @if(!empty($field['required']))
                required
            @endif
            @if(!empty($field['disabled']))
                disabled
            @endif
            {!! $field['attributes'] ?? '' !!}
        >

        <div class="upload-interface">
            <div class="upload-message">
                <span class="dashicons dashicons-upload"></span>
                <span class="message-text">
                    {{ $field['upload_text'] ?? 'Drop files here or click to upload' }}
                </span>
            </div>
            
            @if(!empty($field['help_text']))
                <div class="upload-help">
                    {{ $field['help_text'] }}
                </div>
            @endif
        </div>

        <div class="upload-preview">
            @if(!empty($value))
                @foreach((array)$value as $file)
                    <div class="preview-item" data-file="{{ $file['id'] }}">
                        @if($file['type'] === 'image')
                            <img src="{{ $file['url'] }}" alt="{{ $file['name'] }}">
                        @else
                            <span class="dashicons dashicons-{{ $file['icon'] }}"></span>
                        @endif
                        <div class="file-info">
                            <span class="file-name">{{ $file['name'] }}</span>
                            <span class="file-size">{{ $file['size'] }}</span>
                        </div>
                        <button type="button" class="remove-file" title="Remove file">
                            <span class="dashicons dashicons-no"></span>
                        </button>
                    </div>
                @endforeach
            @endif
        </div>
    </div>

    @if(!empty($field['media_library']))
        <button type="button" class="button media-library-button">
            <span class="dashicons dashicons-admin-media"></span>
            {{ $field['media_button_text'] ?? 'Choose from Media Library' }}
        </button>
    @endif
</div>