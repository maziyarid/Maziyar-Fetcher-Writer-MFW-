<?php
/**
 * Admin Tools Template
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
        <button type="button" class="button" data-action="refresh-status">
            <span class="dashicons dashicons-update"></span> Refresh Status
        </button>
        <a href="{{ admin_url('admin.php?page=mfw-logs') }}" class="button">
            <span class="dashicons dashicons-list-view"></span> View Logs
        </a>
    </div>
@endsection

@section('content')
    <div class="mfw-tools-grid">
        <!-- System Health -->
        <x-card class="mfw-health-card">
            @slot('title')
                System Health
            @endslot

            <div class="mfw-health-status">
                <div class="health-score {{ $health_score['class'] }}">
                    <div class="score-value">{{ $health_score['value'] }}</div>
                    <div class="score-label">Health Score</div>
                </div>

                <div class="health-items">
                    @foreach($health_items as $item)
                        <div class="health-item status-{{ $item['status'] }}">
                            <span class="dashicons dashicons-{{ $item['icon'] }}"></span>
                            <div class="item-info">
                                <h4>{{ $item['label'] }}</h4>
                                <p>{{ $item['message'] }}</p>
                            </div>
                            @if(!empty($item['action']))
                                <button type="button" 
                                    class="button button-small"
                                    data-action="{{ $item['action']['handler'] }}"
                                >
                                    {{ $item['action']['label'] }}
                                </button>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>

            @if(!empty($health_recommendations))
                <div class="health-recommendations">
                    <h4>Recommendations</h4>
                    <ul>
                        @foreach($health_recommendations as $rec)
                            <li class="priority-{{ $rec['priority'] }}">
                                <span class="dashicons dashicons-{{ $rec['icon'] }}"></span>
                                <div class="rec-content">
                                    <p>{{ $rec['message'] }}</p>
                                    @if(!empty($rec['action']))
                                        <button type="button" 
                                            class="button button-small"
                                            data-action="{{ $rec['action']['handler'] }}"
                                        >
                                            {{ $rec['action']['label'] }}
                                        </button>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </x-card>

        <!-- Maintenance Tools -->
        <x-card class="mfw-maintenance-card">
            @slot('title')
                Maintenance Tools
            @endslot

            <div class="mfw-tools-grid">
                @foreach($maintenance_tools as $tool)
                    <div class="tool-item">
                        <div class="tool-header">
                            <span class="dashicons dashicons-{{ $tool['icon'] }}"></span>
                            <h4>{{ $tool['title'] }}</h4>
                        </div>
                        <p>{{ $tool['description'] }}</p>
                        <div class="tool-actions">
                            <button type="button" 
                                class="button {{ $tool['class'] ?? '' }}"
                                data-action="{{ $tool['action'] }}"
                                @if(!empty($tool['confirm']))
                                    data-confirm="{{ $tool['confirm'] }}"
                                @endif
                            >
                                {{ $tool['button_text'] }}
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-card>

        <!-- Database Tools -->
        <x-card class="mfw-database-card">
            @slot('title')
                Database Tools
            @endslot

            <div class="mfw-database-status">
                <div class="status-item">
                    <h4>Database Size</h4>
                    <div class="status-value">{{ $db_stats['size'] }}</div>
                </div>
                <div class="status-item">
                    <h4>Tables</h4>
                    <div class="status-value">{{ $db_stats['tables'] }}</div>
                </div>
                <div class="status-item">
                    <h4>Last Optimized</h4>
                    <div class="status-value">{{ $db_stats['last_optimized'] }}</div>
                </div>
            </div>

            <div class="mfw-database-tools">
                @foreach($database_tools as $tool)
                    <div class="tool-item">
                        <div class="tool-info">
                            <h4>{{ $tool['title'] }}</h4>
                            <p>{{ $tool['description'] }}</p>
                        </div>
                        <button type="button" 
                            class="button {{ $tool['class'] ?? '' }}"
                            data-action="{{ $tool['action'] }}"
                            @if(!empty($tool['confirm']))
                                data-confirm="{{ $tool['confirm'] }}"
                            @endif
                        >
                            {{ $tool['button_text'] }}
                        </button>
                    </div>
                @endforeach
            </div>
        </x-card>

        <!-- Cache Management -->
        <x-card class="mfw-cache-card">
            @slot('title')
                Cache Management
            @endslot

            <div class="mfw-cache-status">
                @foreach($cache_stats as $stat)
                    <div class="status-item">
                        <h4>{{ $stat['label'] }}</h4>
                        <div class="status-value">{{ $stat['value'] }}</div>
                    </div>
                @endforeach
            </div>

            <div class="mfw-cache-tools">
                @foreach($cache_tools as $tool)
                    <div class="tool-item">
                        <div class="tool-info">
                            <h4>{{ $tool['title'] }}</h4>
                            <p>{{ $tool['description'] }}</p>
                        </div>
                        <button type="button" 
                            class="button {{ $tool['class'] ?? '' }}"
                            data-action="{{ $tool['action'] }}"
                        >
                            {{ $tool['button_text'] }}
                        </button>
                    </div>
                @endforeach
            </div>
        </x-card>

        <!-- System Information -->
        <x-card class="mfw-sysinfo-card">
            @slot('title')
                System Information
            @endslot

            <div class="mfw-sysinfo-grid">
                @foreach($system_info as $section)
                    <div class="info-section">
                        <h4>{{ $section['title'] }}</h4>
                        <table class="info-table">
                            @foreach($section['items'] as $item)
                                <tr>
                                    <th>{{ $item['label'] }}</th>
                                    <td>
                                        {!! $item['value'] !!}
                                        @if(!empty($item['status']))
                                            <span class="status-indicator status-{{ $item['status'] }}"></span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </table>
                    </div>
                @endforeach
            </div>

            @slot('footer')
                <button type="button" class="button" data-action="copy-sysinfo">
                    Copy System Info
                </button>
                <button type="button" class="button" data-action="download-sysinfo">
                    Download Report
                </button>
            @endslot
        </x-card>
    </div>

    <!-- Task Progress Modal -->
    <x-modal id="mfw-task-progress" class="mfw-progress-modal">
        @slot('title')
            Task Progress
        @endslot

        <div class="mfw-progress-content">
            <div class="progress-status">
                <div class="progress-message"></div>
                <div class="progress-bar">
                    <div class="progress-fill"></div>
                </div>
                <div class="progress-details">
                    <span class="items-processed">0</span> / <span class="total-items">0</span> items processed
                </div>
            </div>
            <div class="progress-log"></div>
        </div>

        @slot('footer')
            <button type="button" class="button" data-action="cancel-task" style="display: none;">
                Cancel
            </button>
            <button type="button" class="button" data-dismiss="modal" style="display: none;">
                Close
            </button>
        @endslot
    </x-modal>

    <!-- Confirmation Modal -->
    <x-modal id="mfw-confirm-action">
        @slot('title')
            Confirm Action
        @endslot

        <p class="confirm-message"></p>

        @slot('footer')
            <button type="button" class="button" data-dismiss="modal">
                Cancel
            </button>
            <button type="button" class="button button-primary" data-action="confirm">
                Proceed
            </button>
        @endslot
    </x-modal>
@endsection