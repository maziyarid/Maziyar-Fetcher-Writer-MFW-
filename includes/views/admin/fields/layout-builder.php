<?php
/**
 * Layout Builder Field Template
 * 
 * @package MFW
 * @subpackage Views
 * @since 1.0.0
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

$blocks = $field['blocks'] ?? [];
$value = is_array($value) ? $value : [];
$columns = $field['columns'] ?? 12;
$gutter = $field['gutter'] ?? 20;
$row_height = $field['row_height'] ?? 60;
$snap_to_grid = !empty($field['snap_to_grid']);
?>
<div class="mfw-layout-builder {{ $field['class'] ?? '' }}"
    data-columns="{{ $columns }}"
    data-gutter="{{ $gutter }}"
    data-row-height="{{ $row_height }}"
    data-snap-grid="{{ $snap_to_grid ? 'true' : 'false' }}"
>
    <!-- Toolbar -->
    <div class="builder-toolbar">
        <div class="left-actions">
            @if(!empty($field['show_responsive']))
                <div class="device-switcher">
                    @foreach(['desktop', 'tablet', 'mobile'] as $device)
                        <button type="button" 
                            class="device-button {{ $device === 'desktop' ? 'active' : '' }}"
                            data-device="{{ $device }}"
                            title="Show {{ ucfirst($device) }} Layout"
                        >
                            <span class="dashicons dashicons-{{ $device }}"></span>
                        </button>
                    @endforeach
                </div>
            @endif

            @if(!empty($field['show_preview']))
                <button type="button" class="preview-layout" title="Preview Layout">
                    <span class="dashicons dashicons-visibility"></span>
                    Preview
                </button>
            @endif
        </div>

        <div class="right-actions">
            <button type="button" class="clear-layout" title="Clear Layout">
                <span class="dashicons dashicons-trash"></span>
                Clear
            </button>
            @if(!empty($field['show_templates']))
                <div class="template-selector">
                    <select class="layout-template">
                        <option value="">Load Template...</option>
                        @foreach($field['templates'] ?? [] as $template_id => $template)
                            <option value="{{ $template_id }}">{{ $template['name'] }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
        </div>
    </div>

    <!-- Block Palette -->
    <div class="block-palette">
        <div class="palette-header">
            <h3>Available Blocks</h3>
            @if(!empty($field['show_search']))
                <div class="block-search">
                    <input type="text" 
                        placeholder="Search blocks..."
                        class="search-input"
                    >
                </div>
            @endif
        </div>

        <div class="block-categories">
            @foreach($blocks as $category => $category_blocks)
                <div class="block-category">
                    <div class="category-header">
                        <h4>{{ $category }}</h4>
                        <button type="button" class="toggle-category">
                            <span class="dashicons dashicons-arrow-down-alt2"></span>
                        </button>
                    </div>
                    <div class="category-blocks">
                        @foreach($category_blocks as $block_id => $block)
                            <div class="block-item"
                                data-block-id="{{ $block_id }}"
                                data-min-width="{{ $block['min_width'] ?? 1 }}"
                                data-min-height="{{ $block['min_height'] ?? 1 }}"
                                draggable="true"
                            >
                                @if(!empty($block['icon']))
                                    <span class="block-icon">
                                        <span class="dashicons dashicons-{{ $block['icon'] }}"></span>
                                    </span>
                                @endif
                                <span class="block-label">{{ $block['label'] }}</span>
                                @if(!empty($block['description']))
                                    <span class="block-description">
                                        {{ $block['description'] }}
                                    </span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <!-- Layout Grid -->
    <div class="layout-grid">
        <div class="grid-container">
            <!-- Grid background -->
            <div class="grid-background">
                @for($i = 0; $i < $columns; $i++)
                    <div class="grid-column"></div>
                @endfor
            </div>

            <!-- Blocks container -->
            <div class="blocks-container">
                @foreach($value as $block_instance)
                    <div class="layout-block"
                        data-block-id="{{ $block_instance['block_id'] }}"
                        data-instance-id="{{ $block_instance['instance_id'] }}"
                        style="
                            grid-column: span {{ $block_instance['width'] }};
                            grid-row: span {{ $block_instance['height'] }};
                            transform: translate(
                                {{ $block_instance['x'] * $gutter }}px,
                                {{ $block_instance['y'] * $row_height }}px
                            );"
                    >
                        <div class="block-content">
                            <div class="block-header">
                                <span class="block-title">
                                    {{ $blocks[$block_instance['category']][$block_instance['block_id']]['label'] }}
                                </span>
                                <div class="block-actions">
                                    <button type="button" class="edit-block" title="Edit Block">
                                        <span class="dashicons dashicons-edit"></span>
                                    </button>
                                    <button type="button" class="clone-block" title="Clone Block">
                                        <span class="dashicons dashicons-admin-page"></span>
                                    </button>
                                    <button type="button" class="remove-block" title="Remove Block">
                                        <span class="dashicons dashicons-no"></span>
                                    </button>
                                </div>
                            </div>
                            <div class="block-settings">
                                {!! $blocks[$block_instance['category']][$block_instance['block_id']]['content'] ?? '' !!}
                            </div>
                            <div class="resize-handle"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Empty state -->
        <div class="empty-state" 
            @if(!empty($value))
                style="display: none;"
            @endif
        >
            <div class="empty-message">
                <span class="dashicons dashicons-layout"></span>
                <h3>{{ $field['empty_message'] ?? 'Drag blocks here to create your layout' }}</h3>
            </div>
        </div>
    </div>

    <!-- Block Editor Modal -->
    <div class="block-editor-modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Block</h2>
                <button type="button" class="close-modal">
                    <span class="dashicons dashicons-no-alt"></span>
                </button>
            </div>
            <div class="modal-body"></div>
            <div class="modal-footer">
                <button type="button" class="button cancel-edit">Cancel</button>
                <button type="button" class="button button-primary save-block">Save Changes</button>
            </div>
        </div>
    </div>

    <!-- Hidden input for layout data -->
    <input type="hidden"
        name="{{ $field['name'] }}"
        value="{{ json_encode($value) }}"
        class="layout-data"
    >
</div>

<!-- Block Templates -->
<script type="text/template" id="empty-block-template">
    <div class="layout-block"
        data-block-id="{{ id }}"
        data-instance-id="{{ instance_id }}"
    >
        <div class="block-content">
            <div class="block-header">
                <span class="block-title">{{ label }}</span>
                <div class="block-actions">
                    <button type="button" class="edit-block" title="Edit Block">
                        <span class="dashicons dashicons-edit"></span>
                    </button>
                    <button type="button" class="clone-block" title="Clone Block">
                        <span class="dashicons dashicons-admin-page"></span>
                    </button>
                    <button type="button" class="remove-block" title="Remove Block">
                        <span class="dashicons dashicons-no"></span>
                    </button>
                </div>
            </div>
            <div class="block-settings"></div>
            <div class="resize-handle"></div>
        </div>
    </div>
</script>