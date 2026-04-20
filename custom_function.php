<?php
/**
 * 主题自定义功能文件 - niRvana
 * 完整恢复版：保留所有原功能 + 平铺按钮
 */
 
// ================== 1. 基础优化 ==================
add_filter('use_block_editor_for_post', '__return_false');
add_filter('use_block_editor_for_post_type', '__return_false');
remove_action('wp_enqueue_scripts', 'wp_common_block_scripts_and_styles');
remove_action('wp_head', 'print_emoji_detection_script', 7);
remove_action('admin_print_scripts', 'print_emoji_detection_script');
remove_action('wp_print_styles', 'print_emoji_styles');
remove_action('admin_print_styles', 'print_emoji_styles');
remove_action('wp_head', 'rsd_link');
remove_action('wp_head', 'wlwmanifest_link');
remove_action('wp_head', 'wp_generator');
remove_action('wp_head', 'wp_shortlink_wp_head');

add_filter('xmlrpc_enabled', '__return_false');
add_filter('xmlrpc_methods', function ($methods) {
    unset($methods['pingback.ping']);
    return $methods;
});

// 集成文章表格美化样式
add_action('wp_head', function() {
    ?>
    <style id="niRvana-table-style">
    .table-figure { border: 1px solid #ccc; border-radius: 5px; padding: 10px; margin-bottom: 20px; overflow-x: auto; }
    .table-figure table { width: 100%; border-collapse: collapse; min-width: 400px; }
    .table-figure th, .table-figure td { padding: 12px 8px; text-align: left; border-bottom: 1px solid #eee; }
    .table-figure thead { background-color: #f2f2f2; }
    .table-figure th { font-weight: bold; color: #333; }
    .table-figure tbody tr:nth-child(even) { background-color: #f9f9f9; }
    .table-figure tbody tr:hover { background-color: #f5f5f5; transition: background 0.2s; }
    </style>
    <?php
}, 100);

// ================== 2. 系统增强 ==================
function remove_category_base() {
    global $wp_rewrite;
    $wp_rewrite->extra_permastructs['category']['struct'] = '%category%';
}
add_action('init', 'remove_category_base');

function nirvana_flush_rules_on_change() {
    if (!get_transient('nirvana_flush_rules')) {
        set_transient('nirvana_flush_rules', true, HOUR_IN_SECONDS);
        flush_rewrite_rules();
    }
}
add_action('created_category', 'nirvana_flush_rules_on_change');
add_action('edited_category', 'nirvana_flush_rules_on_change');
add_action('delete_category', 'nirvana_flush_rules_on_change');

// 删除文章及其附件
function delete_post_and_attachments($post_ID) {
    $attachments = get_posts(['post_type' => 'attachment', 'post_parent' => $post_ID, 'post_status' => 'any', 'posts_per_page' => -1]);
    foreach ($attachments as $attachment) { wp_delete_attachment($attachment->ID, true); }
    $thumbnail_id = get_post_meta($post_ID, '_thumbnail_id', true);
    if ($thumbnail_id) { wp_delete_attachment($thumbnail_id, true); }
}
add_action('before_delete_post', 'delete_post_and_attachments');

// 移除资源版本号
function _remove_script_version($src) { return remove_query_arg('ver', $src); }
add_filter('script_loader_src', '_remove_script_version', 15);
add_filter('style_loader_src', '_remove_script_version', 15);

// ================== 3. 内容与媒体 ==================
function tag_link($content) {
    $post_id = get_the_ID();
    $cache_key = "tag_link_content_{$post_id}";
    $cached = wp_cache_get($cache_key, 'tag_link');
    if ($cached !== false) return $cached;
    $posttags = get_the_tags();
    if (!$posttags) { wp_cache_set($cache_key, $content, 'tag_link', 3600); return $content; }
    
    $protected_blocks = []; $block_counter = 0;
    $content = preg_replace_callback('/<(pre|code|script|style|textarea)[^>]*>.*?<\/\\1>/isU', function ($matches) use (&$protected_blocks, &$block_counter) {
        $placeholder = 'PROTECTED_BLOCK_' . $block_counter++;
        $protected_blocks[$placeholder] = $matches[0];
        return $placeholder;
    }, $content);

    foreach ($posttags as $tag) {
        $link = get_tag_link($tag->term_id);
        $pattern = '/\b(' . preg_quote($tag->name, '/') . ')\b(?![^<]*<\/a>)/i';
        $content = preg_replace($pattern, "<a href=\"$link\" target=\"_blank\">{$tag->name}</a>", $content, 2);
    }
    foreach ($protected_blocks as $placeholder => $original_content) { $content = str_replace($placeholder, $original_content, $content); }
    wp_cache_set($cache_key, $content, 'tag_link', 3600);
    return $content;
}
add_filter('the_content', 'tag_link', 1);

function catch_first_image( $post_id = null ) {
    if ( $post_id ) { $content = get_post($post_id)->post_content; } else { global $post; $content = $post->post_content; }
    if (preg_match('/<img.+src=[\'"]([^\'"]+)[\'"].*>/i', $content, $matches)) { $img_url = $matches[1]; } 
    else { $img_url = get_stylesheet_directory_uri() . '/assets/imgs/rand/' . rand(1, 10) . '.png'; }
    if (strpos($img_url, 'http') === 0) {
        $site_host = parse_url(home_url(), PHP_URL_HOST);
        $img_host = parse_url($img_url, PHP_URL_HOST);
        if ($img_host && $img_host !== $site_host) {
            $is_external = false;
            foreach (['qiniucdn.com', 'aliyuncs.com', 'myqcloud.com'] as $cdn) { if (strpos($img_host, $cdn) !== false) { $is_external = true; break; } }
            if (!$is_external) { $img_url = str_replace($img_host, $site_host, $img_url); }
        }
    }
    return $img_url;
}

add_action('save_post', function($post_id, $post, $update) {
    if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id) || $post->post_status !== 'publish') return;
    if (get_post_meta($post_id, 'apipush', true) === "0") return;
    wp_remote_post('https://www.bing.com/indexnow', [
        'body' => json_encode(['host' => parse_url(home_url())['host'], 'key' => '173bc4cbffe9447f920afd212df83a4b', 'urlList' => [get_permalink($post_id)]]),
        'headers' => ['Content-Type' => 'application/json'], 'timeout' => 2, 'blocking' => false, 'sslverify' => false
    ]);
    update_post_meta($post_id, 'apipush', '0');
}, 20, 3);

// ================== 4. 编辑器完全增强 ==================

// 恢复所有 QTags（代码模式）
add_action('after_wp_tiny_mce', function() { ?>
    <script type="text/javascript">
		QTags.addButton( 'h2', 'H2', "<h2>", "</h2>\n" );
		QTags.addButton( 'h3', 'H3', "<h3>", "</h3>\n" );
		QTags.addButton( 'h4', 'H4', "<h4>", "</h4>\n" );
		QTags.addButton( 'pre', '代码块', "<pre>", "</pre>\n" );
		QTags.addButton( 'info', '信息全屏', '[tip type="info"]', "[/tip]\n" );
		QTags.addButton( 'success', '成功全屏', '[tip type="success"]', "[/tip]\n" );
		QTags.addButton( 'worning', '警告全屏', '[tip type="worning"]', "[/tip]\n" );
		QTags.addButton( 'download', '下载按钮', "[download]", "[/download]\n" );
		QTags.addButton( 'reply2down', '回复才可下载', "[reply2down]", "[/reply2down]\n" );
		QTags.addButton( 'need_reply', '回复显示', "[need_reply]", "[/need_reply]\n" );
		QTags.addButton( 'vbilibili', 'B站视频', "[vbilibili]", "[/vbilibili] \n" );
		QTags.addButton( 'table', '表格', "<figure class='table-figure'><table><thead><tr><th>标题</th></tr></thead><tbody><tr><td>内容</td></tr></tbody></table></figure>\n" );
		QTags.addButton( 'tr', '行', "<tr>", "</tr>\n" );
		QTags.addButton( 'td', '列', "<td>", "</td>\n" );
		QTags.addButton( 'fmt', '图文排版', "[fmt img=\"image.jpg\" position=\"r\"]内容[/fmt]\n" );
		QTags.addButton( 'dropdown', '按钮形式下拉框', "[dropdown btn_label=\"下拉选择\"][/dropdown]\n" );
		QTags.addButton( 'modal', '按钮弹窗', "[modal btn_label=\"打开弹窗\" title=\"标题\"][/modal]\n" );
		QTags.addButton( 'collapse', '折叠面板', "[collapse btn_label=\"点击展开\"]内容[/collapse]\n" );
		QTags.addButton( 'vbilibili2', '全屏笔记专用', "[bilibili id=\"\" p=\"\" h2=\"\"]\n" );
		QTags.addButton( 'h2bilibili', '全屏视频专用', "[h2bilibili id=\"\" p=\"\"]\n" );
    </script>
<?php });

// 可视化 TinyMCE 注册（分三排显示，确保全平铺）
add_action('admin_init', function() {
    if (!current_user_can('edit_posts') && !current_user_can('edit_pages')) return;
    if (get_user_option('rich_editing') == 'true') {
        add_filter('mce_external_plugins', function($plugin_array) {
           $plugin_array['niRvana_mce_button'] = get_stylesheet_directory_uri() . '/assets/js/tinymce-buttons.js';
           return $plugin_array;
        });
        // 第二排：放高频基础功能
        add_filter('mce_buttons_2', function($buttons) {
            $new_buttons = ['ni_h2', 'ni_h3', 'ni_h4', 'ni_pre', 'ni_info', 'ni_success', 'ni_warning', 'ni_table', 'ni_tr', 'ni_td'];
            return array_merge($buttons, $new_buttons);
        });
        // 第三排：放交互与特定功能
        add_filter('mce_buttons_3', function($buttons) {
            $new_buttons = ['ni_download', 'ni_reply2down', 'ni_need_reply', 'ni_vbilibili', 'ni_fmt', 'ni_dropdown', 'ni_modal', 'ni_collapse', 'ni_bilibili2', 'ni_h2bilibili'];
            return $new_buttons; 
        });
    }
});