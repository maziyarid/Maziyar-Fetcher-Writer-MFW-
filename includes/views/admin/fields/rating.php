<?php
/**
 * Rating Field Template
 * 
 * @package MFW
 * @subpackage Views
 * @since 1.0.0
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

$max_rating = $field['max_rating'] ?? 5;
$step = $field['step'] ?? 1;
$value = floatval($value);
$show_numbers = !empty($field['show_numbers']);
$allow_half = !empty($field['allow_half']);
$icon_empty = $field['icon_empty'] ?? 'star-empty';
$icon_half = $field['icon_half'] ?? 'star-half';
$icon_filled = $field['icon_filled'] ?? 'star-filled';
$icon_size = $field['icon_size'] ?? 20;
?>
<div class="mfw-rating-field {{ $field['class'] ?? '' }}"
    data-max="{{ $max_rating }}"
    data-step="{{ $step }}"
    data-allow-half="{{ $allow_half ? 'true' : 'false' }}"
>
    <div class="rating-container"
        style="--icon-size: {{ $icon_size }}px;"
    >
        <div class="rating-stars">
            @for($i = $step; $i <= $max_rating; $i += $step)
                <?php 
                $is_active = $value >= $i;
                $is_half = $allow_half && ($value + 0.5) >= $i && $value < $i;
                ?>
                <button type="button"
                    class="star-button {{ $is_active ? 'active' : '' }} {{ $is_half ? 'half' : '' }}"
                    data-value="{{ $i }}"
                    title="{{ $i }}"
                >
                    <span class="star-empty">
                        <span class="dashicons dashicons-{{ $icon_empty }}"></span>
                    </span>
                    @if($allow_half)
                        <span class="star-half">
                            <span class="dashicons dashicons-{{ $icon_half }}"></span>
                        </span>
                    @endif
                    <span class="star-filled">
                        <span class="dashicons dashicons-{{ $icon_filled }}"></span>
                    </span>
                </button>
            @endfor
        </div>

        @if($show_numbers)
            <div class="rating-number">
                <input type="number"
                    class="rating-input"
                    value="{{ $value }}"
                    min="0"
                    max="{{ $max_rating }}"
                    step="{{ $allow_half ? '0.5' : '1' }}"
                >
                <span class="rating-max">/ {{ $max_rating }}</span>
            </div>
        @endif
    </div>

    @if(!empty($field['show_labels']))
        <div class="rating-labels">
            @foreach($field['labels'] ?? [] as $rating => $label)
                <div class="rating-label {{ $value == $rating ? 'active' : '' }}"
                    data-rating="{{ $rating }}"
                >
                    {{ $label }}
                </div>
            @endforeach
        </div>
    @endif

    @if(!empty($field['show_description']))
        <div class="rating-description">
            @foreach($field['descriptions'] ?? [] as $rating => $description)
                <div class="description-item {{ $value == $rating ? 'active' : '' }}"
                    data-rating="{{ $rating }}"
                >
                    {{ $description }}
                </div>
            @endforeach
        </div>
    @endif

    @if(!empty($field['show_colors']))
        <style>
            @foreach($field['colors'] ?? [] as $rating => $color)
                .mfw-rating-field[data-field-id="{{ $field['id'] }}"] .star-button[data-value="{{ $rating }}"].active .star-filled .dashicons {
                    color: {{ $color }};
                }
            @endforeach
        </style>
    @endif

    @if(!empty($field['show_hover_preview']))
        <div class="hover-preview">
            <div class="preview-stars">
                @for($i = 1; $i <= $max_rating; $i++)
                    <span class="preview-star">
                        <span class="dashicons dashicons-{{ $icon_empty }}"></span>
                    </span>
                @endfor
            </div>
            <div class="preview-label"></div>
        </div>
    @endif

    @if(!empty($field['show_clear']))
        <button type="button" class="clear-rating">
            <span class="dashicons dashicons-dismiss"></span>
            Clear
        </button>
    @endif

    <!-- Hidden input for form submission -->
    <input type="hidden"
        id="{{ $field['id'] }}"
        name="{{ $field['name'] }}"
        value="{{ $value }}"
        @if(!empty($field['required']))
            required
        @endif
    >

    @if(!empty($field['show_stats']))
        <div class="rating-stats">
            <?php
            $stats = $field['stats'] ?? [];
            $total_ratings = array_sum($stats);
            ?>
            @foreach($stats as $rating => $count)
                <div class="stat-row">
                    <div class="stat-label">{{ $rating }}</div>
                    <div class="stat-bar-wrapper">
                        <div class="stat-bar" style="width: {{ ($count / $total_ratings) * 100 }}%"></div>
                    </div>
                    <div class="stat-count">{{ $count }}</div>
                </div>
            @endforeach
            <div class="stat-summary">
                <div class="average-rating">
                    Average: <strong>{{ number_format(array_sum(array_map(function($rating, $count) {
                        return $rating * $count;
                    }, array_keys($stats), $stats)) / $total_ratings, 1) }}</strong>
                </div>
                <div class="total-ratings">
                    Total ratings: <strong>{{ $total_ratings }}</strong>
                </div>
            </div>
        </div>
    @endif
</div>