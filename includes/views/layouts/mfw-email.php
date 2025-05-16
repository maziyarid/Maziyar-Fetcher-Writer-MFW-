<?php
/**
 * Email Layout Template
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
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        /* Email styles */
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.5;
            color: #333;
            margin: 0;
            padding: 0;
        }
        .mfw-email-wrapper {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .mfw-email-header {
            text-align: center;
            padding: 20px 0;
            border-bottom: 1px solid #eee;
        }
        .mfw-email-content {
            padding: 20px 0;
        }
        .mfw-email-footer {
            text-align: center;
            padding: 20px 0;
            border-top: 1px solid #eee;
            font-size: 12px;
            color: #666;
        }
        .button {
            display: inline-block;
            padding: 10px 20px;
            background-color: #0073aa;
            color: #fff;
            text-decoration: none;
            border-radius: 3px;
        }
    </style>
</head>
<body>
    <div class="mfw-email-wrapper">
        <div class="mfw-email-header">
            <img src="{{ $logo_url }}" alt="{{ $site_name }}" width="200">
        </div>

        <div class="mfw-email-content">
            @yield('content')
        </div>

        <div class="mfw-email-footer">
            <p>
                &copy; {{ date('Y') }} {{ $site_name }}. All rights reserved.<br>
                @if(!empty($unsubscribe_url))
                    <a href="{{ $unsubscribe_url }}">Unsubscribe</a>
                @endif
            </p>
        </div>
    </div>
</body>
</html>