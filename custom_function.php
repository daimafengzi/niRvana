<?php
/**
 * 主题自定义功能文件 - niRvana
 * 终极版：全站标题净化 + 统一自动编号系统
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

/**
 * 核心功能：全站内容标题“净化”
 * 自动识别并移除 H2-H4 标题中手动输入的编号（如：一、 1. 1.1 等）
 * 确保全站视觉统一采用 CSS 自动编号，解决新老文章碰撞导致的乱序问题
 */
function nirvana_clean_heading_numbers($content) {
    if (is_singular()) {
        // 正则解释：增加 'u' 修正符以支持 UTF-8 中文字符精准匹配
        $pattern = '/(<h[2-4][^>]*>)\s*([一二三四五六七八九十\d]{1,3}[、\.．\s]\s*|[\d]+\.[\d]+\s*)/isu';
        $content = preg_replace($pattern, '$1', $content);
    }
    return $content;
}
add_filter('the_content', 'nirvana_clean_heading_numbers', 1);

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

function delete_post_and_attachments($post_ID) {
    $attachments = get_posts(['post_type' => 'attachment', 'post_parent' => $post_ID, 'post_status' => 'any', 'posts_per_page' => -1]);
    foreach ($attachments as $attachment) { wp_delete_attachment($attachment->ID, true); }
    $thumbnail_id = get_post_meta($post_ID, '_thumbnail_id', true);
    if ($thumbnail_id) { wp_delete_attachment($thumbnail_id, true); }
}
add_action('before_delete_post', 'delete_post_and_attachments');

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
add_filter('the_content', 'tag_link', 10); // 放在净化之后执行

function catch_first_image( $post_id = null ) {
    if ( $post_id ) { $content = get_post($post_id)->post_content; } else { global $post; $content = $post->post_content; }
    if (preg_match('/<img.+src=[\'"]([^\'"]+)[\'"].*>/i', $content, $matches)) { $img_url = $matches[1]; } 
    else { $img_url = get_stylesheet_directory_uri() . '/assets/imgs/rand/' . rand(1, 10) . '.png'; }
    return $img_url;
}

// ================== 4. 编辑器完全增强 ==================
add_action('after_wp_tiny_mce', function() { ?>
    <script type="text/javascript">
		QTags.addButton( 'h2', 'H2', "<h2>", "</h2>\n" );
		QTags.addButton( 'h3', 'H3', "<h3>", "</h3>\n" );
		QTags.addButton( 'h4', 'H4', "<h4>", "</h4>\n" );
		QTags.addButton( 'pre', '代码块', "<pre>", "</pre>\n" );
		QTags.addButton( 'vbilibili', 'B站视频', "[vbilibili]", "[/vbilibili] \n" );
		QTags.addButton( 'download', '下载按钮', "[download]", "[/download]\n" );
		QTags.addButton( 'collapse', '折叠面板', "[collapse btn_label=\"点击展开\"]内容[/collapse]\n" );
    </script>
<?php }); // ================== 冗余样式已迁移至外部文件 ==================
add_action('admin_init', function() {
    if (!current_user_can('edit_posts') && !current_user_can('edit_pages')) return;
    if (get_user_option('rich_editing') == 'true') {
        add_filter('mce_external_plugins', function($plugin_array) {
           $plugin_array['niRvana_mce_button'] = get_stylesheet_directory_uri() . '/assets/js/tinymce-buttons.js';
           return $plugin_array;
        });
        add_filter('mce_buttons_2', function($buttons) {
            return array_merge($buttons, ['ni_h2', 'ni_h3', 'ni_h4', 'ni_pre', 'ni_vbilibili', 'ni_download', 'ni_collapse']);
        });
    }
});

