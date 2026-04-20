<?php
/*
Template Name: 友情链接
*/
get_header();
echo "<!-- I AM LINKS TEMPLATE -->";
?>
<?php
$frontpage_carousels_type = _opt( 'frontpage_carousels_type' );
$type = strstr( $frontpage_carousels_type, 'full' ) ? 'single-imageflow-full' : 'single-imageflow';
// get_topSlider( array( $post->ID ), $type );
?>
<div class="container postListsModel">
	<div class="row">
		<?php
		if ( _opt( 'is_single_post_hide_sidebar' ) ) {
			$leftClass = 'col-xs-12 no-sidebar';
			$rightClass = 'hidden';
		} else {
			$leftClass = 'col-md-9 col-lg-9_5';
			$rightClass = 'col-md-3 col-lg-2_5 hidden-xs hidden-sm';
		}
		?>
		<div class="<?php echo $leftClass; ?>">
			<div class="col-xs-12">
				<div class="row postLists">
					<div class="toggle_sidebar" @click="this.single_toggle_sidebar()" data-toggle="tooltip" data-placement="auto top" title="切换边栏"><i class="fas fa-angle-right"></i></div>
					<div class="article_wrapper post clearfix page">
						<article class="clearfix">
							<?php
							$categories = get_categories(
								array(
									'hide_empty' => 0,
									'taxonomy'   => 'favlinks-category',
									'orderby'    => 'slug',
								)
							);
							if ( empty($categories) ) {
								echo '<div style="text-align:center;padding:50px 0;"><i class="fas fa-link" style="font-size:3em;color:#eee;display:block;margin-bottom:15px;"></i><p style="color:#999;">您还没有创建友情链接分类或添加链接。<br>请前往后台【友情链接】菜单，先创建分类，再发布链接。</p></div>';
							}
							for ( $i = 0; $i < count( $categories ); $i++ ) {
								$category = $categories[ $i ];
								echo '<div class="title_style_01 favlinks_title" id="favlink-' . $i . '"><h2>' . $category->name . '</h2></div><div class="favlinks-group clearfix">';
								$args       = array(
									'post_type'      => 'favlinks',
									'posts_per_page' => 100,
									'tax_query'      => array(
										array(
											'taxonomy' => 'favlinks-category',
											'field'    => 'name',
											'terms'    => $category->name,
										),
									),
								);
								$query_posts = new WP_Query();
								$query_posts->query( $args );
								while ( $query_posts->have_posts() ) {
									$query_posts->the_post();
									require 'assets/template/postlist-favlinks.php';
								}
								wp_reset_query();
								echo '</div>';
							}
							?>
							<?php the_content(); ?>
						</article>
					</div>
					<?php comments_template(); ?>
				</div>
			</div>
		</div>
		<div class="<?php echo $rightClass; ?>">
			<div class="row">
				<div class="sidebar sidebar-affix">
					<div manual-template="sidebarMenu"></div>
					<div manual-template="sidebar"></div>
				</div>
			</div>
		</div>
	</div>
</div>
<?php get_footer(); ?>