<?php
/**
 * Code Editor Field Template
 * 
 * @package MFW
 * @subpackage Views
 * @since 1.0.0
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

$editor_settings = array_merge([
    'type' => 'text/plain',
    'lineNumbers' => true,
    'lineWrapping' => true,
    'foldGutter' => true,
    'gutters' => ['CodeMirror-linenumbers', 'CodeMirror-foldgutter'],
    'matchBrackets' => true,
    'autoCloseBrackets' => true,
    'matchTags' => true,
    'autoCloseTags' => true,
    'extraKeys' => [
        'Ctrl-Space' => 'autocomplete',
        'Ctrl-/' => 'toggleComment',
        'Cmd-/' => 'toggleComment',
        'Alt-F' => 'findPersistent',
        'Ctrl-F' => 'findPersistent'
    ],
    'theme' => 'default',
    'mode' => $field['language'] ?? 'text/plain'
], $field['editor_settings'] ?? []);
?>

<div class="mfw-code-field {{ $field['class'] ?? '' }}"
    data-editor-settings="{{ json_encode($editor_settings) }}"
>
    <div class="code-editor-toolbar">
        @if(!empty($field['show_language_selector']))
            <select class="language-selector">
                @foreach($field['languages'] as $lang)
                    <option value="{{ $lang['mode'] }}"
                        @if(($field['language'] ?? '') === $lang['mode'])
                            selected
                        @endif
                    >
                        {{ $lang['label'] }}
                    </option>
                @endforeach
            </select>
        @endif

        @if(!empty($field['show_theme_selector']))
            <select class="theme-selector">
                @foreach($field['themes'] as $theme)
                    <option value="{{ $theme['name'] }}"
                        @if(($editor_settings['theme'] ?? 'default') === $theme['name'])
                            selected
                        @endif
                    >
                        {{ $theme['label'] }}
                    </option>
                @endforeach
            </select>
        @endif

        <div class="toolbar-actions">
            @if(!empty($field['show_fullscreen']))
                <button type="button" class="button toggle-fullscreen" title="Toggle fullscreen">
                    <span class="dashicons dashicons-fullscreen-alt"></span>
                </button>
            @endif
            @if(!empty($field['show_line_wrap']))
                <button type="button" class="button toggle-wrap" title="Toggle line wrap">
                    <span class="dashicons dashicons-text-page"></span>
                </button>
            @endif
            @if(!empty($field['show_format']))
                <button type="button" class="button format-code" title="Format code">
                    <span class="dashicons dashicons-editor-alignleft"></span>
                </button>
            @endif
        </div>
    </div>

    <textarea
        id="{{ $field['id'] }}"
        name="{{ $field['name'] ?? $field['id'] }}"
        class="code-editor"
        @if(!empty($field['required']))
            required
        @endif
        @if(!empty($field['readonly']))
            readonly
        @endif
        @if(!empty($field['disabled']))
            disabled
        @endif
        {!! $field['attributes'] ?? '' !!}
    >{{ $value }}</textarea>

    <div class="editor-footer">
        <div class="editor-info">
            <span class="cursor-position"></span>
            <span class="file-info">
                <span class="line-count"></span> lines |
                <span class="char-count"></span> characters
            </span>
        </div>
        @if(!empty($field['show_encoding']))
            <select class="encoding-selector">
                <option value="UTF-8">UTF-8</option>
                <option value="ISO-8859-1">ISO-8859-1</option>
                <option value="Windows-1252">Windows-1252</option>
            </select>
        @endif
    </div>
</div>