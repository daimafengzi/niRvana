<?php
/**
 * 主题自定义功能文件 - niRvana
 * 终极增强版：全站标题净化 + 旧版功能满血回归 + 架构优化
 */

// ================== 1. 基础优化与安全 ==================
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

// ================== 2. 核心净化引擎 (Heading Clean & CSS Counter Support) ==================

/**
 * 自动识别并移除 H2-H4 标题中手动输入的编号
 */
function nirvana_clean_heading_numbers($content) {
    if (is_singular()) {
        $pattern = '/(<h[2-4][^>]*>)\s*([一二三四五六七八九十\d]{1,3}[、\.．\s]\s*|[\d]+\.[\d]+\s*)/isu';
        $content = preg_replace($pattern, '$1', $content);
    }
    return $content;
}
add_filter('the_content', 'nirvana_clean_heading_numbers', 1);

// ================== 3. SEO 与 文章功能增强 (从旧版迁移) ==================

/**
 * 自动为文章添加标签（基于内容匹配）
 */
add_action('save_post', 'auto_add_tags', 10, 3);
function auto_add_tags($post_id, $post, $update) {
    if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id) || $post->post_status !== 'publish') return;
    if (!in_array($post->post_type, ['post'])) return;
    $tags = get_tags(['number' => 100, 'orderby' => 'count', 'order' => 'DESC']);
    if (!$tags) return;
    foreach ($tags as $tag) {
        if (strpos($post->post_content, $tag->name) !== false) {
            wp_set_post_tags($post_id, $tag->name, true);
        }
    }
}

/**
 * 标签内链核心逻辑 (旧版长词优先策略 + 保护代码块)
 */
function nirvana_tag_link_sort($a, $b) {
    return (strlen($a->name) == strlen($b->name)) ? 0 : ((strlen($a->name) > strlen($b->name)) ? -1 : 1);
}

function nirvana_tag_link_engine($content) {
    if (!is_single()) return $content;
    $post_id = get_the_ID();
    $cache_key = "ni_tag_link_{$post_id}";
    $cached = wp_cache_get($cache_key, 'nirvana');
    if ($cached !== false) return $cached;

    $posttags = get_the_tags();
    if (!$posttags) {
        wp_cache_set($cache_key, $content, 'nirvana', 3600);
        return $content;
    }

    usort($posttags, "nirvana_tag_link_sort");
    $protected = []; $idx = 0;
    $content = preg_replace_callback('/<(pre|code|script|style|textarea|button|a|bilibili)[^>]*>.*?<\/\\1>/isU', function($m) use (&$protected, &$idx) {
        $key = "###PROT_{$idx}###";
        $protected[$key] = $m[0];
        $idx++;
        return $key;
    }, $content);

    foreach ($posttags as $tag) {
        $link = get_tag_link($tag->term_id);
        $name = preg_quote($tag->name, '/');
        $url = "<a href=\"{$link}\" title=\"查看更多关于 {$tag->name} 的文章\" target=\"_blank\">{$tag->name}</a>";
        $content = preg_replace('/\b(' . $name . ')\b/i', $url, $content, 1);
    }

    $content = str_replace(array_keys($protected), array_values($protected), $content);
    wp_cache_set($cache_key, $content, 'nirvana', 3600);
    return $content;
}
add_filter('the_content', 'nirvana_tag_link_engine', 12);

/**
 * Bing IndexNow 自动推送
 */
add_action('save_post', 'nirvana_indexnow_push', 25, 3);
function nirvana_indexnow_push($post_id, $post, $update) {
    if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id) || $post->post_status !== 'publish') return;
    $key = '173bc4cbffe9447f920afd212df83a4b';
    wp_remote_post('https://www.bing.com/indexnow', [
        'body' => json_encode(['host' => parse_url(home_url())['host'], 'key' => $key, 'urlList' => [get_permalink($post_id)]]),
        'headers' => ['Content-Type' => 'application/json'],
        'blocking' => false, 'sslverify' => false
    ]);
}

// ================== 4. 附件与文件处理 ==================

/**
 * 附件重命名 (防中文名乱码)
 */
add_filter('wp_handle_upload_prefilter', function($file) {
    if (preg_match("/[\x{4e00}-\x{9fff}]/u", $file['name'])) {
        $file['name'] = date("YmdHis") . mt_rand(100, 999) . "." . pathinfo($file['name'], PATHINFO_EXTENSION);
    }
    return $file;
});

/**
 * 允许上传压缩包
 */
