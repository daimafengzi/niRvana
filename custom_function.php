<?php
/**
 * 主题自定义功能文件 - niRvana
 * 优化版：安全、高效、无递归、无阻塞
 */
// ================== 1. WordPress 核心功能优化 ==================

// 禁用古腾堡编辑器
add_filter('use_block_editor_for_post', '__return_false');
add_filter('use_block_editor_for_post_type', '__return_false');

// 移除区块全局样式
remove_action('wp_enqueue_scripts', 'wp_common_block_scripts_and_styles');

// 禁用 Emoji
remove_action('wp_head', 'print_emoji_detection_script', 7);
remove_action('admin_print_scripts', 'print_emoji_detection_script');
remove_action('wp_print_styles', 'print_emoji_styles');
remove_action('admin_print_styles', 'print_emoji_styles');

// 禁用 XML-RPC 和 Pingback
add_filter('xmlrpc_enabled', '__return_false');
add_filter('xmlrpc_methods', function ($methods) {
    unset($methods['pingback.ping']);
    return $methods;
});

// 禁止后台编辑文件
if (!defined('DISALLOW_FILE_EDIT')) {
    define('DISALLOW_FILE_EDIT', true);
}

// 移除分类目录前缀 /category/
function remove_category_base() {
    global $wp_rewrite;
    $wp_rewrite->extra_permastructs['category']['struct'] = '%category%';
    add_action('created_category', 'flush_rewrite_rules');
    add_action('edited_category', 'flush_rewrite_rules');
    add_action('delete_category', 'flush_rewrite_rules');
}
add_action('init', 'remove_category_base');

// ================== 3. 文章功能优化 ==================

/**
 * 自动为文章添加标签（基于内容匹配）
 * 建议：标签数过多时可加缓存
 */
add_action('save_post', 'auto_add_tags', 10, 3);
function auto_add_tags($post_id, $post, $update) {
    if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id) || $post->post_status !== 'publish') {
        return;
    }

    if (!in_array($post->post_type, ['post'])) return;

    // 优化示例：只获取前 100 个常用标签，或者根据文章分类预筛选
	$tags = get_tags(['number' => 100, 'orderby' => 'count', 'order' => 'DESC']);
    if (!$tags) return;

    foreach ($tags as $tag) {
        if (strpos($post->post_content, $tag->name) !== false) {
            wp_set_post_tags($post_id, $tag->name, true);
        }
    }
}

/**
 * 当文章保存或标签变更时，清除 tag_link 缓存
 */
function clear_tag_link_cache($object_id, $terms, $tt_ids, $taxonomy) {
    // 只处理文章类型，且只在 post_tag 变更时触发
    if (get_post_type($object_id) !== 'post') {
        return;
    }

    // 确保是标签（post_tag）被修改
    if ($taxonomy !== 'post_tag' && $taxonomy !== 'category') {
        return;
    }

    wp_cache_delete("tag_link_content_{$object_id}", 'tag_link');
}
add_action('set_object_terms', 'clear_tag_link_cache', 10, 4);

/**
 * 文章保存时也清除缓存（内容可能变了）
 */
function clear_tag_link_cache_on_save($post_id, $post, $update) {
    if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
        return;
    }

    if ($post->post_type !== 'post') {
        return;
    }

    wp_cache_delete("tag_link_content_{$post_id}", 'tag_link');
}
add_action('save_post', 'clear_tag_link_cache_on_save', 20, 3);

/**
 * 为文章内标签添加内链（修复版 - 完全保护代码块）
 */
function tag_sort($a, $b) {
    if ($a->name === $b->name) return 0;
    return strlen($a->name) > strlen($b->name) ? -1 : 1;
}

function tag_link($content) {
    $post_id = get_the_ID();
    $cache_key = "tag_link_content_{$post_id}";
    $cached = wp_cache_get($cache_key, 'tag_link');
    if ($cached !== false) return $cached;

    $posttags = get_the_tags();
    if (!$posttags) {
        wp_cache_set($cache_key, $content, 'tag_link', 3600);
        return $content;
    }

    usort($posttags, "tag_sort");
    $match_num_from = 2;
    $match_num_to = 5;

    // 第一步：完全保护所有代码块和特殊标签
    $protected_blocks = [];
    $block_counter = 0;
    
    // 保护 pre, code, script, style 等标签
    $content = preg_replace_callback(
        '/<(pre|code|script|style|textarea|button|bilibili|div[^>]*class="[^"]*code[^"]*"[^>]*)[^>]*>.*?<\/\\1>/isU',
        function ($matches) use (&$protected_blocks, &$block_counter) {
            $placeholder = 'PROTECTED_BLOCK_' . $block_counter++;
            $protected_blocks[$placeholder] = $matches[0];
            return $placeholder;
        },
        $content
    );

    // 第二步：处理标签链接
    foreach ($posttags as $tag) {
        $link = get_tag_link($tag->term_id);
        $keyword = $tag->name;
        $cleankeyword = preg_quote($keyword, '/');

        // 只在 <a> 标签外替换关键词
        $url = "<a href=\"$link\" title=\"查看含有[{$keyword}]标签的文章\" target=\"_blank\">{$keyword}</a>";
        $limit = rand($match_num_from, $match_num_to);

        // 使用单词边界，确保不在链接内
        $pattern = '/\b(' . $cleankeyword . ')\b(?![^<]*<\/a>)/i';
        $content = preg_replace($pattern, $url, $content, $limit);
    }

    // 第三步：恢复所有被保护的内容
    foreach ($protected_blocks as $placeholder => $original_content) {
        $content = str_replace($placeholder, $original_content, $content);
    }

    wp_cache_set($cache_key, $content, 'tag_link', 3600);
    return $content;
}
add_filter('the_content', 'tag_link', 1);

