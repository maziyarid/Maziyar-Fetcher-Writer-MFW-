<?php
/**
 * Admin Settings Template
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
        <button type="button" class="button" data-modal="mfw-import-export">
            <span class="dashicons dashicons-migrate"></span> Import/Export
        </button>
    </div>
@endsection

@section('content')
    <!-- Settings Form -->
    <x-form 
        method="post" 
        action="{{ admin_url('admin-post.php') }}" 
        class="mfw-settings-form"
    >
        <input type="hidden" name="action" value="mfw_save_settings">

        <!-- Settings Tabs -->
        <div class="mfw-settings-tabs">
            <div class="mfw-tab-nav">
                @foreach($tabs as $tab_id => $tab)
                    <button type="button" 
                        class="mfw-tab-button {{ $active_tab === $tab_id ? 'active' : '' }}"
                        data-tab="{{ $tab_id }}"
                    >
                        @if(!empty($tab['icon']))
                            <span class="dashicons dashicons-{{ $tab['icon'] }}"></span>
                        @endif
                        {{ $tab['label'] }}
                    </button>
                @endforeach
            </div>

            <div class="mfw-tab-content">
                @foreach($tabs as $tab_id => $tab)
                    <div class="mfw-tab-pane {{ $active_tab === $tab_id ? 'active' : '' }}"
                        id="tab-{{ $tab_id }}"
                    >
                        @foreach($tab['sections'] as $section)
                            <x-card class="mfw-settings-section">
                                @slot('title')
                                    {{ $section['title'] }}
                                @endslot

                                @if(!empty($section['description']))
                                    <p class="section-description">
                                        {{ $section['description'] }}
                                    </p>
                                @endif

                                <table class="form-table">
                                    @foreach($section['fields'] as $field)
                                        <tr>
                                            <th scope="row">
                                                <label for="{{ $field['id'] }}">
                                                    {{ $field['label'] }}
                                                </label>
                                            </th>
                                            <td>
                                                @include("admin.fields.{$field['type']}", [
                                                    'field' => $field,
                                                    'value' => $settings[$field['id']] ?? $field['default'] ?? ''
                                                ])
                                                @if(!empty($field['description']))
                                                    <p class="description">
                                                        {{ $field['description'] }}
                                                    </p>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </table>
                            </x-card>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>

        <div class="mfw-settings-footer">
            <button type="submit" class="button button-primary">
                Save Settings
            </button>
            <button type="button" class="button" data-action="reset-settings">
                Reset to Defaults
            </button>
        </div>
    </x-form>

    <!-- Import/Export Modal -->
    <x-modal id="mfw-import-export">
        @slot('title')
            Import/Export Settings
        @endslot

        <div class="mfw-import-export-tabs">
            <!-- Export Tab -->
            <div class="mfw-tab-pane active" id="export-settings">
                <p>Export your current settings configuration:</p>
                <button type="button" class="button" data-action="export-settings">
                    Download Settings
                </button>
            </div>

            <!-- Import Tab -->
            <div class="mfw-tab-pane" id="import-settings">
                <p>Import settings from a configuration file:</p>
                <x-form 
                    method="post" 
                    action="{{ admin_url('admin-post.php') }}"
                    enctype="multipart/form-data"
                >
                    <input type="hidden" name="action" value="mfw_import_settings">
                    <input type="file" name="settings_file" accept=".json">
                    <button type="submit" class="button">
                        Import Settings
                    </button>
                </x-form>
            </div>
        </div>

        @slot('footer')
            <button type="button" class="button" data-dismiss="modal">
                Close
            </button>
        @endslot
    </x-modal>
@endsection