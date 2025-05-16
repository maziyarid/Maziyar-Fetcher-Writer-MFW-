<?php
/**
 * Sortable List Field Template
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
$display_type = $field['display'] ?? 'list'; // list, grid, table
$sortable_groups = !empty($field['enable_groups']);
?>
<div class="mfw-sortable-list {{ $field['class'] ?? '' }}"
    data-display="{{ $display_type }}"
    data-groups="{{ $sortable_groups ? 'true' : 'false' }}"
>
    @if(!empty($field['show_toolbar']))
        <div class="list-toolbar">
            <div class="left-actions">
                @if(!empty($field['show_search']))
                    <div class="search-wrapper">
                        <input type="text"
                            class="search-input"
                            placeholder="{{ $field['search_placeholder'] ?? 'Search items...' }}"
                        >
                        <span class="dashicons dashicons-search"></span>
                    </div>
                @endif

                @if(!empty($field['show_display_options']))
                    <div class="display-options">
                        <button type="button" 
                            class="display-button {{ $display_type === 'list' ? 'active' : '' }}"
                            data-display="list"
                            title="List View"
                        >
                            <span class="dashicons dashicons-menu-alt"></span>
                        </button>
                        <button type="button"
                            class="display-button {{ $display_type === 'grid' ? 'active' : '' }}"
                            data-display="grid"
                            title="Grid View"
                        >
                            <span class="dashicons dashicons-grid-view"></span>
                        </button>
                        <button type="button"
                            class="display-button {{ $display_type === 'table' ? 'active' : '' }}"
                            data-display="table"
                            title="Table View"
                        >
                            <span class="dashicons dashicons-editor-table"></span>
                        </button>
                    </div>
                @endif
            </div>

            <div class="right-actions">
                @if(!empty($field['show_bulk_actions']))
                    <div class="bulk-actions">
                        <select class="bulk-action-select">
                            <option value="">Bulk Actions</option>
                            <option value="delete">Delete Selected</option>
                            @if($sortable_groups)
                                <option value="move">Move to Group</option>
                            @endif
                            @foreach($field['bulk_actions'] ?? [] as $action => $label)
                                <option value="{{ $action }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <button type="button" class="button apply-bulk-action">Apply</button>
                    </div>
                @endif

                @if(!empty($field['enable_add']))
                    <button type="button" class="button add-item">
                        <span class="dashicons dashicons-plus"></span>
                        {{ $field['add_label'] ?? 'Add Item' }}
                    </button>
                @endif
            </div>
        </div>
    @endif

    <div class="sortable-container {{ $display_type }}-view">
        @if($sortable_groups)
            @foreach($value as $group_id => $group)
                <div class="sortable-group" data-group-id="{{ $group_id }}">
                    <div class="group-header">
                        <div class="group-info">
                            <span class="group-handle">
                                <span class="dashicons dashicons-menu"></span>
                            </span>
                            <input type="text"
                                class="group-title"
                                name="{{ $field['name'] }}[{{ $group_id }}][title]"
                                value="{{ $group['title'] }}"
                                placeholder="Group title"
                            >
                        </div>
                        <div class="group-actions">
                            <button type="button" class="toggle-group" title="Toggle Group">
                                <span class="dashicons dashicons-arrow-down-alt2"></span>
                            </button>
                            <button type="button" class="remove-group" title="Remove Group">
                                <span class="dashicons dashicons-trash"></span>
                            </button>
                        </div>
                    </div>

                    <div class="group-items sortable-list">
                        @include('admin.fields.partials.sortable-items', [
                            'items' => $group['items'],
                            'display_type' => $display_type,
                            'field' => $field,
                            'group_id' => $group_id
                        ])
                    </div>
                </div>
            @endforeach
        @else
            <div class="sortable-list">
                @include('admin.fields.partials.sortable-items', [
                    'items' => $value,
                    'display_type' => $display_type,
                    'field' => $field
                ])
            </div>
        @endif
    </div>

    @if($sortable_groups && !empty($field['enable_add_group']))
        <button type="button" class="button add-group">
            <span class="dashicons dashicons-plus"></span>
            {{ $field['add_group_label'] ?? 'Add Group' }}
        </button>
    @endif

    <!-- Templates -->
    <script type="text/template" id="sortable-item-template">
        <div class="sortable-item" data-item-id="{{ id }}">
            <div class="item-content">
                @if($display_type === 'list' || $display_type === 'grid')
                    <div class="item-handle">
                        <span class="dashicons dashicons-menu"></span>
                    </div>
                    @if(!empty($field['show_thumbnail']))
                        <div class="item-thumbnail">
                            <img src="{{ thumbnail }}" alt="">
                        </div>
                    @endif
                    <div class="item-info">
                        <input type="text"
                            class="item-title"
                            name="{{ $field['name'] }}[{{ '{{ group_id }}' }}][items][{{ '{{ id }}' }}][title]"
                            value="{{ title }}"
                            placeholder="Item title"
                        >
                        @if(!empty($field['show_description']))
                            <textarea
                                class="item-description"
                                name="{{ $field['name'] }}[{{ '{{ group_id }}' }}][items][{{ '{{ id }}' }}][description]"
                                placeholder="Item description"
                            >{{ description }}</textarea>
                        @endif
                    </div>
                @else
                    @foreach($field['columns'] ?? ['title' => 'Title'] as $column => $label)
                        <div class="item-cell">
                            <input type="text"
                                name="{{ $field['name'] }}[{{ '{{ group_id }}' }}][items][{{ '{{ id }}' }}][{{ $column }}]"
                                value="{{ '{{ ' . $column . ' }}' }}"
                                placeholder="{{ $label }}"
                            >
                        </div>
                    @endforeach
                @endif
                <div class="item-actions">
                    @if(!empty($field['show_edit']))
                        <button type="button" class="edit-item" title="Edit Item">
                            <span class="dashicons dashicons-edit"></span>
                        </button>
                    @endif
                    <button type="button" class="remove-item" title="Remove Item">
                        <span class="dashicons dashicons-trash"></span>
                    </button>
                </div>
            </div>
            @if(!empty($field['show_meta']))
                <div class="item-meta">
                    @foreach($field['meta_fields'] ?? [] as $meta_key => $meta_field)
                        <div class="meta-field">
                            <label>{{ $meta_field['label'] }}</label>
                            <input type="{{ $meta_field['type'] ?? 'text' }}"
                                name="{{ $field['name'] }}[{{ '{{ group_id }}' }}][items][{{ '{{ id }}' }}][meta][{{ $meta_key }}]"
                                value="{{ '{{ meta.' . $meta_key . ' }}' }}"
                            >
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </script>

    @if($sortable_groups)
        <script type="text/template" id="sortable-group-template">
            <div class="sortable-group" data-group-id="{{ id }}">
                <div class="group-header">
                    <div class="group-info">
                        <span class="group-handle">
                            <span class="dashicons dashicons-menu"></span>
                        </span>
                        <input type="text"
                            class="group-title"
                            name="{{ $field['name'] }}[{{ '{{ id }}' }}][title]"
                            value="{{ title }}"
                            placeholder="Group title"
                        >
                    </div>
                    <div class="group-actions">
                        <button type="button" class="toggle-group" title="Toggle Group">
                            <span class="dashicons dashicons-arrow-down-alt2"></span>
                        </button>
                        <button type="button" class="remove-group" title="Remove Group">
                            <span class="dashicons dashicons-trash"></span>
                        </button>
                    </div>
                </div>
                <div class="group-items sortable-list"></div>
            </div>
        </script>
    @endif
</div>