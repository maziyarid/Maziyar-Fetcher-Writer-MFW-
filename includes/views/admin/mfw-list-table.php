<?php
/**
 * Admin List Table Template
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
        @if($can_create)
            <a href="{{ $create_url }}" class="button button-primary">
                <span class="dashicons dashicons-plus"></span> Add New
            </a>
        @endif
        @if($can_import)
            <button type="button" class="button" data-modal="mfw-import">
                <span class="dashicons dashicons-upload"></span> Import
            </button>
        @endif
        @if($can_export)
            <button type="button" class="button" data-action="export">
                <span class="dashicons dashicons-download"></span> Export
            </button>
        @endif
    </div>
@endsection

@section('content')
    <!-- List Table Filters -->
    <div class="mfw-list-filters">
        <x-form method="get" class="mfw-filter-form">
            <input type="hidden" name="page" value="{{ $page_slug }}">
            
            <div class="mfw-filter-fields">
                @foreach($filters as $filter)
                    <div class="mfw-filter-field">
                        @include("admin.filters.{$filter['type']}", [
                            'filter' => $filter,
                            'value' => $current_filters[$filter['id']] ?? ''
                        ])
                    </div>
                @endforeach

                <div class="mfw-filter-actions">
                    <button type="submit" class="button">
                        <span class="dashicons dashicons-filter"></span> Filter
                    </button>
                    @if(!empty($current_filters))
                        <a href="{{ $reset_url }}" class="button">
                            Reset
                        </a>
                    @endif
                </div>
            </div>
        </x-form>
    </div>

    <!-- Bulk Actions Form -->
    <x-form method="post" action="{{ admin_url('admin-post.php') }}" class="mfw-bulk-form">
        <input type="hidden" name="action" value="mfw_bulk_action">
        <input type="hidden" name="page" value="{{ $page_slug }}">

        <!-- List Table -->
        <x-table class="mfw-list-table">
            @slot('title')
                {{ $total_items }} item(s) found
            @endslot

            @slot('actions')
                <div class="mfw-bulk-actions">
                    <select name="bulk_action">
                        <option value="">Bulk Actions</option>
                        @foreach($bulk_actions as $action => $label)
                            <option value="{{ $action }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="button">Apply</button>
                </div>
            @endslot

            @slot('headers')
                @if(!empty($bulk_actions))
                    <th class="check-column">
                        <input type="checkbox" class="mfw-check-all">
                    </th>
                @endif
                @foreach($columns as $column)
                    <th {!! $column['attributes'] ?? '' !!}>
                        @if(!empty($column['sortable']))
                            <a href="{{ $column['sort_url'] }}" class="mfw-sort-link">
                                {{ $column['label'] }}
                                @if($column['sorted'])
                                    <span class="dashicons dashicons-arrow-{{ $column['direction'] }}-alt"></span>
                                @endif
                            </a>
                        @else
                            {{ $column['label'] }}
                        @endif
                    </th>
                @endforeach
                <th class="actions-column">Actions</th>
            @endslot

            @if(!empty($items))
                @foreach($items as $item)
                    <tr class="{{ $item['status'] ?? '' }}">
                        @if(!empty($bulk_actions))
                            <td class="check-column">
                                <input type="checkbox" name="items[]" value="{{ $item['id'] }}">
                            </td>
                        @endif
                        @foreach($columns as $key => $column)
                            <td {!! $column['attributes'] ?? '' !!}>
                                {!! $item[$key] !!}
                            </td>
                        @endforeach
                        <td class="actions-column">
                            @foreach($item['actions'] as $action)
                                @if($action['type'] === 'link')
                                    <a href="{{ $action['url'] }}" 
                                        class="button {{ $action['class'] ?? '' }}"
                                        @if(!empty($action['confirm']))
                                            onclick="return confirm('{{ $action['confirm'] }}')"
                                        @endif
                                    >
                                        @if(!empty($action['icon']))
                                            <span class="dashicons dashicons-{{ $action['icon'] }}"></span>
                                        @endif
                                        {{ $action['label'] }}
                                    </a>
                                @else
                                    <button type="button" 
                                        class="button {{ $action['class'] ?? '' }}"
                                        data-action="{{ $action['action'] }}"
                                        data-id="{{ $item['id'] }}"
                                        @if(!empty($action['confirm']))
                                            data-confirm="{{ $action['confirm'] }}"
                                        @endif
                                    >
                                        @if(!empty($action['icon']))
                                            <span class="dashicons dashicons-{{ $action['icon'] }}"></span>
                                        @endif
                                        {{ $action['label'] }}
                                    </button>
                                @endif
                            @endforeach
                        </td>
                    </tr>
                @endforeach
            @else
                <tr>
                    <td colspan="{{ count($columns) + 2 }}" class="no-items">
                        No items found.
                    </td>
                </tr>
            @endif

            @slot('footer')
                <div class="mfw-table-pagination">
                    {!! $pagination !!}
                </div>
            @endslot
        </x-table>
    </x-form>

    <!-- Import Modal -->
    @if($can_import)
        <x-modal id="mfw-import">
            @slot('title')
                Import Items
            @endslot

            <x-form 
                method="post" 
                action="{{ admin_url('admin-post.php') }}"
                enctype="multipart/form-data"
                class="mfw-import-form"
            >
                <input type="hidden" name="action" value="mfw_import_items">
                <input type="hidden" name="page" value="{{ $page_slug }}">

                <div class="mfw-form-group">
                    <label for="import_file">Select File</label>
                    <input type="file" 
                        name="import_file" 
                        id="import_file"
                        accept=".csv,.json,.xlsx"
                        required
                    >
                    <p class="description">
                        Supported formats: CSV, JSON, XLSX
                    </p>
                </div>

                <div class="mfw-form-group">
                    <label>
                        <input type="checkbox" name="update_existing" value="1">
                        Update existing items
                    </label>
                </div>

                @slot('footer')
                    <button type="button" class="button" data-dismiss="modal">
                        Cancel
                    </button>
                    <button type="submit" class="button button-primary">
                        Import
                    </button>
                @endslot
            </x-form>
        </x-modal>
    @endif
@endsection