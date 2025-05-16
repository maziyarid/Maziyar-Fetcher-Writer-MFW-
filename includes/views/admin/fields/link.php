<?php
/**
 * Link Field Template
 * 
 * @package MFW
 * @subpackage Views
 * @since 1.0.0
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

$value = wp_parse_args($value, [
    'url' => '',
    'text' => '',
    'target' => '_self',
    'rel' => [],
    'title' => '',
    'class' => ''
]);
?>
<div class="mfw-link-field {{ $field['class'] ?? '' }}">
    <div class="link-preview" 
        @if(empty($value['url']))
            style="display: none;"
        @endif
    >
        <a href="{{ $value['url'] }}" 
            class="preview-link"
            target="{{ $value['target'] }}"
            @if(!empty($value['title']))
                title="{{ $value['title'] }}"
            @endif
        >
            <span class="link-icon">
                <span class="dashicons dashicons-admin-links"></span>
            </span>
            <span class="link-text">
                {{ $value['text'] ?: $value['url'] }}
            </span>
        </a>
        <div class="preview-actions">
            <button type="button" class="edit-link" title="Edit link">
                <span class="dashicons dashicons-edit"></span>
            </button>
            <button type="button" class="remove-link" title="Remove link">
                <span class="dashicons dashicons-no"></span>
            </button>
        </div>
    </div>

    <button type="button" 
        class="button add-link"
        @if(!empty($value['url']))
            style="display: none;"
        @endif
    >
        <span class="dashicons dashicons-plus"></span>
        {{ $field['add_text'] ?? 'Add Link' }}
    </button>

    <div class="link-editor" style="display: none;">
        <div class="editor-header">
            <h3>{{ $field['editor_title'] ?? 'Link Settings' }}</h3>
            <button type="button" class="close-editor">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        </div>

        <div class="editor-content">
            <div class="link-field">
                <label>URL</label>
                <div class="url-input-wrapper">
                    @if(!empty($field['show_protocol']))
                        <select class="link-protocol" tabindex="-1">
                            <option value="http://">http://</option>
                            <option value="https://" selected>https://</option>
                            <option value="tel:">tel:</option>
                            <option value="mailto:">mailto:</option>
                            <option value="/">/</option>
                        </select>
                    @endif
                    <input type="url"
                        class="link-url"
                        name="{{ $field['name'] }}[url]"
                        value="{{ $value['url'] }}"
                        placeholder="Enter URL"
                        @if(!empty($field['required']))
                            required
                        @endif
                    >
                </div>
            </div>

            @if(!empty($field['show_text']))
                <div class="link-field">
                    <label>Link Text</label>
                    <input type="text"
                        class="link-text"
                        name="{{ $field['name'] }}[text]"
                        value="{{ $value['text'] }}"
                        placeholder="Enter link text"
                    >
                </div>
            @endif

            @if(!empty($field['show_title']))
                <div class="link-field">
                    <label>Title</label>
                    <input type="text"
                        class="link-title"
                        name="{{ $field['name'] }}[title]"
                        value="{{ $value['title'] }}"
                        placeholder="Enter title attribute"
                    >
                </div>
            @endif

            <div class="link-options">
                @if(!empty($field['show_target']))
                    <div class="link-field">
                        <label>
                            <input type="checkbox"
                                class="link-target"
                                @if($value['target'] === '_blank')
                                    checked
                                @endif
                            >
                            Open in new tab
                        </label>
                    </div>
                @endif

                @if(!empty($field['show_rel']))
                    <div class="link-field">
                        <label>Link Relationship</label>
                        <div class="rel-options">
                            @foreach(['nofollow', 'sponsored', 'ugc'] as $rel)
                                <label>
                                    <input type="checkbox"
                                        class="link-rel"
                                        value="{{ $rel }}"
                                        @if(in_array($rel, $value['rel']))
                                            checked
                                        @endif
                                    >
                                    {{ ucfirst($rel) }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if(!empty($field['show_class']))
                    <div class="link-field">
                        <label>CSS Class</label>
                        <input type="text"
                            class="link-class"
                            name="{{ $field['name'] }}[class]"
                            value="{{ $value['class'] }}"
                            placeholder="Enter CSS class(es)"
                        >
                    </div>
                @endif
            </div>
        </div>

        <div class="editor-footer">
            <button type="button" class="button cancel-link">
                Cancel
            </button>
            <button type="button" class="button button-primary save-link">
                Save Link
            </button>
        </div>
    </div>

    <!-- Hidden fields for form submission -->
    <input type="hidden" name="{{ $field['name'] }}[target]" value="{{ $value['target'] }}">
    <input type="hidden" name="{{ $field['name'] }}[rel]" value="{{ implode(',', $value['rel']) }}">
</div>