// ================== 5. 短代码渲染逻辑 (Shortcodes) ==================
add_shortcode('vbilibili', function($atts, $content = null) {
    if (empty($content)) return '';
    $content = trim(strip_tags($content));
    $id = '';
    if (preg_match('/video\/(BV[a-zA-Z0-9]+)/', $content, $matches)) { $id = $matches[1]; } else { $id = $content; }
    $p = 1;
    if (preg_match('/p=(\d+)/', $content, $matches)) { $p = $matches[1]; }
    return '<div class="video-container" style="position:relative;padding-bottom:56.25%;height:0;overflow:hidden;margin:20px 0;border-radius:8px;box-shadow:0 10px 30px rgba(0,0,0,0.1);background:#000;">
                <iframe src="//player.bilibili.com/player.html?bvid='.$id.'&page='.$p.'&high_quality=1&danmaku=0" loading="lazy" scrolling="no" border="0" frameborder="no" framespacing="0" allowfullscreen="true" style="position:absolute;top:0;left:0;width:100%;height:100%;"></iframe>
            </div>';
});

add_shortcode('download', function($atts, $content = null) {
    static $directDownload_times = 0;
    $directDownload_times++;
    
    // 获取后台定义的版权说明
    $licence = get_option('版权说明');
    if (!$licence) {
        $licence = '<p>本站提供的下载内容版权归本站所有。转载 <span style="color:#ff7800">必须</span> 注明出处！</p><p style="font-size:80%; color:#888;">* 标有 “转载” 字样的文章，内容版权归原作者所有。</p>';
    } else {
        $licence = wpautop(str_ireplace('img', 'div', $licence));
    }

    // 渲染 NiRvana 特色的双层按钮结构
    return '
    <div type="button" class="getit" data-toggle="modal" data-target="#directDownload_'.$directDownload_times.'">
        <a style="cursor:pointer;"><span>Get it!</span><span>Download</span></a>
    </div>
    <div class="modal fade" id="directDownload_'.$directDownload_times.'" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title">下载提示与版权说明</h4>
                </div>
                <div class="modal-body">'.$licence.'</div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">不同意</button>
                    <button type="button" class="btn btn-primary" data-dismiss="modal" onclick=window.open("'.trim($content).'")>同意并下载</button>
                </div>
            </div>
        </div>
    </div>';
});

add_shortcode('collapse', function($atts, $content = null) {
    extract(shortcode_atts(['btn_label' => '点击展开'], $atts));
// ================== 6. 找回丢失的核心短代码 (Article, Reply, Video, etc.) ==================

/**
 * [article id="123"] 文章内链引用
 */
add_shortcode('article', function($atts) {
    extract(shortcode_atts(['id' => ''], $atts));
    if (empty($id)) return '';
    $post = get_post($id);
    if ($post) {
        return '<div class="ni-article-cite"><a href="' . get_permalink($id) . '" target="_blank"><i class="fa fa-link"></i> ' . get_the_title($id) . '</a></div>';
    }
    return '';
});

/**
 * [reply] 评论可见内容 [/reply]
 */
add_shortcode('reply', function($atts, $content = null) {
    extract(shortcode_atts(['notice' => '<div class="ni-reply-notice"><i class="fa fa-lock"></i> 温馨提示：此处内容需要<a href="#respond">评论本文</a>后刷新才能查看！</div>'], $atts));
    
    $email = null;
    $user_ID = (int)wp_get_current_user()->ID;
    
    if ($user_ID > 0) {
        $email = wp_get_current_user()->user_email;
        if ($user_ID == 1) return do_shortcode($content); // 管理员豁免
    } else if (isset($_COOKIE['comment_author_email_' . COOKIEHASH])) {
        $email = str_replace('%40', '@', $_COOKIE['comment_author_email_' . COOKIEHASH]);
    }
    
    if ($email) {
        // 调用 functions.php 中的验证函数
        if (function_exists('pf_user_has_approved_comment_in_post')) {
            if (pf_user_has_approved_comment_in_post(get_the_ID(), $email)) {
                return do_shortcode($content);
            }
        }
    }
    return $notice;
});

/**
 * [video img="封面图"]视频地址[/video] 原生视频播放
 */
add_shortcode('video', function($atts, $content = null) {
    extract(shortcode_atts(['img' => ''], $atts));
    return '<div class="ni-video-wrap"><video src="' . trim($content) . '" poster="' . $img . '" controls="controls" style="width:100%; border-radius:8px;"></video></div>';
});

/**
 * [fmt] 图文格式化短代码 (兼容老文章)
 */
add_shortcode('fmt', 'shortCodeArticleFormat');