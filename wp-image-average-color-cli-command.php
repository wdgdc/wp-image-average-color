<?php

class WP_Image_Average_Color_Command extends WP_CLI_Command {
	public function detect() {
		$query = new WP_Query(array(
			'post_type' => 'attachment',
			'post_status' => 'any',
			'posts_per_page' => -1,
			'post_mime_type' => array(
				'image/jpeg',
				'image/png',
				'image/gif'
			),
			'meta_query' => array(
				array(
					array(
						'key' => '_average_colors',
						'compare' => 'NOT EXISTS'
					)
				)
			)
		));

		if ($query->found_posts > 0) {
			$progress_bar = \WP_CLI\Utils\make_progress_bar( "Found $query->found_posts images...", $query->found_posts );

			if (!class_exists('ColorsOfImage')) {
				require_once( __DIR__ . '/class-colors-of-image.php');
			}

			foreach($query->posts as &$post) {
				$post->path = get_attached_file($post->ID, true);

				if( !file_exists( $post->path ) ) {
					error_log( 'Image not found: '.$post->ID );
					return 'transparent';
				}

				$post->colors = new ColorsOfImage( $post->path, 25, 3 );
				$post->prominent_colors = $post->colors->getProminentColors();
				$post->prominent_color = $post->prominent_colors[0];

				if (!empty($post->prominent_colors)) {
					update_post_meta($post->ID, '_average_colors', $post->prominent_colors, true);
					update_post_meta($post->ID, '_average_color', $post->prominent_color, true);
				}

				$progress_bar->tick();
			}

			$progress_bar->finish();
		}

		\WP_CLI\Utils\format_items('table', $query->posts, array('ID', 'prominent_color', 'prominent_colors', 'post_title'));
	}
}

WP_CLI::add_command('image-color', 'WP_Image_Average_Color_Command');
