<?php
/**
 * Admin Layout Template
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
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @wphead
</head>
<body class="wp-admin">
    <div id="mfw-admin-wrapper">
        <header class="mfw-admin-header">
            <div class="mfw-logo">
                <img src="<?php echo MFW_PLUGIN_URL; ?>assets/images/logo.svg" alt="MFW">
            </div>
            <nav class="mfw-admin-nav">
                @include('admin.partials.navigation')
            </nav>
            <div class="mfw-user-menu">
                @include('admin.partials.user-menu')
            </div>
        </header>

        <div class="mfw-admin-container">
            <aside class="mfw-admin-sidebar">
                @include('admin.partials.sidebar')
            </aside>

            <main class="mfw-admin-main">
                @if(!empty($notices))
                    <div class="mfw-notices">
                        @foreach($notices as $notice)
                            <div class="notice notice-{{ $notice['type'] }}">
                                <p>{!! $notice['message'] !!}</p>
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="mfw-page-header">
                    <h1>{{ $title }}</h1>
                    @yield('page-actions')
                </div>

                <div class="mfw-content">
                    @yield('content')
                </div>
            </main>
        </div>

        <footer class="mfw-admin-footer">
            @include('admin.partials.footer')
        </footer>
    </div>
    @wpfooter
</body>
</html>