add_filter('upload_mimes', function($mimes) {
    $mimes['rar'] = 'application/rar';
    $mimes['zip'] = 'application/zip';
    return $mimes;
});

/**
 * 自动删除文章附件
 */
function nirvana_delete_attachments($post_ID) {
    $attachments = get_posts(['post_type' => 'attachment', 'post_parent' => $post_ID, 'posts_per_page' => -1]);
    foreach ($attachments as $attachment) { wp_delete_attachment($attachment->ID, true); }
}
add_action('before_delete_post', 'nirvana_delete_attachments');

// ================== 5. 短代码系统 (Shortcodes) ==================

// B站视频
add_shortcode('vbilibili', function($atts, $content = null) {
    if (empty($content)) return '';
    $id = preg_match('/video\/(BV[a-zA-Z0-9]+)/', $content, $m) ? $m[1] : trim(strip_tags($content));
    $p = preg_match('/p=(\d+)/', $content, $m) ? $m[1] : 1;
    return '<div class="video-container" style="position:relative;padding-bottom:56.25%;height:0;overflow:hidden;margin:20px 0;border-radius:8px;box-shadow:0 10px 30px rgba(0,0,0,0.1);background:#000;">
                <iframe src="//player.bilibili.com/player.html?bvid='.$id.'&page='.$p.'&high_quality=1&danmaku=0" loading="lazy" scrolling="no" border="0" frameborder="no" allowfullscreen="true" style="position:absolute;top:0;left:0;width:100%;height:100%;"></iframe>
            </div>';
});

// 高级下载按钮
add_shortcode('download', function($atts, $content = null) {
    static $dl_idx = 0; $dl_idx++;
    $licence = get_option('版权说明') ?: '<p>本站下载内容版权归属本站，转载请注明出处。</p>';
    return '<div class="getit" data-toggle="modal" data-target="#dl_'.$dl_idx.'"><a style="cursor:pointer;"><span>Get it!</span><span>Download</span></a></div>
    <div class="modal fade" id="dl_'.$dl_idx.'" tabindex="-1" role="dialog">
        <div class="modal-dialog"><div class="modal-content">
            <div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title">下载与版权协议</h4></div>
            <div class="modal-body">'.wpautop($licence).'</div>
            <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">取消</button><button type="button" class="btn btn-primary" onclick=window.open("'.trim($content).'")>同意并下载</button></div>
        </div></div>
    </div>';
});

// 回复可见下载
add_shortcode('reply2down', function($atts, $content = null) {
    static $r2d_idx = 0; $r2d_idx++;
    $notice = '<div class="getit" data-toggle="modal" data-target="#r2d_'.$r2d_idx.'"><a style="cursor:pointer;"><span>Get it!</span><span>Reply & Down</span></a></div>
    <div class="modal fade" id="r2d_'.$r2d_idx.'" tabindex="-1" role="dialog">
        <div class="modal-dialog"><div class="modal-content">
            <div class="modal-header"><h4 class="modal-title">下载提示</h4></div>
            <div class="modal-body">'.wpautop(get_option('回复可见说明') ?: '请在该文章下认真评论后再下载！').'</div>
            <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">知道了</button></div>
        </div></div>
    </div>';
    $email = is_user_logged_in() ? wp_get_current_user()->user_email : ($_COOKIE['comment_author_email_' . COOKIEHASH] ?? null);
    if ($email && function_exists('pf_user_has_approved_comment_in_post') && pf_user_has_approved_comment_in_post(get_the_ID(), str_replace('%40', '@', $email))) {
        return do_shortcode('[download]'.$content.'[/download]');
    }
    return current_user_can('manage_options') ? do_shortcode('[download]'.$content.'[/download]') : $notice;
});

// 回复可见内容
add_shortcode('reply', function($atts, $content = null) {
    $notice = '<div class="ni-reply-notice"><i class="fa fa-lock"></i> 内容隐藏：请在下方<a href="#respond">发表评论</a>后刷新查看！</div>';
    $email = is_user_logged_in() ? wp_get_current_user()->user_email : ($_COOKIE['comment_author_email_' . COOKIEHASH] ?? null);
    if ($email && function_exists('pf_user_has_approved_comment_in_post') && pf_user_has_approved_comment_in_post(get_the_ID(), str_replace('%40', '@', $email))) {
        return do_shortcode($content);
    }
    return current_user_can('manage_options') ? do_shortcode($content) : $notice;
});

