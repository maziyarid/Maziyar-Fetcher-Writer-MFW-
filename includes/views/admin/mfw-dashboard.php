<?php
/**
 * Admin Dashboard Template
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
        <button type="button" class="button" data-modal="mfw-welcome">
            <span class="dashicons dashicons-info"></span> Quick Start Guide
        </button>
    </div>
@endsection

@section('content')
    <div class="mfw-dashboard-grid">
        <!-- System Status Card -->
        <x-card class="mfw-status-card">
            @slot('title')
                System Status
            @endslot

            <div class="mfw-status-items">
                @foreach($system_status as $item)
                    <div class="mfw-status-item status-{{ $item['status'] }}">
                        <span class="dashicons dashicons-{{ $item['icon'] }}"></span>
                        <div class="status-info">
                            <h4>{{ $item['label'] }}</h4>
                            <p>{{ $item['message'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>

            @slot('footer')
                <a href="{{ admin_url('admin.php?page=mfw-status') }}" class="button">
                    View Full Status Report
                </a>
            @endslot
        </x-card>

        <!-- Recent Activity Card -->
        <x-card class="mfw-activity-card">
            @slot('title')
                Recent Activity
            @endslot

            @if(!empty($recent_activity))
                <ul class="mfw-activity-list">
                    @foreach($recent_activity as $activity)
                        <li class="activity-{{ $activity['type'] }}">
                            <span class="activity-icon dashicons dashicons-{{ $activity['icon'] }}"></span>
                            <div class="activity-content">
                                <p>{!! $activity['message'] !!}</p>
                                <time datetime="{{ $activity['timestamp'] }}">
                                    {{ human_time_diff(strtotime($activity['timestamp']), current_time('timestamp')) }} ago
                                </time>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="mfw-no-items">No recent activity to display.</p>
            @endif
        </x-card>

        <!-- Statistics Card -->
        <x-card class="mfw-stats-card">
            @slot('title')
                Statistics
            @endslot

            <div class="mfw-stats-grid">
                @foreach($statistics as $stat)
                    <div class="mfw-stat-item">
                        <div class="stat-value">{{ $stat['value'] }}</div>
                        <div class="stat-label">{{ $stat['label'] }}</div>
                        @if(!empty($stat['trend']))
                            <div class="stat-trend trend-{{ $stat['trend']['direction'] }}">
                                <span class="dashicons dashicons-arrow-{{ $stat['trend']['direction'] }}"></span>
                                {{ $stat['trend']['value'] }}%
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            @slot('footer')
                <a href="{{ admin_url('admin.php?page=mfw-reports') }}" class="button">
                    View Detailed Reports
                </a>
            @endslot
        </x-card>

        <!-- Quick Actions Card -->
        <x-card class="mfw-actions-card">
            @slot('title')
                Quick Actions
            @endslot

            <div class="mfw-quick-actions">
                @foreach($quick_actions as $action)
                    <a href="{{ $action['url'] }}" class="mfw-quick-action">
                        <span class="dashicons dashicons-{{ $action['icon'] }}"></span>
                        <span class="action-label">{{ $action['label'] }}</span>
                    </a>
                @endforeach
            </div>
        </x-card>
    </div>

    <!-- Welcome Modal -->
    <x-modal id="mfw-welcome" class="mfw-welcome-modal">
        @slot('title')
            Welcome to MFW Framework
        @endslot

        <div class="mfw-welcome-content">
            <h3>Quick Start Guide</h3>
            <ol class="mfw-setup-steps">
                @foreach($setup_steps as $step)
                    <li class="setup-step {{ $step['completed'] ? 'completed' : '' }}">
                        <h4>{{ $step['title'] }}</h4>
                        <p>{{ $step['description'] }}</p>
                        @if(!empty($step['action']))
                            <a href="{{ $step['action']['url'] }}" class="button">
                                {{ $step['action']['label'] }}
                            </a>
                        @endif
                    </li>
                @endforeach
            </ol>
        </div>

        @slot('footer')
            <button type="button" class="button button-primary" data-dismiss="modal">
                Got it!
            </button>
        @endslot
    </x-modal>
@endsection