// 删除文章时删除附件
function delete_post_and_attachments($post_ID) {
    $attachments = get_posts([
        'post_type'      => 'attachment',
        'post_parent'    => $post_ID,
        'post_status'    => 'any',
        'posts_per_page' => -1
    ]);

    foreach ($attachments as $attachment) {
        wp_delete_attachment($attachment->ID, true);
    }

    // 删除特色图像
    $thumbnail_id = get_post_meta($post_ID, '_thumbnail_id', true);
    if ($thumbnail_id) {
        wp_delete_attachment($thumbnail_id, true);
    }
}
add_action('before_delete_post', 'delete_post_and_attachments');

// 上传文件重命名
function git_upload_filter($file) {
    $info = pathinfo($file['name']);
    $ext = strtolower($info['extension']);
    $name = $info['filename'];

    if (preg_match("/[\x{4e00}-\x{9fff}]/u", $name) || stripos($name, 'image') !== false) {
        $file['name'] = date("YmdHis") . mt_rand(100, 999) . ".{$ext}";
    }
    return $file;
}
add_filter('wp_handle_upload_prefilter', 'git_upload_filter');

// 禁用缩略图生成
add_filter('intermediate_image_sizes_advanced', '__return_empty_array');
add_filter('image_size_names_choose', '__return_empty_array');
add_action('init', function () {
    update_option('thumbnail_size_h', 0);
    update_option('thumbnail_size_w', 0);
    update_option('medium_size_h', 0);
    update_option('medium_size_w', 0);
    update_option('large_size_h', 0);
    update_option('large_size_w', 0);
});

// ================== 4. 第三方集成优化 ==================

// Bing 自动推送（异步非阻塞）
add_action('save_post', 'noplugin_indexnow', 20, 3);
function noplugin_indexnow($post_id, $post, $update) {
    if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id) || $post->post_status !== 'publish') {
        return;
    }

    if (get_post_meta($post_id, 'apipush', true) === "0") {
        return;
    }

    $key = '173bc4cbffe9447f920afd212df83a4b';
    $api = 'https://www.bing.com/indexnow';
    $url = get_permalink($post_id);

    // 异步请求，不阻塞页面保存
    wp_remote_post($api, [
        'body'        => json_encode([
            'host'    => parse_url(home_url())['host'],
            'key'     => $key,
            'urlList' => [$url]
        ]),
        'headers'     => ['Content-Type' => 'application/json'],
        'timeout'     => 2,
        'blocking'    => false, // 关键：非阻塞
        'sslverify'   => false,
        'httpversion' => '1.1'
    ]);

    update_post_meta($post_id, 'apipush', '0');
}

// ================== 5. 其他功能 ==================

// 支持外链缩略图已在 functions.php 中配置

// 获取第一张图片（增强版：支持传入 ID 和随机逻辑）
function catch_first_image( $post_id = null ) {
    if ( $post_id ) {
        $post_obj = get_post( $post_id );
        $content = $post_obj->post_content;
    } else {
        global $post;
        $content = $post->post_content;
    }

    // 1. 尝试抓取文章内第一张图
    if (preg_match('/<img.+src=[\'"]([^\'"]+)[\'"].*>/i', $content, $matches)) {
        return $matches[1];
    }

    // 2. 如果文章没图，使用随机图（1-10.png）
    $random_id = rand(1, 10);
    $random_path = get_stylesheet_directory_uri() . '/assets/imgs/rand/' . $random_id . '.png';
    
    return $random_path;
}

// 评论用户不走缓存
add_action('set_comment_cookies', 'coffin_set_cookies', 10, 3);
function coffin_set_cookies($comment, $user, $cookies_consent) {
    wp_set_comment_cookies($comment, $user, true);
}

// 移除静态资源版本号
function _remove_script_version($src) {
    return remove_query_arg('ver', $src);
}
add_filter('script_loader_src', '_remove_script_version', 15);
add_filter('style_loader_src', '_remove_script_version', 15);

