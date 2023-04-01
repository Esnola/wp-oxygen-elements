<?php

class PostFilter extends OxyEl {

    function init() {
		add_action('wp_ajax_filterposts', [$this, 'filter_posts']); 
		add_action('wp_ajax_nopriv_filterposts', [$this, 'filter_posts']);
    }

    function afterInit() {
        $this->removeApplyParamsButton();
    }

    function name() {
        return 'Posts Filter';
    }
    
    function slug() {
        return "post-filter";
    }

    function icon() {
		return plugin_dir_url( __FILE__ ).basename(__FILE__, '.php').'.svg';
    }
	
    function controls() {

        // Extra Settings
        $extra_section = $this->addControlSection("extra_section", __("Extra Settings"), "assets/icon.png", $this);
        $ppp_option = $extra_section->addOptionControl(
            array(
                "type" => 'textfield',
                "name" => 'Posts per page',
                "slug" => 'posts_per_page'
            )
        );
        $ppp_option->rebuildElementOnChange();
        $ppp_option->setDefaultValue('10');
        $ppp_option->whitelist();

        $infinite_option = $extra_section->addOptionControl(
            array(
                "type" => 'checkbox',
                "name" => 'Infinite Scroll',
                "slug" => 'infinite_scroll'
            )
        );
		$infinite_option->setDefaultValue('false');

        // Filter
        $filter_section = $this->addControlSection("filter_section", __("Filter"), "assets/icon.png", $this);
        $filter_section->typographySection(
            __("Typography"),
            '.woe-radio-toolbar label',
            $this
        );
        $filter_section->borderSection(
            __("Borders"),
            ".woe-radio-toolbar label",
            $this
        );
        $filter_section->addStyleControl(
            array(
                "name" => __('Background Color'),
                "selector" => ".woe-radio-toolbar label",
                "property" => 'background-color',
                "control_type" => "colorpicker"
            )
        );

        // Filter Active
        $filter_active_section = $this->addControlSection("filter_active_section", __("Filter Active"), "assets/icon.png", $this);
        $filter_active_section->typographySection(
            __("Typography"),
            ".woe-radio-toolbar input[type='radio']:checked+label",
            $this
        );
        $filter_active_section->borderSection(
            __("Borders"),
            ".woe-radio-toolbar input[type='radio']:checked+label",
            $this
        );
        $filter_active_section->addStyleControl(
            array(
                "name" => __('Background Color'),
                "selector" => ".woe-radio-toolbar input[type='radio']:checked+label",
                "property" => 'background-color',
                "control_type" => "colorpicker"
            )
        );

        // Title
        $title_section = $this->addControlSection("title_section", __("Title"), "assets/icon.png", $this);
        $title_section->typographySection(
            __("Typography"),
            '.woe-post__heading',
            $this
        );

        // Excerpt
        $excerpt_section = $this->addControlSection("excerpt_section", __("Excerpt"), "assets/icon.png", $this);
        $excerpt_section->typographySection(
            __("Typography"),
            '.woe-post__text',
            $this
        );

        // Categories
        $terms = get_terms( array( 'taxonomy' => 'category', 'orderby' => 'name' ) );
        foreach ($terms as $term) {
            $category_section = $this->addControlSection($term->slug, __("Category " . $term->name), "assets/icon.png", $this);
            $category_section->addStyleControl(
                array(
                    "name" => __('Background Color'),
                    "selector" => '.' . $term->slug . '',
                    "property" => 'background-color',
                    "control_type" => "colorpicker"
                )
            );
            $category_section->typographySection(
                __("Typography"),
                '.' . $term->slug . '',
                $this
            );
        }
        
    }

    function defaultCSS() {
        return file_get_contents(__DIR__.'/'.basename(__FILE__, '.php').'.css');
    }

