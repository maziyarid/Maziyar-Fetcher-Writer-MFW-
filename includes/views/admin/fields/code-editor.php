<?php
/**
 * Code Editor Field Template
 * Advanced code editing with syntax highlighting and intellisense
 * 
 * @package MFW
 * @subpackage Views
 * @since 1.0.0
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

$language = $field['language'] ?? 'html';
$theme = $field['theme'] ?? 'vs-dark';
$value = esc_textarea($value);

// Default settings
$editor_settings = wp_parse_args($field['editor_settings'] ?? [], [
    'minimap' => [
        'enabled' => true
    ],
    'fontSize' => 14,
    'lineNumbers' => true,
    'wordWrap' => 'on',
    'tabSize' => 4,
    'insertSpaces' => true,
    'autoClosingBrackets' => true,
    'formatOnPaste' => true,
    'formatOnType' => true,
    'scrollBeyondLastLine' => false,
    'quickSuggestions' => true,
    'snippets' => true
]);

// Language specific settings
$language_settings = [
    'html' => [
        'emmet' => true,
        'format' => [
            'enable' => true,
            'wrapLineLength' => 120
        ]
    ],
    'css' => [
        'lint' => true,
        'validate' => true,
        'format' => [
            'enable' => true
        ]
    ],
    'javascript' => [
        'lint' => true,
        'validate' => true,
        'format' => [
            'enable' => true
        ]
    ],
    'php' => [
        'format' => [
            'enable' => true
        ]
    ]
];
?>
<div class="mfw-code-editor {{ $field['class'] ?? '' }}"
    data-language="{{ $language }}"
    data-theme="{{ $theme }}"
    data-settings="{{ esc_attr(json_encode($editor_settings)) }}"
    data-language-settings="{{ esc_attr(json_encode($language_settings[$language] ?? [])) }}"
>
    <!-- Editor Toolbar -->
    <div class="editor-toolbar">
        <div class="left-controls">
            @if(!empty($field['show_language_selector']))
                <select class="language-selector">
                    @foreach(['html', 'css', 'javascript', 'php', 'json', 'markdown', 'sql'] as $lang)
                        <option value="{{ $lang }}"
                            @if($language === $lang)
                                selected
                            @endif
                        >{{ strtoupper($lang) }}</option>
                    @endforeach
                </select>
            @endif

            @if(!empty($field['show_theme_selector']))
                <select class="theme-selector">
                    @foreach(['vs', 'vs-dark', 'hc-black'] as $editor_theme)
                        <option value="{{ $editor_theme }}"
                            @if($theme === $editor_theme)
                                selected
                            @endif
                        >{{ ucwords(str_replace('-', ' ', $editor_theme)) }}</option>
                    @endforeach
                </select>
            @endif
        </div>

        <div class="right-controls">
            @if(!empty($field['show_format']))
                <button type="button" class="format-code" title="Format Code">
                    <span class="dashicons dashicons-editor-alignleft"></span>
                </button>
            @endif

            @if(!empty($field['show_fullscreen']))
                <button type="button" class="toggle-fullscreen" title="Toggle Fullscreen">
                    <span class="dashicons dashicons-fullscreen-alt"></span>
                </button>
            @endif
        </div>
    </div>

    <!-- Code Editor Container -->
    <div class="editor-container">
        <div id="{{ $field['id'] }}_editor" class="monaco-editor"></div>
        <textarea
            name="{{ $field['name'] }}"
            id="{{ $field['id'] }}"
            style="display: none;"
        >{{ $value }}</textarea>
    </div>

    <!-- Bottom Bar -->
    <div class="editor-bottom-bar">
        <div class="left-info">
            <span class="cursor-position"></span>
            <span class="file-info">
                <span class="language-indicator">{{ strtoupper($language) }}</span>
                <span class="encoding">UTF-8</span>
            </span>
        </div>

        <div class="right-info">
            <span class="line-count"></span>
        </div>
    </div>

    <!-- Editor Settings -->
    @if(!empty($field['show_settings']))
        <div class="editor-settings" style="display: none;">
            <div class="settings-header">
                <h3>Editor Settings</h3>
                <button type="button" class="close-settings">
                    <span class="dashicons dashicons-no-alt"></span>
                </button>
            </div>

            <div class="settings-body">
                <!-- Visual Settings -->
                <div class="settings-section">
                    <h4>Visual</h4>
                    <label>
                        <input type="checkbox"
                            class="setting-minimap"
                            @if($editor_settings['minimap']['enabled'])
                                checked
                            @endif
                        >
                        Show Minimap
                    </label>
                    <label>
                        <input type="checkbox"
                            class="setting-line-numbers"
                            @if($editor_settings['lineNumbers'])
                                checked
                            @endif
                        >
                        Show Line Numbers
                    </label>
                    <div class="font-size-setting">
                        <label>Font Size</label>
                        <input type="number"
                            class="setting-font-size"
                            value="{{ $editor_settings['fontSize'] }}"
                            min="8"
                            max="30"
                        >
                    </div>
                </div>

                <!-- Editor Settings -->
                <div class="settings-section">
                    <h4>Editor</h4>
                    <label>
                        <input type="checkbox"
                            class="setting-word-wrap"
                            @if($editor_settings['wordWrap'] === 'on')
                                checked
                            @endif
                        >
                        Word Wrap
                    </label>
                    <label>
                        <input type="checkbox"
                            class="setting-auto-closing"
                            @if($editor_settings['autoClosingBrackets'])
                                checked
                            @endif
                        >
                        Auto Close Brackets
                    </label>
                    <div class="tab-size-setting">
                        <label>Tab Size</label>
                        <input type="number"
                            class="setting-tab-size"
                            value="{{ $editor_settings['tabSize'] }}"
                            min="1"
                            max="8"
                        >
                    </div>
                </div>

                <!-- Intelligence Settings -->
                <div class="settings-section">
                    <h4>Intelligence</h4>
                    <label>
                        <input type="checkbox"
                            class="setting-suggestions"
                            @if($editor_settings['quickSuggestions'])
                                checked
                            @endif
                        >
                        Quick Suggestions
                    </label>
                    <label>
                        <input type="checkbox"
                            class="setting-format-paste"
                            @if($editor_settings['formatOnPaste'])
                                checked
                            @endif
                        >
                        Format on Paste
                    </label>
                    <label>
                        <input type="checkbox"
                            class="setting-snippets"
                            @if($editor_settings['snippets'])
                                checked
                            @endif
                        >
                        Enable Snippets
                    </label>
                </div>
            </div>

            <div class="settings-footer">
                <button type="button" class="button reset-settings">
                    Reset to Defaults
                </button>
                <button type="button" class="button button-primary save-settings">
                    Save Settings
                </button>
            </div>
        </div>
    @endif

    <!-- Custom Snippets -->
    @if(!empty($field['snippets']))
        <script type="text/json" class="editor-snippets">
            {!! json_encode($field['snippets']) !!}
        </script>
    @endif

    <!-- Error/Warning Panel -->
    <div class="editor-diagnostics" style="display: none;">
        <div class="diagnostics-header">
            <span class="error-count">0 Errors</span>
            <span class="warning-count">0 Warnings</span>
            <button type="button" class="close-diagnostics">
                <span class="dashicons dashicons-no"></span>
            </button>
        </div>
        <div class="diagnostics-content"></div>
    </div>
</div>