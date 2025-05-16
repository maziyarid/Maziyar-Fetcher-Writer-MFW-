<?php
/**
 * HTML Helper Class
 * 
 * Provides utility methods for HTML generation and manipulation.
 * Handles common HTML operations with proper escaping and validation.
 *
 * @package MFW
 * @subpackage Helpers
 * @since 1.0.0
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_Html_Helper {
    /**
     * Last operation timestamp
     *
     * @var string
     */
    private static $last_operation = '2025-05-14 07:20:47';

    /**
     * Last operator
     *
     * @var string
     */
    private static $last_operator = 'maziyarid';

    /**
     * Self-closing HTML tags
     *
     * @var array
     */
    private static $void_elements = [
        'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input',
        'link', 'meta', 'param', 'source', 'track', 'wbr'
    ];

    /**
     * Create HTML element
     *
     * @param string $tag Element tag
     * @param array $attributes Element attributes
     * @param string|array $content Element content
     * @return string HTML element
     */
    public static function element($tag, $attributes = [], $content = '') {
        $tag = strtolower($tag);
        $html = '<' . $tag;

        // Add attributes
        if (!empty($attributes)) {
            $html .= self::attributes($attributes);
        }

        // Handle void elements
        if (in_array($tag, self::$void_elements)) {
            return $html . ' />';
        }

        $html .= '>';

        // Add content
        if (is_array($content)) {
            $html .= implode('', $content);
        } else {
            $html .= $content;
        }

        return $html . '</' . $tag . '>';
    }

    /**
     * Create HTML attributes string
     *
     * @param array $attributes Attributes array
     * @return string Attributes string
     */
    public static function attributes($attributes) {
        $html = '';

        foreach ($attributes as $key => $value) {
            if (is_numeric($key)) {
                $html .= ' ' . $value;
            } elseif ($value === true) {
                $html .= ' ' . $key;
            } elseif ($value !== false && !is_null($value)) {
                $html .= ' ' . $key . '="' . esc_attr($value) . '"';
            }
        }

        return $html;
    }

    /**
     * Create link element
     *
     * @param string $url Link URL
     * @param string $text Link text
     * @param array $attributes Additional attributes
     * @return string Link element
     */
    public static function link($url, $text, $attributes = []) {
        $attributes['href'] = esc_url($url);
        return self::element('a', $attributes, esc_html($text));
    }

    /**
     * Create image element
     *
     * @param string $src Image URL
     * @param string $alt Alt text
     * @param array $attributes Additional attributes
     * @return string Image element
     */
    public static function image($src, $alt = '', $attributes = []) {
        $attributes['src'] = esc_url($src);
        $attributes['alt'] = esc_attr($alt);
        return self::element('img', $attributes);
    }

    /**
     * Create list element
     *
     * @param array $items List items
     * @param array $attributes List attributes
     * @param string $type List type (ul|ol)
     * @return string List element
     */
    public static function list($items, $attributes = [], $type = 'ul') {
        $list_items = '';

        foreach ($items as $item) {
            if (is_array($item)) {
                $list_items .= self::element('li', $item['attributes'] ?? [], $item['content']);
            } else {
                $list_items .= self::element('li', [], $item);
            }
        }

        return self::element($type, $attributes, $list_items);
    }

    /**
     * Create table element
     *
     * @param array $headers Table headers
     * @param array $rows Table rows
     * @param array $attributes Table attributes
     * @return string Table element
     */
    public static function table($headers, $rows, $attributes = []) {
        $thead = '';
        $tbody = '';

        // Create header row
        if (!empty($headers)) {
            $header_cells = '';
            foreach ($headers as $header) {
                if (is_array($header)) {
                    $header_cells .= self::element('th', $header['attributes'] ?? [], $header['content']);
                } else {
                    $header_cells .= self::element('th', [], $header);
                }
            }
            $thead = self::element('thead', [], self::element('tr', [], $header_cells));
        }

        // Create body rows
        foreach ($rows as $row) {
            $cells = '';
            foreach ($row as $cell) {
                if (is_array($cell)) {
                    $cells .= self::element('td', $cell['attributes'] ?? [], $cell['content']);
                } else {
                    $cells .= self::element('td', [], $cell);
                }
            }
            $tbody .= self::element('tr', [], $cells);
        }
        $tbody = self::element('tbody', [], $tbody);

        return self::element('table', $attributes, $thead . $tbody);
    }

    /**
     * Create button element
     *
     * @param string $text Button text
     * @param array $attributes Button attributes
     * @return string Button element
     */
    public static function button($text, $attributes = []) {
        $attributes['type'] = $attributes['type'] ?? 'button';
        return self::element('button', $attributes, esc_html($text));
    }

    /**
     * Create div element
     *
     * @param string|array $content Div content
     * @param array $attributes Div attributes
     * @return string Div element
     */
    public static function div($content, $attributes = []) {
        return self::element('div', $attributes, $content);
    }

    /**
     * Create span element
     *
     * @param string $content Span content
     * @param array $attributes Span attributes
     * @return string Span element
     */
    public static function span($content, $attributes = []) {
        return self::element('span', $attributes, esc_html($content));
    }

    /**
     * Create heading element
     *
     * @param string $content Heading content
     * @param int $level Heading level (1-6)
     * @param array $attributes Heading attributes
     * @return string Heading element
     */
    public static function heading($content, $level = 2, $attributes = []) {
        $level = min(6, max(1, (int) $level));
        return self::element('h' . $level, $attributes, esc_html($content));
    }

    /**
     * Create paragraph element
     *
     * @param string $content Paragraph content
     * @param array $attributes Paragraph attributes
     * @return string Paragraph element
     */
    public static function paragraph($content, $attributes = []) {
        return self::element('p', $attributes, esc_html($content));
    }

    /**
     * Create label element
     *
     * @param string $content Label content
     * @param string $for Input ID
     * @param array $attributes Label attributes
     * @return string Label element
     */
    public static function label($content, $for = '', $attributes = []) {
        if ($for) {
            $attributes['for'] = $for;
        }
        return self::element('label', $attributes, esc_html($content));
    }

    /**
     * Create icon element
     *
     * @param string $icon Icon name/class
     * @param array $attributes Icon attributes
     * @return string Icon element
     */
    public static function icon($icon, $attributes = []) {
        $attributes['class'] = isset($attributes['class']) 
            ? $attributes['class'] . ' ' . $icon
            : $icon;
        return self::element('i', $attributes);
    }

    /**
     * Create badge element
     *
     * @param string $content Badge content
     * @param string $type Badge type (default|primary|success|warning|danger)
     * @param array $attributes Badge attributes
     * @return string Badge element
     */
    public static function badge($content, $type = 'default', $attributes = []) {
        $attributes['class'] = isset($attributes['class'])
            ? $attributes['class'] . ' badge badge-' . $type
            : 'badge badge-' . $type;
        return self::element('span', $attributes, esc_html($content));
    }
}