    function render($options, $defaults, $content) {
		?>
		<form action="<?php echo esc_url( site_url() ) ?>/wp-admin/admin-ajax.php" method="POST" id="filterForm">

			<?php
			if( $terms = get_terms( array( 'taxonomy' => 'category', 'orderby' => 'name' ) ) ) {
				$output = "";

				echo '<div class="woe-radio-toolbar">';

				foreach ( $terms as $term ) {

					$input = '<input id="' . esc_html( $term->name ) . '" type="radio" class="woe-posts-filter" name="posts_category" value="' . esc_html( $term->term_id ) . '" />';
					$label =  '<label for="' . esc_html( $term->name ) . '" class="woe-radio-filter">' . esc_html( $term->name ) . " (" . esc_html( $term->count ) . ') </label>';

					$output .= $input . $label;
				}

				echo '<input id="all" type="radio" class="woe-posts-filter" name="posts_category" value="-1" checked />';
				echo '<label for="all">All (' . wp_count_posts( $post_type = 'post' )->publish . ')</label>';
				echo $output;
				echo '</select>';
				echo '</div>';
			}
			?>
			
			<input type="hidden" name="infinite_scroll" value="<?= $options['infinite_scroll'] ?>">
			<input type="hidden" name="posts_per_page" value="<?= $options['posts_per_page'] ?>">
			<input type="hidden" name="action" value="filterposts">
		</form> 

		<div id="woe-response"/>
		<?php

		$this->El->inlineJS(file_get_contents(__DIR__.'/'.basename(__FILE__, '.php').'.js'));
    }
    
	function filter_posts() {
		$paged = 1;
		$posts_per_page = 10;

		if( isset($_POST['paged'])) {
			$paged = sanitize_text_field( $_POST['paged'] );
		}
		
		if( isset($_POST['posts_per_page'])) {
			$posts_per_page = sanitize_text_field( $_POST['posts_per_page'] );
		}

		$args = array(
			'post_type' => 'post',
			'post_status' => 'publish',
			'paged' => $paged,
			'posts_per_page' => $posts_per_page
		);

		if( isset( $_POST['posts_category'] ) &&  $_POST['posts_category'] != -1 )
			$args['tax_query'] = array(
			array(
				'taxonomy' => 'category',
				'field' => 'id',
				'terms' => sanitize_text_field( $_POST['posts_category'] )
			)
		);

		$query = new WP_Query( $args );

		if( $query->have_posts() ) {
			
			if ( $paged == 1 ) {
				echo '<div class="woe-posts">';
			}
			
			while ( $query->have_posts() ) {
				$query->the_post(); ?>
				
				<div class="woe-post woe-loading woe-lazy">

					<div class="woe-post__body-container">
						<div class="woe-post__category-container">
							<?php 
								foreach ( ( get_the_category() ) as $category ) {
									echo '<div class="woe-post__category ' . esc_html( $category->slug ) . '">' . esc_html( $category->cat_name ) . '</div>';
								} 
							?>
						</div>

						<h4 class="woe-post__heading">
							<a href="<?php esc_url( the_permalink() ); ?>"><?php esc_html( the_title() ); ?></a>
						</h4>

						<p class="woe-post__text">
							<?php echo  esc_html( get_the_excerpt() ); ?>
						</p> 
					</div>

					<div class="woe-post__image-wrapper">
						<?php
							$id = get_post_thumbnail_id();
							$attachment = wp_get_attachment_image_src( $id, 'full');
							$src 	= wp_get_attachment_image_url( $id, 'full' );
							$srcset = wp_get_attachment_image_srcset( $id, 'full' );
							$sizes 	= wp_get_attachment_image_sizes( $id, 'full' );

							echo '<img class="woe-post__image" data-src="'. $src .'" data-srcset="' . $srcset . '" sizes="' . $sizes . '" />';
						?>
					</div>

					<div class="woe-post__footer">
						<p class="woe-post__link">Read More</p>
					</div>
				</div>
				<?php
			}
			
			if ( $paged == 1 ) {
				echo '</div>';
				
				if ($query->max_num_pages > 1 && $paged < $query->max_num_pages) {
					echo '<button 
                            class="woe-posts__load-more" 
                            data-current-page="'. esc_html( $paged ) .'" 
                            data-next-page="'. esc_html( ($paged + 1) ) .'" 
                            data-max-page="'. esc_html( $query->max_num_pages ) .'" 
                            onClick="loadMore()">Load more</button>';
				}
			}

			wp_reset_postdata();
		}
		else {
			echo 'No posts found';
		}
		
		die();
	}	
}

new PostFilter();
