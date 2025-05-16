<?php
/**
 * Frontend Layout Template
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
    <meta charset="{{ $site['charset'] }}">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $site['name'] }} - @yield('title')</title>
    @wphead
</head>
<body <?php body_class(); ?>>
    <?php wp_body_open(); ?>
    
    <div id="mfw-wrapper">
        <header class="mfw-header">
            <div class="mfw-container">
                <div class="mfw-site-branding">
                    <h1 class="site-title">
                        <a href="{{ $site['url'] }}">{{ $site['name'] }}</a>
                    </h1>
                    <p class="site-description">{{ $site['description'] }}</p>
                </div>

                <nav class="mfw-main-nav">
                    @if(!empty($menu))
                        <ul class="menu">
                            @foreach($menu as $item)
                                <li class="{{ $item->classes }}">
                                    <a href="{{ $item->url }}">{{ $item->title }}</a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </nav>
            </div>
        </header>

        <div class="mfw-container">
            <main class="mfw-main">
                @yield('content')
            </main>

            @if(!empty($sidebar))
                <aside class="mfw-sidebar">
                    {!! $sidebar !!}
                </aside>
            @endif
        </div>

        <footer class="mfw-footer">
            <div class="mfw-container">
                <div class="mfw-footer-widgets">
                    @foreach($footer as $widget_area)
                        <div class="widget-area">
                            {!! $widget_area !!}
                        </div>
                    @endforeach
                </div>
                <div class="mfw-footer-info">
                    @include('frontend.partials.footer-info')
                </div>
            </div>
        </footer>
    </div>
    @wpfooter
</body>
</html>