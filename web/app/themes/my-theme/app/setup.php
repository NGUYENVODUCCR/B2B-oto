<?php

/**
 * Theme setup.
 */

namespace App;

use function Roots\bundle;

/**
 * Register the theme assets.
 *
 * @return void
 */
add_action('wp_enqueue_scripts', function () {
    $bundle = bundle('app')->enqueue();
    $config = sprintf(
        'window.B2B_CONFIG = %s;',
        wp_json_encode([
            'apiBase' => home_url('/wp-json/b2b/v1'),
            'homeUrl' => home_url('/'),
            'chatWsUrl' => apply_filters(
                'b2b_chat_ws_url',
                get_option('b2b_chat_ws_url', getenv('B2B_CHAT_WS_URL') ?: '')
            ),
        ])
    );

    $bundle->js(function ($handle) use ($config) {
        wp_add_inline_script($handle, $config, 'before');
    });
    // AI Box (floating) assets
    wp_enqueue_style(
        'b2b-ai-chat',
        get_theme_file_uri('/resources/styles/pages/ai-chat.css'),
        [],
        filemtime(get_theme_file_path('/resources/styles/pages/ai-chat.css'))
    );

    wp_enqueue_script(
        'b2b-ai-chat',
        get_theme_file_uri('/resources/scripts/pages/ai-chat.js'),
        [],
        filemtime(get_theme_file_path('/resources/scripts/pages/ai-chat.js')),
        true
    );
}, 100);

add_filter('script_loader_tag', function ($tag, $handle, $src) {
    if ($handle !== 'b2b-ai-chat') {
        return $tag;
    }

    return sprintf(
        '<script type="module" src="%s" charset="UTF-8" id="%s-js"></script>',
        esc_url($src),
        esc_attr($handle)
    );
}, 10, 3);

add_filter('query_vars', function ($vars) {
    $vars[] = 'b2b_support_workspace';

    return $vars;
});

add_action('init', function () {
    add_rewrite_rule(
        '^support-workspace/?$',
        'index.php?pagename=support&b2b_support_workspace=1',
        'top'
    );

    $rewriteVersion = '20260526';
    if (get_option('b2b_support_workspace_rewrite_version') !== $rewriteVersion) {
        flush_rewrite_rules(false);
        update_option('b2b_support_workspace_rewrite_version', $rewriteVersion, false);
    }
}, 20);

/**
 * Register the theme assets with the block editor.
 *
 * @return void
 */
add_action('enqueue_block_editor_assets', function () {
    bundle('editor')->enqueue();
}, 100);

/**
 * Register the initial theme setup.
 *
 * @return void
 */
add_action('after_setup_theme', function () {
    /**
     * Enable features from the Soil plugin if activated.
     *
     * @link https://roots.io/plugins/soil/
     */
    add_theme_support('soil', [
        'clean-up',
        'nav-walker',
        'nice-search',
        'relative-urls',
    ]);

    /**
     * Disable full-site editing support.
     *
     * @link https://wptavern.com/gutenberg-10-5-embeds-pdfs-adds-verse-block-color-options-and-introduces-new-patterns
     */
    remove_theme_support('block-templates');

    /**
     * Register the navigation menus.
     *
     * @link https://developer.wordpress.org/reference/functions/register_nav_menus/
     */
    register_nav_menus([
        'primary_navigation' => __('Primary Navigation', 'sage'),
    ]);

    /**
     * Disable the default block patterns.
     *
     * @link https://developer.wordpress.org/block-editor/developers/themes/theme-support/#disabling-the-default-block-patterns
     */
    remove_theme_support('core-block-patterns');

    /**
     * Enable plugins to manage the document title.
     *
     * @link https://developer.wordpress.org/reference/functions/add_theme_support/#title-tag
     */
    add_theme_support('title-tag');

    /**
     * Enable post thumbnail support.
     *
     * @link https://developer.wordpress.org/themes/functionality/featured-images-post-thumbnails/
     */
    add_theme_support('post-thumbnails');

    /**
     * Enable responsive embed support.
     *
     * @link https://developer.wordpress.org/block-editor/how-to-guides/themes/theme-support/#responsive-embedded-content
     */
    add_theme_support('responsive-embeds');

    /**
     * Enable HTML5 markup support.
     *
     * @link https://developer.wordpress.org/reference/functions/add_theme_support/#html5
     */
    add_theme_support('html5', [
        'caption',
        'comment-form',
        'comment-list',
        'gallery',
        'search-form',
        'script',
        'style',
    ]);

    /**
     * Enable selective refresh for widgets in customizer.
     *
     * @link https://developer.wordpress.org/reference/functions/add_theme_support/#customize-selective-refresh-widgets
     */
    add_theme_support('customize-selective-refresh-widgets');
}, 20);

/**
 * Register the theme sidebars.
 *
 * @return void
 */
add_action('widgets_init', function () {
    $config = [
        'before_widget' => '<section class="widget %1$s %2$s">',
        'after_widget' => '</section>',
        'before_title' => '<h3>',
        'after_title' => '</h3>',
    ];

    register_sidebar([
        'name' => __('Primary', 'sage'),
        'id' => 'sidebar-primary',
    ] + $config);

    register_sidebar([
        'name' => __('Footer', 'sage'),
        'id' => 'sidebar-footer',
    ] + $config);
});
