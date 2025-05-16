<?php
/**
 * Admin Import/Export Template
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

@extends('layouts.admin')

@section('page-actions')
    <div class="mfw-page-actions">
        <a href="{{ admin_url('admin.php?page=mfw-tools') }}" class="button">
            <span class="dashicons dashicons-admin-tools"></span> Tools
        </a>
    </div>
@endsection

@section('content')
    <div class="mfw-import-export-grid">
        <!-- Import Section -->
        <x-card class="mfw-import-card">
            @slot('title')
                Import Data
            @endslot

            <x-form 
                method="post" 
                action="{{ admin_url('admin-post.php') }}"
                enctype="multipart/form-data"
                class="mfw-import-form"
            >
                <input type="hidden" name="action" value="mfw_import_data">

                <div class="mfw-form-section">
                    <div class="mfw-form-field">
                        <label for="import_type">Data Type</label>
                        <select name="import_type" id="import_type" required>
                            <option value="">Select Data Type</option>
                            @foreach($import_types as $type => $label)
                                <option value="{{ $type }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mfw-form-field">
                        <label for="import_file">Import File</label>
                        <input type="file" 
                            name="import_file" 
                            id="import_file"
                            accept=".csv,.json,.xlsx"
                            required
                        >
                        <p class="field-description">
                            Supported formats: CSV, JSON, XLSX
                        </p>
                    </div>

                    <div class="mfw-form-field">
                        <label>Import Options</label>
                        <label class="checkbox-label">
                            <input type="checkbox" name="options[update_existing]" value="1">
                            Update existing records
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" name="options[skip_validation]" value="1">
                            Skip validation (not recommended)
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" name="options[send_notifications]" value="1" checked>
                            Send notifications
                        </label>
                    </div>

                    <div class="mfw-form-field mapping-field" style="display: none;">
                        <label>Field Mapping</label>
                        <div class="mfw-field-mapping">
                            <table class="mfw-mapping-table">
                                <thead>
                                    <tr>
                                        <th>File Column</th>
                                        <th>Maps To</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Dynamically populated via JavaScript -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="mfw-form-actions">
                    <button type="submit" class="button button-primary">
                        Start Import
                    </button>
                    <button type="button" class="button" data-action="validate-file">
                        Validate File
                    </button>
                </div>
            </x-form>

            @slot('footer')
                <a href="{{ $sample_file_url }}" class="button">
                    Download Sample File
                </a>
                <a href="{{ $documentation_url }}" class="button" target="_blank">
                    View Documentation
                </a>
            @endslot
        </x-card>

        <!-- Export Section -->
        <x-card class="mfw-export-card">
            @slot('title')
                Export Data
            @endslot

            <x-form 
                method="post" 
                action="{{ admin_url('admin-post.php') }}"
                class="mfw-export-form"
            >
                <input type="hidden" name="action" value="mfw_export_data">

                <div class="mfw-form-section">
                    <div class="mfw-form-field">
                        <label for="export_type">Data Type</label>
                        <select name="export_type" id="export_type" required>
                            <option value="">Select Data Type</option>
                            @foreach($export_types as $type => $label)
                                <option value="{{ $type }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mfw-form-field">
                        <label for="export_format">Export Format</label>
                        <select name="export_format" id="export_format" required>
                            <option value="csv">CSV</option>
                            <option value="json">JSON</option>
                            <option value="xlsx">Excel (XLSX)</option>
                        </select>
                    </div>

                    <div class="mfw-form-field">
                        <label>Export Options</label>
                        <label class="checkbox-label">
                            <input type="checkbox" name="options[include_deleted]" value="1">
                            Include deleted records
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" name="options[include_timestamps]" value="1" checked>
                            Include timestamps
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" name="options[include_meta]" value="1" checked>
                            Include meta data
                        </label>
                    </div>

                    <div class="mfw-form-field">
                        <label>Date Range</label>
                        <div class="date-range-inputs">
                            <input type="date" 
                                name="date_range[start]" 
                                placeholder="Start Date"
                            >
                            <span>to</span>
                            <input type="date" 
                                name="date_range[end]" 
                                placeholder="End Date"
                            >
                        </div>
                    </div>

                    <div class="mfw-form-field">
                        <label>Select Fields</label>
                        <div class="field-selection">
                            <div class="available-fields">
                                <h4>Available Fields</h4>
                                <select multiple class="field-list" id="available-fields">
                                    <!-- Dynamically populated via JavaScript -->
                                </select>
                            </div>
                            <div class="field-actions">
                                <button type="button" data-action="add-fields">→</button>
                                <button type="button" data-action="remove-fields">←</button>
                            </div>
                            <div class="selected-fields">
                                <h4>Selected Fields</h4>
                                <select multiple name="fields[]" class="field-list" id="selected-fields">
                                    <!-- Dynamically populated via JavaScript -->
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mfw-form-actions">
                    <button type="submit" class="button button-primary">
                        Export Data
                    </button>
                    <button type="button" class="button" data-action="preview-export">
                        Preview Export
                    </button>
                </div>
            </x-form>
        </x-card>

        <!-- History Section -->
        <x-card class="mfw-history-card">
            @slot('title')
                Import/Export History
            @endslot

            @if(!empty($history))
                <table class="mfw-history-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Records</th>
                            <th>User</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($history as $entry)
                            <tr class="status-{{ $entry['status'] }}">
                                <td>{{ $entry['date'] }}</td>
                                <td>{{ $entry['type'] }}</td>
                                <td>
                                    <span class="status-badge">
                                        {{ $entry['status'] }}
                                    </span>
                                </td>
                                <td>{{ $entry['records'] }}</td>
                                <td>{{ $entry['user'] }}</td>
                                <td>
                                    <div class="row-actions">
                                        @if($entry['can_download'])
                                            <a href="{{ $entry['download_url'] }}" 
                                                class="button button-small"
                                            >
                                                Download
                                            </a>
                                        @endif
                                        @if($entry['can_retry'])
                                            <button type="button" 
                                                class="button button-small"
                                                data-action="retry"
                                                data-id="{{ $entry['id'] }}"
                                            >
                                                Retry
                                            </button>
                                        @endif
                                        @if($entry['can_view_log'])
                                            <button type="button" 
                                                class="button button-small"
                                                data-action="view-log"
                                                data-id="{{ $entry['id'] }}"
                                            >
                                                View Log
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <p class="mfw-no-items">No import/export history available.</p>
            @endif
        </x-card>
    </div>

    <!-- Preview Modal -->
    <x-modal id="mfw-preview-modal">
        @slot('title')
            Export Preview
        @endslot

        <div class="mfw-preview-content">
            <!-- Dynamically populated via JavaScript -->
        </div>

        @slot('footer')
            <button type="button" class="button" data-dismiss="modal">
                Close
            </button>
            <button type="button" class="button button-primary" data-action="proceed-export">
                Proceed with Export
            </button>
        @endslot
    </x-modal>

    <!-- Log Modal -->
    <x-modal id="mfw-log-modal">
        @slot('title')
            Operation Log
        @endslot

        <div class="mfw-log-content">
            <!-- Dynamically populated via JavaScript -->
        </div>

        @slot('footer')
            <button type="button" class="button" data-dismiss="modal">
                Close
            </button>
            <button type="button" class="button" data-action="download-log">
                Download Log
            </button>
        @endslot
    </x-modal>
@endsection