<?php
/**
 * File Upload Field Template
 * Advanced file upload with drag & drop, preview, and multiple file support
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
$max_files = $field['max_files'] ?? 0;
$max_size = $field['max_size'] ?? wp_max_upload_size();
$allowed_types = $field['allowed_types'] ?? ['image/*', '.pdf', '.doc', '.docx'];
$value = is_array($value) ? $value : ($value ? [$value] : []);

// Get file info for existing files
$files_info = [];
foreach ($value as $file_id) {
    if (is_numeric($file_id)) {
        $file_url = wp_get_attachment_url($file_id);
        $file_type = get_post_mime_type($file_id);
        $file_name = basename($file_url);
        $files_info[$file_id] = [
            'id' => $file_id,
            'url' => $file_url,
            'type' => $file_type,
            'name' => $file_name,
            'size' => filesize(get_attached_file($file_id)),
            'title' => get_the_title($file_id),
            'caption' => wp_get_attachment_caption($file_id)
        ];
    }
}
?>
<div class="mfw-file-upload {{ $field['class'] ?? '' }}"
    data-multiple="{{ $multiple ? 'true' : 'false' }}"
    data-max-files="{{ $max_files }}"
    data-max-size="{{ $max_size }}"
    data-allowed-types="{{ implode(',', $allowed_types) }}"
>
    <!-- Upload Zone -->
    <div class="upload-zone"
        @if(count($files_info) >= $max_files && $max_files > 0)
            style="display: none;"
        @endif
    >
        <div class="zone-inner">
            <div class="zone-content">
                <span class="dashicons dashicons-upload"></span>
                <div class="upload-instructions">
                    <strong>Drop files here</strong>
                    <span>or</span>
                    <button type="button" class="button select-files">
                        Select Files
                    </button>
                </div>
                <div class="upload-restrictions">
                    @if($max_files)
                        <span class="max-files">Maximum {{ $max_files }} files</span>
                    @endif
                    <span class="max-size">Max size: {{ size_format($max_size) }}</span>
                    <span class="allowed-types">
                        Allowed: {{ implode(', ', array_map(function($type) {
                            return str_replace(['*', '.'], '', $type);
                        }, $allowed_types)) }}
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- File Preview Area -->
    <div class="file-preview-area"
        @if(empty($files_info))
            style="display: none;"
        @endif
    >
        <div class="preview-list">
            @foreach($files_info as $file_info)
                <div class="file-preview-item" data-file-id="{{ $file_info['id'] }}">
                    <!-- Preview Thumbnail -->
                    <div class="preview-thumbnail">
                        @if(strpos($file_info['type'], 'image/') === 0)
                            <img src="{{ wp_get_attachment_image_url($file_info['id'], 'thumbnail') }}"
                                alt="{{ $file_info['title'] }}"
                            >
                        @else
                            <span class="file-icon dashicons dashicons-{{ get_file_icon_class($file_info['type']) }}"></span>
                        @endif
                    </div>

                    <!-- File Info -->
                    <div class="file-info">
                        <div class="file-name" title="{{ $file_info['name'] }}">
                            {{ $file_info['name'] }}
                        </div>
                        <div class="file-meta">
                            <span class="file-size">{{ size_format($file_info['size']) }}</span>
                            <span class="file-type">{{ strtoupper(pathinfo($file_info['name'], PATHINFO_EXTENSION)) }}</span>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="file-actions">
                        <button type="button" class="edit-file" title="Edit">
                            <span class="dashicons dashicons-edit"></span>
                        </button>
                        <button type="button" class="remove-file" title="Remove">
                            <span class="dashicons dashicons-trash"></span>
                        </button>
                    </div>

                    <!-- Progress Bar (hidden by default) -->
                    <div class="upload-progress" style="display: none;">
                        <div class="progress-bar"></div>
                        <div class="progress-text">0%</div>
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Bulk Actions -->
        @if($multiple)
            <div class="bulk-actions">
                <select class="bulk-action-select">
                    <option value="">Bulk Actions</option>
                    <option value="remove">Remove Selected</option>
                    @if(!empty($field['allow_download']))
                        <option value="download">Download Selected</option>
                    @endif
                </select>
                <button type="button" class="button apply-bulk-action">Apply</button>
            </div>
        @endif
    </div>

    <!-- File Edit Modal -->
    <div class="file-edit-modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit File Details</h3>
                <button type="button" class="close-modal">
                    <span class="dashicons dashicons-no-alt"></span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Title</label>
                    <input type="text" class="file-title">
                </div>
                <div class="form-group">
                    <label>Caption</label>
                    <textarea class="file-caption"></textarea>
                </div>
                <div class="form-group">
                    <label>Alt Text</label>
                    <input type="text" class="file-alt">
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea class="file-description"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="button cancel-edit">Cancel</button>
                <button type="button" class="button button-primary save-file">Save Changes</button>
            </div>
        </div>
    </div>

    <!-- Hidden File Input -->
    <input type="file"
        class="file-input"
        style="display: none;"
        @if($multiple)
            multiple
        @endif
        accept="{{ implode(',', $allowed_types) }}"
    >

    <!-- Hidden Value Input -->
    <input type="hidden"
        name="{{ $field['name'] }}{{ $multiple ? '[]' : '' }}"
        value="{{ implode(',', array_keys($files_info)) }}"
        class="file-ids"
    >

    <!-- Error Container -->
    <div class="upload-errors" style="display: none;"></div>

    <!-- Templates -->
    <script type="text/template" id="file-preview-template">
        <div class="file-preview-item" data-file-id="{{ id }}">
            <div class="preview-thumbnail">
                {{ thumbnail }}
            </div>
            <div class="file-info">
                <div class="file-name" title="{{ name }}">{{ name }}</div>
                <div class="file-meta">
                    <span class="file-size">{{ size }}</span>
                    <span class="file-type">{{ type }}</span>
                </div>
            </div>
            <div class="file-actions">
                <button type="button" class="edit-file" title="Edit">
                    <span class="dashicons dashicons-edit"></span>
                </button>
                <button type="button" class="remove-file" title="Remove">
                    <span class="dashicons dashicons-trash"></span>
                </button>
            </div>
            <div class="upload-progress">
                <div class="progress-bar"></div>
                <div class="progress-text">0%</div>
            </div>
        </div>
    </script>
</div>