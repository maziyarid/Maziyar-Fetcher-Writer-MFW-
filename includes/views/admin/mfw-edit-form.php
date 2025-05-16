<?php
/**
 * Admin Edit Form Template
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
        <a href="{{ $list_url }}" class="button">
            <span class="dashicons dashicons-arrow-left-alt"></span> Back to List
        </a>
        @if($item && $can_delete)
            <button type="button" 
                class="button button-danger" 
                data-modal="mfw-delete-confirm"
            >
                <span class="dashicons dashicons-trash"></span> Delete
            </button>
        @endif
    </div>
@endsection

@section('content')
    <x-card class="mfw-edit-form">
        <x-form 
            method="post" 
            action="{{ admin_url('admin-post.php') }}"
            enctype="multipart/form-data"
            id="mfw-edit-form"
        >
            <input type="hidden" name="action" value="mfw_save_item">
            <input type="hidden" name="page" value="{{ $page_slug }}">
            @if($item)
                <input type="hidden" name="id" value="{{ $item['id'] }}">
            @endif

            <!-- Form Tabs -->
            <div class="mfw-form-tabs">
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
                            @if(!empty($tab['error']))
                                <span class="mfw-error-badge">!</span>
                            @endif
                        </button>
                    @endforeach
                </div>

                <div class="mfw-tab-content">
                    @foreach($tabs as $tab_id => $tab)
                        <div class="mfw-tab-pane {{ $active_tab === $tab_id ? 'active' : '' }}"
                            id="tab-{{ $tab_id }}"
                        >
                            @foreach($tab['sections'] as $section)
                                <div class="mfw-form-section">
                                    @if(!empty($section['title']))
                                        <h3 class="section-title">{{ $section['title'] }}</h3>
                                    @endif

                                    @if(!empty($section['description']))
                                        <p class="section-description">
                                            {{ $section['description'] }}
                                        </p>
                                    @endif

                                    <div class="mfw-form-fields">
                                        @foreach($section['fields'] as $field)
                                            <div class="mfw-form-field {{ $field['type'] }}-field">
                                                @if($field['type'] !== 'hidden')
                                                    <label for="{{ $field['id'] }}">
                                                        {{ $field['label'] }}
                                                        @if(!empty($field['required']))
                                                            <span class="required">*</span>
                                                        @endif
                                                    </label>
                                                @endif

                                                @include("admin.fields.{$field['type']}", [
                                                    'field' => $field,
                                                    'value' => $item[$field['id']] ?? $field['default'] ?? '',
                                                    'errors' => $errors[$field['id']] ?? []
                                                ])

                                                @if(!empty($field['description']))
                                                    <p class="field-description">
                                                        {{ $field['description'] }}
                                                    </p>
                                                @endif

                                                @if(!empty($errors[$field['id']]))
                                                    <div class="field-errors">
                                                        @foreach($errors[$field['id']] as $error)
                                                            <p class="error-message">{{ $error }}</p>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Form Sidebar -->
            <div class="mfw-form-sidebar">
                <!-- Status Card -->
                <x-card class="mfw-status-card">
                    @slot('title')
                        Status & Visibility
                    @endslot

                    <div class="mfw-form-field status-field">
                        <select name="status" id="status">
                            @foreach($statuses as $status => $label)
                                <option value="{{ $status }}"
                                    {{ ($item['status'] ?? 'draft') === $status ? 'selected' : '' }}
                                >
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mfw-form-field visibility-field">
                        <label>Visibility</label>
                        @foreach($visibility_options as $option => $label)
                            <label class="radio-label">
                                <input type="radio" 
                                    name="visibility" 
                                    value="{{ $option }}"
                                    {{ ($item['visibility'] ?? 'public') === $option ? 'checked' : '' }}
                                >
                                {{ $label }}
                            </label>
                        @endforeach
                    </div>

                    @if($item)
                        <div class="mfw-meta-info">
                            <p>
                                Created: {{ $item['created_at'] }}<br>
                                By: {{ $item['author'] }}
                            </p>
                            @if($item['updated_at'])
                                <p>
                                    Last Modified: {{ $item['updated_at'] }}<br>
                                    By: {{ $item['last_editor'] }}
                                </p>
                            @endif
                        </div>
                    @endif
                </x-card>

                <!-- Additional Sidebar Cards -->
                @foreach($sidebar_cards as $card)
                    <x-card class="{{ $card['class'] ?? '' }}">
                        @slot('title')
                            {{ $card['title'] }}
                        @endslot

                        {!! $card['content'] !!}

                        @if(!empty($card['footer']))
                            @slot('footer')
                                {!! $card['footer'] !!}
                            @endslot
                        @endif
                    </x-card>
                @endforeach
            </div>

            <!-- Form Footer -->
            <div class="mfw-form-footer">
                <button type="submit" class="button button-primary" name="save_action" value="save">
                    {{ $item ? 'Update' : 'Create' }}
                </button>
                <button type="submit" class="button" name="save_action" value="save_draft">
                    Save as Draft
                </button>
                @if($can_preview)
                    <button type="submit" class="button" name="save_action" value="preview">
                        Preview
                    </button>
                @endif
            </div>
        </x-form>
    </x-card>

    <!-- Delete Confirmation Modal -->
    @if($item && $can_delete)
        <x-modal id="mfw-delete-confirm" class="mfw-delete-modal">
            @slot('title')
                Delete Confirmation
            @endslot

            <p>Are you sure you want to delete this item? This action cannot be undone.</p>

            <x-form 
                method="post" 
                action="{{ admin_url('admin-post.php') }}"
                class="mfw-delete-form"
            >
                <input type="hidden" name="action" value="mfw_delete_item">
                <input type="hidden" name="page" value="{{ $page_slug }}">
                <input type="hidden" name="id" value="{{ $item['id'] }}">

                @slot('footer')
                    <button type="button" class="button" data-dismiss="modal">
                        Cancel
                    </button>
                    <button type="submit" class="button button-danger">
                        Delete Permanently
                    </button>
                @endslot
            </x-form>
        </x-modal>
    @endif

    <!-- Unsaved Changes Warning -->
    <script type="text/javascript">
        var unsavedChanges = false;
        
        document.getElementById('mfw-edit-form').addEventListener('change', function() {
            unsavedChanges = true;
        });

        window.addEventListener('beforeunload', function(e) {
            if (unsavedChanges) {
                e.preventDefault();
                e.returnValue = '';
            }
        });
    </script>
@endsection