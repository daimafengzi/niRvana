(function() {
    function add_ni_button(ed, name, title, open_tag, close_tag) {
        ed.addButton(name, {
            title: title,
            text: title,
            onclick: function() {
                ed.selection.setContent(open_tag + ed.selection.getContent() + close_tag);
            }
        });
    }

    tinymce.create("tinymce.plugins.niRvanaShortcodes", {
        init : function(ed, url) {
            // 第二排功能按钮
            add_ni_button(ed, "ni_h2", "H2", "<h2>", "</h2>");
            add_ni_button(ed, "ni_h3", "H3", "<h3>", "</h3>");
            add_ni_button(ed, "ni_h4", "H4", "<h4>", "</h4>");
            add_ni_button(ed, "ni_pre", "代码块", "<pre>", "</pre>");
            add_ni_button(ed, "ni_info", "蓝色框", '[tip type="info"]', "[/tip]");
            add_ni_button(ed, "ni_success", "绿色框", '[tip type="success"]', "[/tip]");
            add_ni_button(ed, "ni_warning", "红色框", '[tip type="worning"]', "[/tip]");
            
            ed.addButton("ni_table", { title: "插入表格", text: "完整表格", onclick: function() {
                ed.insertContent("<figure class=\"table-figure\"><table><thead><tr><th>标题一</th><th>标题二</th></tr></thead><tbody><tr><td>内容一</td><td>内容二</td></tr></tbody></table></figure>");
            }});
            add_ni_button(ed, "ni_tr", "加行", "<tr>", "</tr>");
            add_ni_button(ed, "ni_td", "加列", "<td>", "</td>");

            // 第三排功能按钮
            add_ni_button(ed, "ni_download", "下载", "[download]", "[/download]");
            add_ni_button(ed, "ni_reply2down", "回复下载", "[reply2down]", "[/reply2down]");
            add_ni_button(ed, "ni_need_reply", "回复可见", "[need_reply]", "[/need_reply]");
            add_ni_button(ed, "ni_vbilibili", "B站视频", "[vbilibili]", "[/vbilibili]");
            add_ni_button(ed, "ni_fmt", "图文排版", '[fmt img="image.jpg" position="r"]', "[/fmt]");
            add_ni_button(ed, "ni_dropdown", "下拉菜单", '[dropdown btn_label="下拉选择"]', "[/dropdown]");
            add_ni_button(ed, "ni_modal", "弹窗", '[modal btn_label="打开弹窗" title="标题"]', "[/modal]");
            add_ni_button(ed, "ni_collapse", "折叠面板", '[collapse btn_label="点击展开"]', "[/collapse]");
            add_ni_button(ed, "ni_bilibili2", "全屏笔记", '[bilibili id="" p="" h2=""]', "");
            add_ni_button(ed, "ni_h2bilibili", "全屏视频", '[h2bilibili id="" p=""]', "");
        }
    });
    tinymce.PluginManager.add("niRvana_mce_button", tinymce.plugins.niRvanaShortcodes);
})();
