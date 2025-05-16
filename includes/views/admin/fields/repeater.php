<?php
/**
 * Repeater Field Template
 * Dynamic repeatable field groups with AI-powered content suggestions
 * 
 * @package MFW
 * @subpackage Views
 * @since 1.0.0
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

$value = is_array($value) ? $value : [];
$min_rows = $field['min_rows'] ?? 0;
$max_rows = $field['max_rows'] ?? 0;
$row_label = $field['row_label'] ?? 'Row';
$sub_fields = $field['sub_fields'] ?? [];

// Ensure minimum rows
if (count($value) < $min_rows) {
    $value = array_merge($value, array_fill(count($value), $min_rows - count($value), []));
}
?>
<div class="mfw-repeater-field {{ $field['class'] ?? '' }}"
    data-min-rows="{{ $min_rows }}"
    data-max-rows="{{ $max_rows }}"
>
    <!-- Repeater Header -->
    <div class="repeater-header">
        <div class="repeater-title">
            <h3>{{ $field['title'] ?? 'Repeater Field' }}</h3>
            <span class="row-count">
                {{ count($value) }} {{ $max_rows ? 'of ' . $max_rows : '' }} items
            </span>
        </div>

        <!-- Actions Toolbar -->
        <div class="repeater-actions">
            @if(!empty($field['enable_ai']))
                <div class="ai-assistant">
                    <button type="button" class="ai-suggest" title="Get AI suggestions">
                        <span class="dashicons dashicons-admin-customizer"></span>
                        AI Assist
                    </button>
                    <div class="ai-options" style="display: none;">
                        <select class="ai-template">
                            <option value="">Select Template</option>
                            @foreach($field['ai_templates'] ?? [] as $template => $label)
                                <option value="{{ $template }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <button type="button" class="generate-content">
                            Generate Content
                        </button>
                    </div>
                </div>
            @endif

            @if(!empty($field['enable_import']))
                <button type="button" class="import-data" title="Import Data">
                    <span class="dashicons dashicons-download"></span>
                </button>
            @endif

            @if(!empty($field['enable_export']))
                <button type="button" class="export-data" title="Export Data">
                    <span class="dashicons dashicons-upload"></span>
                </button>
            @endif
        </div>
    </div>

    <!-- Rows Container -->
    <div class="repeater-rows" data-layout="{{ $field['layout'] ?? 'table' }}">
        @if($field['layout'] === 'table' && !empty($sub_fields))
            <!-- Table Header -->
            <div class="repeater-table-header">
                <div class="row-handle"></div>
                @foreach($sub_fields as $sub_field)
                    <div class="column-header" style="width: {{ $sub_field['width'] ?? 'auto' }}">
                        {{ $sub_field['label'] }}
                        @if(!empty($sub_field['description']))
                            <span class="help-tip" title="{{ $sub_field['description'] }}">?</span>
                        @endif
                    </div>
                @endforeach
                <div class="row-actions"></div>
            </div>
        @endif

        <!-- Repeater Rows -->
        <div class="rows-wrapper">
            @foreach($value as $row_index => $row)
                <div class="repeater-row" data-index="{{ $row_index }}">
                    <div class="row-header">
                        <div class="row-handle">
                            <span class="dashicons dashicons-menu"></span>
                        </div>
                        <div class="row-number">
                            {{ $row_index + 1 }}
                        </div>
                        <div class="row-label">
                            @if(is_callable($row_label))
                                {{ call_user_func($row_label, $row, $row_index) }}
                            @else
                                {{ $row_label }} {{ $row_index + 1 }}
                            @endif
                        </div>
                        <div class="row-actions">
                            <button type="button" class="toggle-row" title="Toggle Row">
                                <span class="dashicons dashicons-arrow-down-alt2"></span>
                            </button>
                            <button type="button" class="clone-row" title="Clone Row">
                                <span class="dashicons dashicons-admin-page"></span>
                            </button>
                            <button type="button" class="remove-row" title="Remove Row">
                                <span class="dashicons dashicons-trash"></span>
                            </button>
                        </div>
                    </div>

                    <div class="row-content">
                        @foreach($sub_fields as $sub_field_key => $sub_field)
                            <div class="field-wrapper field-type-{{ $sub_field['type'] }}">
                                @if($field['layout'] !== 'table')
                                    <label>
                                        {{ $sub_field['label'] }}
                                        @if(!empty($sub_field['required']))
                                            <span class="required">*</span>
                                        @endif
                                    </label>
                                @endif

                                {!! render_field([
                                    'type' => $sub_field['type'],
                                    'name' => $field['name'] . "[{$row_index}][{$sub_field_key}]",
                                    'value' => $row[$sub_field_key] ?? null,
                                    'id' => $field['id'] . "_{$row_index}_{$sub_field_key}",
                                    ...$sub_field
                                ]) !!}
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <!-- Add Row Button -->
    <div class="repeater-footer">
        <button type="button" 
            class="button add-row"
            @if($max_rows && count($value) >= $max_rows)
                disabled
            @endif
        >
            <span class="dashicons dashicons-plus"></span>
            {{ $field['add_label'] ?? 'Add Row' }}
        </button>
    </div>

    <!-- Templates -->
    <script type="text/template" id="empty-row-template">
        <div class="repeater-row" data-index="{{ index }}">
            <!-- Row template content -->
        </div>
    </script>

    <!-- Bulk Edit Modal -->
    @if(!empty($field['enable_bulk_edit']))
        <div class="bulk-edit-modal" style="display: none;">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Bulk Edit</h3>
                    <button type="button" class="close-modal">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                </div>
                <div class="modal-body">
                    <!-- Bulk edit form will be dynamically populated -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="button cancel-bulk-edit">Cancel</button>
                    <button type="button" class="button button-primary apply-bulk-edit">
                        Apply Changes
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Import Modal -->
    @if(!empty($field['enable_import']))
        <div class="import-modal" style="display: none;">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Import Data</h3>
                    <button type="button" class="close-modal">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="import-options">
                        <div class="option-group">
                            <label>Import Format</label>
                            <select class="import-format">
                                <option value="csv">CSV</option>
                                <option value="json">JSON</option>
                                <option value="excel">Excel</option>
                            </select>
                        </div>
                        <div class="option-group">
                            <label>Import Behavior</label>
                            <select class="import-behavior">
                                <option value="append">Append to existing</option>
                                <option value="replace">Replace existing</option>
                                <option value="merge">Merge with existing</option>
                            </select>
                        </div>
                    </div>
                    <div class="file-upload-area">
                        <input type="file" class="import-file">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="button cancel-import">Cancel</button>
                    <button type="button" class="button button-primary start-import">
                        Import Data
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>