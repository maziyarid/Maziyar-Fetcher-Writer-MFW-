<?php
/**
 * Chart Field Template
 * Advanced data visualization with AI-powered insights and automatic chart suggestions
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
    'type' => $field['default_type'] ?? 'line',
    'data' => $field['default_data'] ?? [],
    'options' => $field['default_options'] ?? []
]);

$chart_types = [
    'line' => 'Line Chart',
    'bar' => 'Bar Chart',
    'pie' => 'Pie Chart',
    'doughnut' => 'Doughnut Chart',
    'radar' => 'Radar Chart',
    'polar' => 'Polar Chart',
    'bubble' => 'Bubble Chart',
    'scatter' => 'Scatter Plot'
];
?>
<div class="mfw-chart-field {{ $field['class'] ?? '' }}"
    data-type="{{ $value['type'] }}"
>
    <!-- Chart Header -->
    <div class="chart-header">
        <div class="chart-info">
            <h3>{{ $field['title'] ?? 'Data Visualization' }}</h3>
            @if(!empty($field['description']))
                <span class="chart-description">{{ $field['description'] }}</span>
            @endif
        </div>

        <!-- Chart Actions -->
        <div class="chart-actions">
            @if(!empty($field['enable_ai']))
                <div class="ai-assistant">
                    <button type="button" class="ai-analyze" title="AI Analysis">
                        <span class="dashicons dashicons-admin-customizer"></span>
                        AI Insights
                    </button>
                    <div class="ai-options" style="display: none;">
                        <div class="analysis-options">
                            <div class="option-group">
                                <label>Analysis Type</label>
                                <select class="analysis-type">
                                    <option value="trends">Trend Analysis</option>
                                    <option value="patterns">Pattern Recognition</option>
                                    <option value="correlations">Correlations</option>
                                    <option value="predictions">Predictions</option>
                                </select>
                            </div>
                            <div class="option-group">
                                <label>Insight Level</label>
                                <select class="insight-level">
                                    <option value="basic">Basic</option>
                                    <option value="detailed">Detailed</option>
                                    <option value="advanced">Advanced</option>
                                </select>
                            </div>
                        </div>
                        <button type="button" class="button button-primary analyze-data">
                            Generate Insights
                        </button>
                    </div>
                </div>
            @endif

            <!-- Chart Controls -->
            <div class="chart-controls">
                <select class="chart-type-selector">
                    @foreach($chart_types as $type => $label)
                        <option value="{{ $type }}" {{ $value['type'] === $type ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
                <button type="button" class="refresh-chart" title="Refresh Chart">
                    <span class="dashicons dashicons-update"></span>
                </button>
                @if(!empty($field['enable_export']))
                    <button type="button" class="export-chart" title="Export Chart">
                        <span class="dashicons dashicons-download"></span>
                    </button>
                @endif
            </div>
        </div>
    </div>

    <!-- Chart Container -->
    <div class="chart-container">
        <!-- Chart Canvas -->
        <canvas id="{{ $field['id'] }}_chart"></canvas>

        <!-- Loading Overlay -->
        <div class="chart-loading" style="display: none;">
            <div class="loading-spinner"></div>
            <div class="loading-text">Processing data...</div>
        </div>
    </div>

    <!-- Data Management -->
    <div class="data-management">
        <div class="data-tabs">
            <button type="button" class="tab active" data-tab="data">
                Data
            </button>
            <button type="button" class="tab" data-tab="options">
                Options
            </button>
            @if(!empty($field['enable_ai']))
                <button type="button" class="tab" data-tab="insights">
                    AI Insights
                </button>
            @endif
        </div>

        <!-- Data Tab -->
        <div class="tab-content data-tab active">
            <div class="data-tools">
                <button type="button" class="add-dataset">
                    <span class="dashicons dashicons-plus"></span>
                    Add Dataset
                </button>
                <button type="button" class="import-data">
                    <span class="dashicons dashicons-upload"></span>
                    Import
                </button>
            </div>

            <div class="datasets-container">
                @foreach($value['data']['datasets'] ?? [] as $dataset_index => $dataset)
                    <div class="dataset-item" data-index="{{ $dataset_index }}">
                        <div class="dataset-header">
                            <input type="text"
                                class="dataset-label"
                                value="{{ $dataset['label'] ?? '' }}"
                                placeholder="Dataset name"
                            >
                            <div class="dataset-actions">
                                <button type="button" class="toggle-dataset" title="Toggle Dataset">
                                    <span class="dashicons dashicons-arrow-down-alt2"></span>
                                </button>
                                <button type="button" class="remove-dataset" title="Remove Dataset">
                                    <span class="dashicons dashicons-trash"></span>
                                </button>
                            </div>
                        </div>
                        <div class="dataset-content" style="display: none;">
                            <div class="data-grid"></div>
                            <div class="dataset-options">
                                <div class="option-group">
                                    <label>Color</label>
                                    <input type="color"
                                        class="dataset-color"
                                        value="{{ $dataset['borderColor'] ?? '#000000' }}"
                                    >
                                </div>
                                <div class="option-group">
                                    <label>Type</label>
                                    <select class="dataset-type">
                                        @foreach($chart_types as $type => $label)
                                            <option value="{{ $type }}" {{ ($dataset['type'] ?? '') === $type ? 'selected' : '' }}>
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Options Tab -->
        <div class="tab-content options-tab">
            <div class="options-form">
                <!-- Title Options -->
                <div class="option-section">
                    <h4>Title</h4>
                    <div class="option-group">
                        <label>Display Title</label>
                        <input type="checkbox"
                            class="show-title"
                            {{ !empty($value['options']['plugins']['title']['display']) ? 'checked' : '' }}
                        >
                    </div>
                    <div class="option-group">
                        <label>Title Text</label>
                        <input type="text"
                            class="title-text"
                            value="{{ $value['options']['plugins']['title']['text'] ?? '' }}"
                        >
                    </div>
                </div>

                <!-- Legend Options -->
                <div class="option-section">
                    <h4>Legend</h4>
                    <div class="option-group">
                        <label>Show Legend</label>
                        <input type="checkbox"
                            class="show-legend"
                            {{ !empty($value['options']['plugins']['legend']['display']) ? 'checked' : '' }}
                        >
                    </div>
                    <div class="option-group">
                        <label>Position</label>
                        <select class="legend-position">
                            <option value="top" {{ ($value['options']['plugins']['legend']['position'] ?? '') === 'top' ? 'selected' : '' }}>
                                Top
                            </option>
                            <option value="bottom" {{ ($value['options']['plugins']['legend']['position'] ?? '') === 'bottom' ? 'selected' : '' }}>
                                Bottom
                            </option>
                            <option value="left" {{ ($value['options']['plugins']['legend']['position'] ?? '') === 'left' ? 'selected' : '' }}>
                                Left
                            </option>
                            <option value="right" {{ ($value['options']['plugins']['legend']['position'] ?? '') === 'right' ? 'selected' : '' }}>
                                Right
                            </option>
                        </select>
                    </div>
                </div>

                <!-- Axes Options -->
                <div class="option-section">
                    <h4>Axes</h4>
                    <div class="option-group">
                        <label>Show Grid</label>
                        <input type="checkbox"
                            class="show-grid"
                            {{ !empty($value['options']['scales']['x']['grid']['display']) ? 'checked' : '' }}
                        >
                    </div>
                    <div class="option-group">
                        <label>Stacked</label>
                        <input type="checkbox"
                            class="stacked-axes"
                            {{ !empty($value['options']['scales']['x']['stacked']) ? 'checked' : '' }}
                        >
                    </div>
                </div>
            </div>
        </div>

        <!-- AI Insights Tab -->
        @if(!empty($field['enable_ai']))
            <div class="tab-content insights-tab">
                <div class="insights-container">
                    <div class="loading-insights" style="display: none;">
                        <div class="loading-spinner"></div>
                        <div class="loading-text">Analyzing data...</div>
                    </div>
                    <div class="insights-content"></div>
                </div>
            </div>
        @endif
    </div>

    <!-- Hidden Input -->
    <input type="hidden"
        name="{{ $field['name'] }}"
        value="{{ json_encode($value) }}"
        class="chart-value"
    >
</div>