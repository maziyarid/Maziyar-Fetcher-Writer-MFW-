<?php
/**
 * Tags Field Template
 * Advanced tag input with AI suggestions and auto-completion
 * 
 * @package MFW
 * @subpackage Views
 * @since 1.0.0
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

$value = is_array($value) ? $value : explode(',', $value);
$max_tags = $field['max_tags'] ?? 0;
$min_length = $field['min_length'] ?? 2;
$allow_create = !empty($field['allow_create']);
$show_count = !empty($field['show_count']);
?>
<div class="mfw-tags-field {{ $field['class'] ?? '' }}"
    data-max-tags="{{ $max_tags }}"
    data-min-length="{{ $min_length }}"
    data-allow-create="{{ $allow_create ? 'true' : 'false' }}"
>
    <!-- AI Assistant Button -->
    @if(!empty($field['enable_ai']))
        <div class="ai-assistant">
            <button type="button" class="ai-suggest-tags" title="Get AI suggestions">
                <span class="dashicons dashicons-admin-customizer"></span>
                AI Suggest
            </button>
            <div class="ai-options">
                <select class="suggestion-type">
                    <option value="seo">SEO-optimized</option>
                    <option value="trending">Trending</option>
                    <option value="related">Related</option>
                    <option value="categorical">Categorical</option>
                </select>
            </div>
        </div>
    @endif

    <!-- Tags Input -->
    <div class="tags-input-wrapper">
        <div class="selected-tags">
            @foreach($value as $tag)
                <span class="tag-item">
                    <span class="tag-text">{{ $tag }}</span>
                    <button type="button" class="remove-tag">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                    @if($show_count)
                        <span class="tag-count">
                            {{ get_tag_count($tag) }}
                        </span>
                    @endif
                </span>
            @endforeach
        </div>

        <input type="text"
            class="tag-input"
            placeholder="{{ $field['placeholder'] ?? 'Add tags...' }}"
            @if($max_tags && count($value) >= $max_tags)
                disabled
            @endif
        >
    </div>

    <!-- Suggestions Area -->
    <div class="tag-suggestions" style="display: none;">
        <div class="suggestion-tabs">
            <button type="button" class="tab active" data-tab="popular">
                Popular
            </button>
            <button type="button" class="tab" data-tab="recent">
                Recent
            </button>
            @if(!empty($field['enable_ai']))
                <button type="button" class="tab" data-tab="ai">
                    AI Suggestions
                </button>
            @endif
        </div>

        <div class="suggestion-content"></div>
    </div>

    <!-- Hidden Input -->
    <input type="hidden"
        name="{{ $field['name'] }}"
        value="{{ implode(',', $value) }}"
        class="tags-value"
    >
</div>