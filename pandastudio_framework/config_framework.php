<?php

function pf_framework_enqueue_scripts() {
    wp_register_script( 'pf_restapi', '' );

    // 无论固定链接怎么设，强制使用最稳定的参数化路径，解决 phpStudy/localhost 下的重写失败问题
    $pf_api_translation_array['route'] = home_url('index.php?rest_route=/');
    $pf_api_translation_array['blog_name'] = get_bloginfo('name');
    $pf_api_translation_array['nonce'] = wp_create_nonce('wp_rest');
    $pf_api_translation_array['home'] = home_url();

    $theme = wp_get_theme();
    $pf_api_translation_array['theme'] = array(
        'uri' => $theme->get('ThemeURI'),
        'author_uri' => $theme->get('AuthorURI'),
        'name' => $theme->get('Name'),
        'version' => $theme->get('Version'),
        'route' => get_stylesheet_directory_uri(),
    );

    $pf_api_translation_array['dark_mode'] = array(
        'enable' => get_option('enable_dark_mode'),
        'auto' => get_option('auto_dark_mode'),
        'time_start' => get_option('dark_mode_time_start'),
        'time_end' => get_option('dark_mode_time_end'),
    );

    if (is_admin()) {
        global $wpdb;

        $request  = "SELECT $wpdb->terms.term_id, name FROM $wpdb->terms ";
        $request .= " LEFT JOIN $wpdb->term_taxonomy ON $wpdb->term_taxonomy.term_id = $wpdb->terms.term_id ";
        $request .= " WHERE $wpdb->term_taxonomy.taxonomy = 'category' ";
        $request .= " ORDER BY term_id asc";

        $categorys = $wpdb->get_results($request);
        $categorySelector = array();

        foreach ($categorys as $category) {
            $categorySelector[] = array(
                'label' => $category->name,
                'value' => $category->term_id,
            );
        }

        $pf_api_translation_array['categorySelector'] = $categorySelector;
    }

    $current_user = wp_get_current_user();
    $pf_api_translation_array['current_user'] = array(
        'logged_in' => is_user_logged_in(),
        'name' => $current_user->user_login,
        'email' => $current_user->user_email,
        'id' => $current_user->ID,
    );

    $pf_api_translation_array = apply_filters('modify_pandastudio_translation_array', $pf_api_translation_array);

    wp_localize_script('pf_restapi', 'pandastudio_framework', $pf_api_translation_array);
    wp_enqueue_script('pf_restapi');
}

add_action('wp_enqueue_scripts', 'pf_framework_enqueue_scripts');
add_action('admin_enqueue_scripts', 'pf_framework_enqueue_scripts');

add_action('rest_api_init', function () {
    register_rest_route(
        'pandastudio/framework',
        '/get_option_json',
        array(
            'methods' => 'get',
            'callback' => 'get_option_json_by_RestAPI',
            'permission_callback' => '__return_true',
        )
    );
});

function get_option_json_by_RestAPI()
{
    $path = get_template_directory() . '/pandastudio_framework/option.json';
    $option_json_file = file_exists($path) ? file_get_contents($path) : '';

    if (strlen($option_json_file) > 10) {
        $option_json = json_decode($option_json_file, true);
    } else {
        $option_json = array();
    }

    $option_json = apply_filters('modify_pandastudio_options', $option_json);

    return $option_json;
}

add_action('rest_api_init', function () {
    register_rest_route(
        'pandastudio/framework',
        '/get_posttype_and_meta_json',
        array(
            'methods' => 'get',
            'callback' => 'get_posttype_and_meta_json_by_RestAPI',
            'permission_callback' => '__return_true',
        )
    );
});

function get_posttype_and_meta_json_by_RestAPI()
{
    $path = get_template_directory() . '/pandastudio_framework/posttype_and_meta.json';
    $posttype_and_meta_json_file = file_exists($path) ? file_get_contents($path) : '';

    if (strlen($posttype_and_meta_json_file) > 10) {
        $posttype_and_meta_json = json_decode($posttype_and_meta_json_file, true);
    } else {
        $posttype_and_meta_json = array();
    }

    $posttype_and_meta_json = apply_filters('modify_pandastudio_posttype_and_meta', $posttype_and_meta_json);

    return $posttype_and_meta_json;
}

$base_dir = get_template_directory() . '/pandastudio_framework';
$posttype_and_meta_file = file_exists($base_dir . '/posttype_and_meta.json') ? file_get_contents($base_dir . '/posttype_and_meta.json') : '';

if (strlen($posttype_and_meta_file) > 10) {
    $posttype_and_meta = get_posttype_and_meta_json_by_RestAPI();
    $myPostTypes = $posttype_and_meta['myPostTypes'] ?? array('posttypes' => array());
    $meta_tabs = $posttype_and_meta['meta'] ?? array();
    $meta_screens = array();

    foreach ($meta_tabs as $tab) {
        if (isset($tab['screen'])) {
            foreach ($tab['screen'] as $screen) {
                array_push($meta_screens, $screen);
            }
        }
    }

    include_once($base_dir . '/assets/template/posttype_json.php');
    include_once($base_dir . '/assets/template/meta_rest.php');
}

$option_file = file_exists($base_dir . '/option.json') ? file_get_contents($base_dir . '/option.json') : '';

if (strlen($option_file) > 10) {
    include_once($base_dir . '/assets/template/option_rest.php');
}