// 表情包面值
add_shortcode('face', function($atts) {
    extract(shortcode_atts(['p' => '', 'g' => ''], $atts));
    $name = $p ?: $g; $ext = $p ? 'png' : 'gif';
    return '<img src="'.get_stylesheet_directory_uri().'/faces/'.$name.'.'.$ext.'" class="cmt_faces" style="display:inline;vertical-align:middle;">';
});

// 极简折叠
add_shortcode('collapse', function($atts, $content = null) {
    extract(shortcode_atts(['btn_label' => '点击展开'], $atts));
    return '<div class="ni-collapse"><div class="ni-collapse-head">'.$btn_label.'</div><div class="ni-collapse-body">'.do_shortcode($content).'</div></div>';
});

// 文章内链引用
add_shortcode('article', function($atts) {
    extract(shortcode_atts(['id' => ''], $atts));
    $post = get_post($id);
    return $post ? '<div class="ni-article-cite"><a href="'.get_permalink($id).'" target="_blank"><i class="fa fa-link"></i> '.get_the_title($id).'</a></div>' : '';
});

// 图文格式化
add_shortcode('fmt', function($atts, $content = null) {
    extract(shortcode_atts(['img' => '', 'col' => '4', 'position' => 'r'], $atts));
    $float = ($position == 'r') ? 'right' : 'left';
    return '<div class="ni-fmt clearfix" style="margin:20px 0;"><div class="col-sm-'.$col.'" style="float:'.$float.';"><img src="'.$img.'" style="border-radius:4px;width:100%;"></div><div class="col-sm-'.(12-$col).'">'.do_shortcode(wpautop($content)).'</div></div>';
});

// ================== 6. 可视化编辑器 (TinyMCE) 增强 ==================

/**
 * 注册 TinyMCE 外部插件 JS
 */
add_filter('mce_external_plugins', function($plugin_array) {
    if (current_user_can('edit_posts') || current_user_can('edit_pages')) {
        $plugin_array['niRvana_mce_button'] = get_stylesheet_directory_uri() . '/assets/js/tinymce-buttons.js';
    }
    return $plugin_array;
});

/**
 * 将按钮添加至编辑器工具栏 (分布在第 2 排和第 3 排)
 */
add_filter('mce_buttons_2', function($buttons) {
    $new_buttons = ['ni_h2', 'ni_h3', 'ni_h4', 'ni_pre', 'ni_info', 'ni_success', 'ni_warning', 'ni_table', 'ni_tr', 'ni_td'];
    return array_merge($buttons, $new_buttons);
});

add_filter('mce_buttons_3', function($buttons) {
    $new_buttons = ['ni_download', 'ni_reply2down', 'ni_need_reply', 'ni_vbilibili', 'ni_fmt', 'ni_dropdown', 'ni_modal', 'ni_collapse'];
    return array_merge($buttons, $new_buttons);
});

// 编辑器 HTML 模式 (Quicktags) 增强
add_action('after_wp_tiny_mce', function() { ?>
    <script type="text/javascript">
        if (typeof QTags !== 'undefined') {
            QTags.addButton('ni_h2', 'H2', "<h2>", "</h2>\n");
            QTags.addButton('ni_h3', 'H3', "<h3>", "</h3>\n");
            QTags.addButton('ni_code', '代码', "<pre>", "</pre>\n");
            QTags.addButton('ni_table', '表格', "<figure class='table-figure'><table><thead><tr><th>标题</th><th>标题</th></tr></thead><tbody><tr><td>内容</td><td>内容</td></tr></tbody></table></figure>\n");
            QTags.addButton('ni_r2d', '回复下载', '[reply2down]', '[/reply2down]');
            QTags.addButton('ni_face', '表情', '[face p="1"]');
            QTags.addButton('ni_bili', 'B站视频', '[vbilibili]', '[/vbilibili]');
        }
    </script>
<?php });

// 首图获取
function catch_first_image($post_id = null) {
    $content = $post_id ? get_post($post_id)->post_content : get_post()->post_content;
    if (preg_match('/<img.+src=[\'"]([^\'"]+)[\'"].*>/i', $content, $m)) return $m[1];
    return get_stylesheet_directory_uri() . '/assets/imgs/rand/' . rand(1, 10) . '.png';
}

// 伪静态重写修复
add_action('init', function() {
    add_rewrite_rule('^([0-9]+)\.html$', 'index.php?p=$matches[1]', 'top');
    add_rewrite_rule('^([0-9]+)/?$', 'index.php?p=$matches[1]', 'top');
});