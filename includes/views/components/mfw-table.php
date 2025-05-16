<?php
/**
 * Table Component Template
 * 
 * @package MFW
 * @subpackage Views
 * @since 1.0.0
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="mfw-table-wrapper {{ $attributes['class'] ?? '' }}">
    @if(!empty($title) || !empty($actions))
        <div class="mfw-table-header">
            @if(!empty($title))
                <h3 class="mfw-table-title">{{ $title }}</h3>
            @endif
            @if(!empty($actions))
                <div class="mfw-table-actions">
                    {{ $actions }}
                </div>
            @endif
        </div>
    @endif

    <table class="mfw-table">
        @if(!empty($headers))
            <thead>
                <tr>
                    @foreach($headers as $header)
                        <th {!! $header['attributes'] ?? '' !!}>
                            @if(!empty($header['sortable']))
                                <a href="{{ $header['sort_url'] }}" class="mfw-sort-link">
                                    {{ $header['label'] }}
                                    @if($header['sorted'])
                                        <span class="mfw-sort-icon mfw-sort-{{ $header['direction'] }}"></span>
                                    @endif
                                </a>
                            @else
                                {{ $header['label'] }}
                            @endif
                        </th>
                    @endforeach
                </tr>
            </thead>
        @endif

        <tbody>
            {{ $slot }}
        </tbody>

        @if(!empty($footer))
            <tfoot>
                {{ $footer }}
            </tfoot>
        @endif
    </table>

    @if(!empty($pagination))
        <div class="mfw-table-pagination">
            {{ $pagination }}
        </div>
    @endif
</div>