<?php
/**
 * Rich Text Editor Field Template
 * 
 * @package MFW
 * @subpackage Views
 * @since 1.0.0
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

$editor_id = $field['id'];
$editor_settings = array_merge([
    'textarea_name' => $field['name'] ?? $field['id'],
    'editor_class' => 'mfw-richtext-editor ' . ($field['class'] ?? ''),
    'editor_height' => $field['height'] ?? 300,
    'textarea_rows' => $field['rows'] ?? 20,
    'teeny' => $field['teeny'] ?? false,
    'quicktags' => $field['quicktags'] ?? true,
    'media_buttons' => $field['media_buttons'] ?? true,
    'drag_drop_upload' => $field['drag_drop'] ?? true,
    'tinymce' => [
        'wp_autoresize_on' => true,
        'paste_as_text' => !empty($field['paste_as_text']),
        'content_css' => $field['content_css'] ?? '',
        'plugins' => $field['plugins'] ?? 'charmap,colorpicker,hr,lists,media,paste,tabfocus,textcolor,fullscreen,wordpress,wpautoresize,wpeditimage,wpemoji,wpgallery,wplink,wpdialogs,wptextpattern,wpview',
        'toolbar1' => $field['toolbar1'] ?? 'formatselect,bold,italic,bullist,numlist,blockquote,alignleft,aligncenter,alignright,link,wp_more,spellchecker,fullscreen,wp_adv',
        'toolbar2' => $field['toolbar2'] ?? 'strikethrough,hr,forecolor,pastetext,removeformat,charmap,outdent,indent,undo,redo,wp_help'
    ]
], $field['editor_settings'] ?? []);
?>

<div class="mfw-richtext-field">
    <?php wp_editor($value, $editor_id, $editor_settings); ?>
    
    @if(!empty($field['word_count']))
        <div class="word-count">
            Words: <span class="word-count-value">0</span>
        </div>
    @endif

    @if(!empty($field['char_count']))
        <div class="char-count">
            Characters: <span class="char-count-value">0</span>
        </div>
    @endif
</div>