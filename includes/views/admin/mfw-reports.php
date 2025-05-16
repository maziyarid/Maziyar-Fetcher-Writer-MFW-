<?php
/**
 * Admin Reports Template
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

@extends('layouts.admin')

@section('page-actions')
    <div class="mfw-page-actions">
        <button type="button" class="button" data-action="refresh-data">
            <span class="dashicons dashicons-update"></span> Refresh Data
        </button>
        <button type="button" class="button" data-modal="mfw-export-report">
            <span class="dashicons dashicons-download"></span> Export Report
        </button>
        <button type="button" class="button" data-action="schedule-report">
            <span class="dashicons dashicons-calendar-alt"></span> Schedule Report
        </button>
    </div>
@endsection

@section('content')
    <!-- Report Filters -->
    <x-card class="mfw-report-filters">
        <x-form method="get" class="mfw-filter-form">
            <input type="hidden" name="page" value="mfw-reports">
            
            <div class="mfw-filter-grid">
                <!-- Date Range -->
                <div class="mfw-form-field">
                    <label for="date_range">Date Range</label>
                    <select name="date_range" id="date_range">
                        <option value="today" {{ $current_range === 'today' ? 'selected' : '' }}>Today</option>
                        <option value="yesterday" {{ $current_range === 'yesterday' ? 'selected' : '' }}>Yesterday</option>
                        <option value="last7" {{ $current_range === 'last7' ? 'selected' : '' }}>Last 7 Days</option>
                        <option value="last30" {{ $current_range === 'last30' ? 'selected' : '' }}>Last 30 Days</option>
                        <option value="this_month" {{ $current_range === 'this_month' ? 'selected' : '' }}>This Month</option>
                        <option value="last_month" {{ $current_range === 'last_month' ? 'selected' : '' }}>Last Month</option>
                        <option value="custom" {{ $current_range === 'custom' ? 'selected' : '' }}>Custom Range</option>
                    </select>
                    
                    <div class="custom-range" style="{{ $current_range === 'custom' ? 'display:block' : 'display:none' }}">
                        <input type="date" name="start_date" value="{{ $start_date }}" max="{{ $end_date }}">
                        <span>to</span>
                        <input type="date" name="end_date" value="{{ $end_date }}" min="{{ $start_date }}">
                    </div>
                </div>

                <!-- Comparison -->
                <div class="mfw-form-field">
                    <label>
                        <input type="checkbox" name="compare" value="1" {{ $compare ? 'checked' : '' }}>
                        Compare with previous period
                    </label>
                </div>

                <!-- Additional Filters -->
                @foreach($report_filters as $filter)
                    <div class="mfw-form-field">
                        @include("admin.filters.{$filter['type']}", [
                            'filter' => $filter,
                            'value' => $current_filters[$filter['id']] ?? ''
                        ])
                    </div>
                @endforeach

                <div class="mfw-filter-actions">
                    <button type="submit" class="button button-primary">
                        Apply Filters
                    </button>
                    <a href="{{ $reset_url }}" class="button">
                        Reset
                    </a>
                </div>
            </div>
        </x-form>
    </x-card>

    <!-- Report Overview -->
    <div class="mfw-report-overview">
        @foreach($overview_cards as $card)
            <x-card class="mfw-overview-card {{ $card['trend'] }}">
                <div class="overview-header">
                    <h3>{{ $card['title'] }}</h3>
                    @if(!empty($card['help']))
                        <span class="dashicons dashicons-editor-help" title="{{ $card['help'] }}"></span>
                    @endif
                </div>
                <div class="overview-content">
                    <div class="primary-value">{{ $card['value'] }}</div>
                    @if($compare && !empty($card['comparison']))
                        <div class="comparison-value {{ $card['comparison']['class'] }}">
                            <span class="dashicons dashicons-{{ $card['comparison']['icon'] }}"></span>
                            {{ $card['comparison']['value'] }}
                        </div>
                    @endif
                </div>
            </x-card>
        @endforeach
    </div>

    <!-- Report Charts -->
    <div class="mfw-report-charts">
        @foreach($charts as $chart)
            <x-card class="mfw-chart-card">
                @slot('title')
                    {{ $chart['title'] }}
                @endslot

                @slot('actions')
                    <button type="button" class="button" data-action="customize-chart" data-chart="{{ $chart['id'] }}">
                        <span class="dashicons dashicons-edit"></span>
                    </button>
                    <button type="button" class="button" data-action="download-chart" data-chart="{{ $chart['id'] }}">
                        <span class="dashicons dashicons-download"></span>
                    </button>
                @endslot

                <div class="mfw-chart" 
                    id="chart-{{ $chart['id'] }}"
                    data-type="{{ $chart['type'] }}"
                    data-options="{{ json_encode($chart['options']) }}"
                >
                    <!-- Chart will be rendered here via JavaScript -->
                </div>

                @if(!empty($chart['legend']))
                    <div class="mfw-chart-legend">
                        @foreach($chart['legend'] as $item)
                            <div class="legend-item">
                                <span class="legend-color" style="background-color: {{ $item['color'] }}"></span>
                                <span class="legend-label">{{ $item['label'] }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-card>
        @endforeach
    </div>

    <!-- Report Tables -->
    <div class="mfw-report-tables">
        @foreach($tables as $table)
            <x-card class="mfw-table-card">
                @slot('title')
                    {{ $table['title'] }}
                @endslot

                @slot('actions')
                    <button type="button" class="button" data-action="export-table" data-table="{{ $table['id'] }}">
                        <span class="dashicons dashicons-download"></span> Export
                    </button>
                @endslot

                <x-table>
                    @slot('headers')
                        @foreach($table['columns'] as $column)
                            <th {!! $column['attributes'] ?? '' !!}>
                                {{ $column['label'] }}
                                @if(!empty($column['help']))
                                    <span class="dashicons dashicons-editor-help" title="{{ $column['help'] }}"></span>
                                @endif
                            </th>
                        @endforeach
                    @endslot

                    @foreach($table['data'] as $row)
                        <tr>
                            @foreach($table['columns'] as $column)
                                <td {!! $column['attributes'] ?? '' !!}>
                                    {!! $row[$column['id']] !!}
                                </td>
                            @endforeach
                        </tr>
                    @endforeach

                    @if(!empty($table['totals']))
                        @slot('footer')
                            <tr class="totals-row">
                                @foreach($table['columns'] as $column)
                                    <td {!! $column['attributes'] ?? '' !!}>
                                        {!! $table['totals'][$column['id']] ?? '' !!}
                                    </td>
                                @endforeach
                            </tr>
                        @endslot
                    @endif
                </x-table>
            </x-card>
        @endforeach
    </div>

    <!-- Export Report Modal -->
    <x-modal id="mfw-export-report">
        @slot('title')
            Export Report
        @endslot

        <x-form method="post" action="{{ admin_url('admin-post.php') }}" class="mfw-export-form">
            <input type="hidden" name="action" value="mfw_export_report">
            
            <div class="mfw-form-field">
                <label for="export_format">Format</label>
                <select name="export_format" id="export_format" required>
                    <option value="pdf">PDF</option>
                    <option value="xlsx">Excel</option>
                    <option value="csv">CSV</option>
                </select>
            </div>

            <div class="mfw-form-field">
                <label>Content</label>
                <label class="checkbox-label">
                    <input type="checkbox" name="content[]" value="overview" checked>
                    Overview
                </label>
                <label class="checkbox-label">
                    <input type="checkbox" name="content[]" value="charts" checked>
                    Charts
                </label>
                <label class="checkbox-label">
                    <input type="checkbox" name="content[]" value="tables" checked>
                    Tables
                </label>
            </div>

            @slot('footer')
                <button type="button" class="button" data-dismiss="modal">
                    Cancel
                </button>
                <button type="submit" class="button button-primary">
                    Export
                </button>
            @endslot
        </x-form>
    </x-modal>

    <!-- Chart Customization Modal -->
    <x-modal id="mfw-customize-chart">
        @slot('title')
            Customize Chart
        @endslot

        <x-form class="mfw-chart-options-form">
            <!-- Dynamically populated via JavaScript -->
        </x-form>

        @slot('footer')
            <button type="button" class="button" data-dismiss="modal">
                Cancel
            </button>
            <button type="button" class="button button-primary" data-action="apply-chart-options">
                Apply
            </button>
        @endslot
    </x-modal>
@endsection