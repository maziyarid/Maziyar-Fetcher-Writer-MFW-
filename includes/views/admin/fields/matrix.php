<?php
/**
 * Matrix Field Template
 * Advanced matrix builder for complex content structures with AI assistance
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
$block_types = $field['block_types'] ?? [];
$max_blocks = $field['max_blocks'] ?? 0;
$min_blocks = $field['min_blocks'] ?? 0;
?>
<div class="mfw-matrix-field {{ $field['class'] ?? '' }}"
    data-max-blocks="{{ $max_blocks }}"
    data-min-blocks="{{ $min_blocks }}"
>
    <!-- Matrix Header -->
    <div class="matrix-header">
        <div class="matrix-info">
            <h3>{{ $field['title'] ?? 'Content Blocks' }}</h3>
            <span class="block-count">
                {{ count($value) }} {{ $max_blocks ? 'of ' . $max_blocks : '' }} blocks
            </span>
        </div>

        <!-- Matrix Actions -->
        <div class="matrix-actions">
            @if(!empty($field['enable_ai']))
                <div class="ai-assistant">
                    <button type="button" class="ai-suggest" title="AI Suggestions">
                        <span class="dashicons dashicons-admin-customizer"></span>
                        AI Assist
                    </button>
                    <div class="ai-options" style="display: none;">
                        <div class="content-analysis">
                            <textarea 
                                class="content-brief"
                                placeholder="Describe the content you want to create..."
                            ></textarea>
                        </div>
                        <div class="generation-options">
                            <div class="option-group">
                                <label>Content Type</label>
                                <select class="content-type">
                                    <option value="article">Article</option>
                                    <option value="product">Product</option>
                                    <option value="landing">Landing Page</option>
                                    <option value="custom">Custom</option>
                                </select>
                            </div>
                            <div class="option-group">
                                <label>Tone</label>
                                <select class="content-tone">
                                    <option value="professional">Professional</option>
                                    <option value="casual">Casual</option>
                                    <option value="formal">Formal</option>
                                    <option value="friendly">Friendly</option>
                                </select>
                            </div>
                        </div>
                        <button type="button" class="button button-primary generate-structure">
                            Generate Structure
                        </button>
                    </div>
                </div>
            @endif

            <!-- Block Type Selector -->
            <div class="block-selector">
                <button type="button" class="add-block">
                    <span class="dashicons dashicons-plus"></span>
                    Add Block
                </button>
                <div class="block-types" style="display: none;">
                    @foreach($block_types as $type => $config)
                        <div class="block-type" data-type="{{ $type }}">
                            <span class="block-icon">
                                <span class="dashicons dashicons-{{ $config['icon'] ?? 'block-default' }}"></span>
                            </span>
                            <div class="block-info">
                                <span class="block-title">{{ $config['title'] }}</span>
                                @if(!empty($config['description']))
                                    <span class="block-description">{{ $config['description'] }}</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- View Options -->
            <div class="view-options">
                <button type="button" class="toggle-preview" title="Toggle Preview">
                    <span class="dashicons dashicons-visibility"></span>
                </button>
                <button type="button" class="expand-all" title="Expand All">
                    <span class="dashicons dashicons-editor-expand"></span>
                </button>
            </div>
        </div>
    </div>

    <!-- Matrix Container -->
    <div class="matrix-container">
        <div class="blocks-container" data-sortable="true">
            @foreach($value as $block_index => $block)
                <?php $block_config = $block_types[$block['type']] ?? null; ?>
                @if($block_config)
                    <div class="matrix-block" data-type="{{ $block['type'] }}" data-index="{{ $block_index }}">
                        <div class="block-header">
                            <div class="block-handle">
                                <span class="dashicons dashicons-menu"></span>
                            </div>
                            <div class="block-info">
                                <span class="block-icon">
                                    <span class="dashicons dashicons-{{ $block_config['icon'] ?? 'block-default' }}"></span>
                                </span>
                                <span class="block-title">{{ $block_config['title'] }}</span>
                            </div>
                            <div class="block-actions">
                                <button type="button" class="toggle-block" title="Toggle Block">
                                    <span class="dashicons dashicons-arrow-down-alt2"></span>
                                </button>
                                <button type="button" class="clone-block" title="Clone Block">
                                    <span class="dashicons dashicons-admin-page"></span>
                                </button>
                                <button type="button" class="remove-block" title="Remove Block">
                                    <span class="dashicons dashicons-trash"></span>
                                </button>
                            </div>
                        </div>

                        <div class="block-content">
                            @foreach($block_config['fields'] as $field_key => $field_config)
                                <div class="field-wrapper field-type-{{ $field_config['type'] }}">
                                    <label>
                                        {{ $field_config['label'] }}
                                        @if(!empty($field_config['required']))
                                            <span class="required">*</span>
                                        @endif
                                    </label>

                                    {!! render_field([
                                        'type' => $field_config['type'],
                                        'name' => $field['name'] . "[{$block_index}][fields][{$field_key}]",
                                        'value' => $block['fields'][$field_key] ?? null,
                                        'id' => $field['id'] . "_{$block_index}_{$field_key}",
                                        ...$field_config
                                    ]) !!}
                                </div>
                            @endforeach
                        </div>

                        <div class="block-preview" style="display: none;"></div>
                    </div>
                @endif
            @endforeach
        </div>
    </div>

    <!-- Block Templates -->
    @foreach($block_types as $type => $config)
        <script type="text/template" id="block-template-{{ $type }}">
            <div class="matrix-block" data-type="{{ $type }}" data-index="{{ index }}">
                <!-- Block template content -->
            </div>
        </script>
    @endforeach

    <!-- AI Structure Preview Modal -->
    <div class="ai-preview-modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Suggested Structure</h3>
                <button type="button" class="close-modal">
                    <span class="dashicons dashicons-no-alt"></span>
                </button>
            </div>
            <div class="modal-body">
                <div class="structure-preview"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="button regenerate-structure">
                    Regenerate
                </button>
                <button type="button" class="button button-primary apply-structure">
                    Apply Structure
                </button>
            </div>
        </div>
    </div>

    <!-- Hidden Input -->
    <input type="hidden"
        name="{{ $field['name'] }}"
        value="{{ json_encode($value) }}"
        class="matrix-value"
    >
</div>