//添加了重写规则，主题不支持固定链接，添加修复
// 添加自定义重写规则支持
add_action('init', 'nirvana_add_rewrite_rules');
function nirvana_add_rewrite_rules() {
    // 1. 明确我们要特殊支持的页面路径（优先级最高）
    add_rewrite_rule('^page-archives/?$', 'index.php?pagename=page-archives', 'top');
    add_rewrite_rule('^page-links/?$', 'index.php?pagename=page-links', 'top');
    add_rewrite_rule('^page-say/?$', 'index.php?pagename=page-say', 'top');
    add_rewrite_rule('^newarchives/?$', 'index.php?pagename=newarchives', 'top');

    // 2. 恢复文章 ID.html 格式支持
    add_rewrite_rule('^([0-9]+)\.html$', 'index.php?p=$matches[1]', 'top');
    add_rewrite_rule('^([0-9]+)/?$', 'index.php?p=$matches[1]', 'top');
    
    // 3. 通用页面支持（放到 bottom，不抢分类和文章的生意）
    add_rewrite_rule('^([^/]+)/?$', 'index.php?pagename=$matches[1]', 'bottom');

    // 添加文章名格式的重写规则（备用）
    //add_rewrite_rule('^([^/]+)/?$', 'index.php?name=$matches[1]', 'top');
}

// 添加 HTML 编辑器快捷按钮
add_action('after_wp_tiny_mce', 'my_quicktags');
function my_quicktags() { ?>
    <script type="text/javascript">
        QTags.addButton( 'h1', 'H1无效果', "<h1>", "</h1>\n" );
		QTags.addButton( 'h2', 'H2', "<h2>", "</h2>\n" );
		QTags.addButton( 'h3', 'H3', "<h3>", "</h3>\n" );
		QTags.addButton( 'h4', 'H4', "<h4>", "</h4>\n" );
		QTags.addButton( 'pre', '代码块', "<pre>", "</pre>\n" );
		QTags.addButton( 'info', '信息全屏', '[tip type="info" display="custom-class"]', "[/tip]\n" );
		QTags.addButton( 'success', '成功全屏', '[tip type="success" display="custom-class"]', "[/tip]\n" );
		QTags.addButton( 'worning', '警告全屏', '[tip type="worning" display="custom-class"]', "[/tip]\n" );
		QTags.addButton( 'download', '下载按钮', "[download][/download]\n", "" );
		QTags.addButton( 'reply2down', '回复才可下载', "[reply2down][/reply2down]\n", "" );
		QTags.addButton( 'need_reply', '回复显示', "[need_reply][/need_reply]\n", "" );
		QTags.addButton( 'bilibili', 'bilibili', "[vbilibili][/vbilibili] \n", "" );
		QTags.addButton( 'table', '添加表格', "<figure class='table-figure'><table>\n<thead>\n<tr><th>标题一</th><th>标题二</th></tr>\n</thead>\n<tbody>\n<tr><td>标题一内容</td><td>标题二内容</td></tr>\n</tbody>\n</table></figure>\n", "" );
		QTags.addButton( 'tr', '添加表格行', "<tr><td>标题一内容</td></tr>\n", "" );
		QTags.addButton( 'td', '添加表格块', "<td>标题一内容</td>\n", "" );
		QTags.addButton( 'fmt', '文字在左图片在右', "[fmt img='image.jpg' col='4' position='r']这里是文本内容[/fmt]", "" );
		QTags.addButton( 'dropdown', '按钮形式下拉框', "[dropdown id=\"my-dropdown\" btn_type=\"btn-primary\" btn_label=\"Select an option\"]\n<li><a href=\"#\">Option 1</a></li>\n<li><a href=\"#\">Option 2</a></li>\n<li><a href=\"#\">Option 3</a></li>\n[/dropdown]\n", "" );
		QTags.addButton( 'modal', '按钮弹窗', "[modal id=\"my-modal\" btn_type=\"btn-primary\" btn_label=\"Open Modal\" title=\"弹窗标题\" close_label=\"关闭\" href_label=\"打开\" href=\"链接地址\"]\n弹窗内的说明[/modal]\n", "" );
		QTags.addButton( 'collapse', '按钮型折叠内容', "[collapse id=\"my-collapse\" btn_type=\"btn-primary\" btn_label=\"按钮名称\"]\n折叠内容\n[/collapse]\n", "" );
		QTags.addButton( 'bilibili2', 'JAVA学习bilibili视频', "\n<h2>视频</h2> \n[vbilibili]【尚硅谷2024最新Java入门视频教程（上部）java零基础入门教程】 https://www.bilibili.com/video/BV1YT4y1H7YM/?p=7&share_source=copy_web&vd_source=85f561e7442caa320f4a23b57edee129[/vbilibili]\n", "" );
		QTags.addButton( 'h2bilibili', '笔记', "<h2>笔记</h2>\n", "" );
    </script>
<?php }