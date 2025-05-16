<?php
/**
 * Accordion Field Template
 * Collapsible sections with dynamic content and AI-powered organization
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
$sections = $field['sections'] ?? [];
$allow_multiple = !empty($field['allow_multiple']);
$sortable = !empty($field['sortable']);
?>
<div class="mfw-accordion-field {{ $field['class'] ?? '' }}"
    data-multiple="{{ $allow_multiple ? 'true' : 'false' }}"
    data-sortable="{{ $sortable ? 'true' : 'false' }}"
>
    <!-- Accordion Header -->
    <div class="accordion-header">
        <div class="accordion-info">
            <h3>{{ $field['title'] ?? 'Content Sections' }}</h3>
            <span class="section-count">{{ count($value) }} sections</span>
        </div>

        <!-- Accordion Actions -->
        <div class="accordion-actions">
            @if(!empty($field['enable_ai']))
                <div class="ai-assistant">
                    <button type="button" class="ai-organize" title="AI Organization">
                        <span class="dashicons dashicons-admin-customizer"></span>
                        AI Organize
                    </button>
                    <div class="ai-options" style="display: none;">
                        <div class="organization-options">
                            <div class="option-group">
                                <label>Organization Style</label>
                                <select class="org-style">
                                    <option value="logical">Logical Flow</option>
                                    <option value="chronological">Chronological</option>
                                    <option value="hierarchical">Hierarchical</option>
                                    <option value="categorical">Categorical</option>
                                </select>
                            </div>
                            <div class="option-group">
                                <label>Content Priority</label>
                                <select class="content-priority">
                                    <option value="importance">By Importance</option>
                                    <option value="complexity">By Complexity</option>
                                    <option value="relevance">By Relevance</option>
                                </select>
                            </div>
                        </div>
                        <button type="button" class="button button-primary organize-sections">
                            Optimize Organization
                        </button>
                    </div>
                </div>
            @endif

            <!-- Global Actions -->
            <div class="global-actions">
                <button type="button" class="expand-all" title="Expand All">
                    <span class="dashicons dashicons-editor-expand"></span>
                </button>
                <button type="button" class="collapse-all" title="Collapse All">
                    <span class="dashicons dashicons-editor-contract"></span>
                </button>
                @if($sortable)
                    <button type="button" class="toggle-sort" title="Toggle Sort Mode">
                        <span class="dashicons dashicons-sort"></span>
                    </button>
                @endif
            </div>
        </div>
    </div>

    <!-- Accordion Container -->
    <div class="accordion-container">
        @foreach($sections as $section_key => $section)
            <div class="accordion-section" 
                data-section="{{ $section_key }}"
                @if(!empty($value[$section_key]['state']))
                    data-state="{{ $value[$section_key]['state'] }}"
                @endif
            >
                <div class="section-header">
                    @if($sortable)
                        <div class="sort-handle">
                            <span class="dashicons dashicons-menu"></span>
                        </div>
                    @endif

                    <div class="section-title">
                        <span class="title-text">{{ $section['title'] }}</span>
                        @if(!empty($section['subtitle']))
                            <span class="subtitle-text">{{ $section['subtitle'] }}</span>
                        @endif
                    </div>

                    <div class="section-actions">
                        @if(!empty($field['enable_preview']))
                            <button type="button" class="preview-section" title="Preview">
                                <span class="dashicons dashicons-visibility"></span>
                            </button>
                        @endif
                        <button type="button" class="toggle-section" title="Toggle Section">
                            <span class="dashicons dashicons-arrow-down-alt2"></span>
                        </button>
                    </div>
                </div>

                <div class="section-content" style="display: none;">
                    @foreach($section['fields'] as $field_key => $field_config)
                        <div class="field-wrapper field-type-{{ $field_config['type'] }}">
                            <label>
                                {{ $field_config['label'] }}
                                @if(!empty($field_config['required']))
                                    <span class="required">*</span>
                                @endif
                            </label>

                            {!! render_field([
                                'type' => $field_config['type'],
                                'name' => $field['name'] . "[{$section_key}][fields][{$field_key}]",
                                'value' => $value[$section_key]['fields'][$field_key] ?? null,
                                'id' => $field['id'] . "_{$section_key}_{$field_key}",
                                ...$field_config
                            ]) !!}

                            @if(!empty($field_config['description']))
                                <div class="field-description">
                                    {{ $field_config['description'] }}
                                </div>
                            @endif
                        </div>
                    @endforeach

                    @if(!empty($section['summary']))
                        <div class="section-summary">
                            <div class="summary-header">
                                <span class="dashicons dashicons-info"></span>
                                Summary
                            </div>
                            <div class="summary-content">
                                {{ $section['summary'] }}
                            </div>
                        </div>
                    @endif
                </div>

                @if(!empty($field['show_footer']))
                    <div class="section-footer">
                        <div class="status-indicator">
                            <span class="status-icon"></span>
                            <span class="status-text">{{ $value[$section_key]['status'] ?? 'Not started' }}</span>
                        </div>
                        <div class="footer-actions">
                            <button type="button" class="reset-section">Reset</button>
                            <button type="button" class="save-section">Save Changes</button>
                        </div>
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    <!-- AI Organization Preview Modal -->
    <div class="ai-organization-modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Suggested Organization</h3>
                <button type="button" class="close-modal">
                    <span class="dashicons dashicons-no-alt"></span>
                </button>
            </div>
            <div class="modal-body">
                <div class="organization-preview"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="button regenerate-organization">
                    Regenerate
                </button>
                <button type="button" class="button button-primary apply-organization">
                    Apply Organization
                </button>
            </div>
        </div>
    </div>

    <!-- Hidden Input -->
    <input type="hidden"
        name="{{ $field['name'] }}"
        value="{{ json_encode($value) }}"
        class="accordion-value"
    >
</div>