<?php

$is_production = true;
$theme_uri = get_stylesheet_directory_uri();
$theme_version = wp_get_theme()->get('Version');
function is_login_page()
{
    return in_array($GLOBALS['pagenow'], array('wp-login.php', 'wp-register.php'));
}
if ($is_production) {
    add_action('wp_enqueue_scripts', function () use ($theme_version, $theme_uri) {
        wp_register_script(
            'niRvana',
            $theme_uri . '/assets/minify/app.min.js',
            array('jquery'),
            $theme_version
        );
        wp_register_style(
            'niRvana',
            $theme_uri . '/assets/minify/app.min.css',
            array(),
            $theme_version
        );
        if (!is_admin() && !is_login_page()) {
            wp_enqueue_script('niRvana');
            wp_enqueue_style('niRvana');
        }
    });
} else {
    add_action('wp_enqueue_scripts', function () use ($theme_uri, $theme_version) {
        // 定义所有开发环境资源
        $assets = [
            'css' => [
                'bootstrap' => 'assets/css/bootstrap.min.css',
                'bootstrap-xxs' => 'assets/css/bootstrap_xxs.css',
                'bootstrap-24' => 'assets/css/bootstrap_24.css',
                'bootstrap-xl' => 'assets/css/bootstrap_xl.css',
                'pdmessage' => 'assets/css/pdmessage-my.css',
                'fontawesome' => 'assets/css/fontawesome.css',
                'jv-element' => 'assets/css/jv-element.css',
                'user-center' => 'assets/css/user-center-login.css',
                'main-style' => 'assets/css/style.css',
                'highlightjs' => 'assets/css/highlightjs.css',
            ],
            'js' => [
                'jquery-v2' => ['path' => 'assets/js/jquery-2.1.0.min.js', 'dep' => []],
                'force-cache' => ['path' => 'assets/js/jQuery.forceCache.js', 'dep' => ['jquery-v2']],
                'jquery-mobile' => ['path' => 'assets/js/jquery.mobile.custom.min.js', 'dep' => ['jquery-v2']],
                'jquery-ui-drag' => ['path' => 'assets/js/jquery-ui-custom-drag.min.js', 'dep' => ['jquery-v2']],
                'scrollbars' => ['path' => 'assets/js/jquery.custom-scrollbars.js', 'dep' => ['jquery-v2']],
                'qrcode' => ['path' => 'assets/js/jquery.qrcode.min.js', 'dep' => ['jquery-v2']],
                'pdmessage' => ['path' => 'assets/js/pdmessage.js', 'dep' => ['jquery-v2']],
                'bootstrap' => ['path' => 'assets/js/bootstrap.min.js', 'dep' => ['jquery-v2']],
                'color-thief' => ['path' => 'assets/js/color-thief.js', 'dep' => ['jquery-v2']],
                'stackblur' => ['path' => 'assets/js/stackblur.min.js', 'dep' => ['jquery-v2']],
                'circlemagic' => ['path' => 'assets/js/circleMagic.min.js', 'dep' => ['jquery-v2']],
                'mustache' => ['path' => 'assets/js/mustache.min.js', 'dep' => []],
                'panda-slider' => ['path' => 'assets/js/pandaSlider.js', 'dep' => ['jquery-v2']],
                'panda-tab' => ['path' => 'assets/js/pandaTab.js', 'dep' => ['jquery-v2']],
                'panda-hooks' => ['path' => 'assets/js/pandaHooks.js', 'dep' => ['jquery-v2']],
                'jquery-vue' => ['path' => 'assets/js/jquery.vue.js', 'dep' => ['jquery-v2']],
                'jv-element' => ['path' => 'assets/js/jv-element.js', 'dep' => ['jquery-v2']],
                'user-center' => ['path' => 'assets/js/user-center-login.js', 'dep' => ['jquery-v2']],
                'masonry' => ['path' => 'assets/js/masonry.pkgd.min.js', 'dep' => ['jquery-v2']],
                'imgcomplete' => ['path' => 'assets/js/jquery.imgcomplete.js', 'dep' => ['jquery-v2']],
                'highlight' => ['path' => 'assets/js/highlight.min.js', 'dep' => []],
                'highlight-ln' => ['path' => 'assets/js/highlightjs-line-numbers.js', 'dep' => ['highlight']],
                'theme-main' => ['path' => 'assets/js/theme.js', 'dep' => ['jquery-v2']],
            ]
        ];

        if (!is_admin() && !is_login_page()) {
            foreach ($assets['css'] as $handle => $rel_path) {
                wp_enqueue_style($handle, $theme_uri . '/' . $rel_path, [], $theme_version);
            }
            foreach ($assets['js'] as $handle => $data) {
                wp_enqueue_script($handle, $theme_uri . '/' . $data['path'], $data['dep'], $theme_version, true);
            }
        }
    });
}
