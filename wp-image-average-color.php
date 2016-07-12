<?php
/*
Plugin Name: WP Image Average Color
Plugin URI: http://github.com/WDGDC/wp-image-average-color
Description: Insert the average background color into the image metadata
Author: WDGDC, Kurtis Shaner
Author URI: http://github.com/WDGDC
Version: 0.0.1
Text Domain: wp-image-average-color
License: MIT
*/

final class WP_Image_Average_Color {

	private static $_instance;
	public static function instance() {
		if ( !isset( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * constructor
	 * @access private
	 */

	 private function __construct() {
		add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));

		add_filter('wp_generate_attachment_metadata', array($this, 'wp_generate_attachment_metadata'), 10, 2);
		add_filter('attachment_fields_to_edit', array($this, 'edit_attachment'), 10, 2); // render
		add_filter('attachment_fields_to_save', array($this, 'save_attachment'), 10, 2); // save
		add_filter('wp_get_attachment_image_attributes', array($this, 'wp_get_attachment_image_attributes'), 10, 3);
		add_filter('get_image_tag', array($this, 'get_image_tag'), 10, 6);
	}

	/**
	 * wp_get_attachment_image_attributes
	 * @filter wp_get_attachment_image_attributes
	 */

	 public function wp_get_attachment_image_attributes($attr, $attachment, $size) {
		if ($color = get_post_meta($attachment->ID, '_average_color', true)) {
			$attr['style'] = 'background-color: '. $color.';';
		}
		return $attr;
	}

	public function get_image_tag($html, $id, $alt, $title, $align, $size) {
		if ($color = get_post_meta($id, '_average_color', true)) {
			$html = str_replace('>', ' style="background-color:'.$color.';">', $html);
		}
		return $html;
	}

	public function admin_enqueue_scripts() {
		wp_enqueue_style('wp-image-average-color', plugins_url('wp-image-average-color.css', __FILE__));
	}

	public function wp_generate_attachment_metadata($metadata, $id) {

		if (!class_exists('ColorsOfImage')) {
			require_once( __DIR__ . '/class-colors-of-image.php');
		}

		$upload_dir = wp_upload_dir();
		$path = $upload_dir['basedir'] . '/' . $metadata['file'];

		if( !file_exists( $path ) ) {
			error_log( 'Image not found: '.$image );
			return 'transparent';
		}

		$colors = new ColorsOfImage( $path, 25, 3 );
		$prominent_colors = $colors->getProminentColors();

		update_post_meta($id, '_average_colors', $prominent_colors, true);
		update_post_meta($id, '_average_color', $prominent_colors[0], true);

		return $metadata;
	}

	public function edit_attachment($fields, $attachment) {
		$colors = get_post_meta($attachment->ID, '_average_colors', true);

		if (!empty($colors)) {
			$color = get_post_meta($attachment->ID, '_average_color', true);

			$input = '';
			foreach($colors as $c) {
				$input .= sprintf(
					'<label><input type="radio" value="%1$s" name="attachments[%2$s][_average_color]"%3$s><span style="background-color:%1$s"></span></label>',
					$c,
					$attachment->ID,
					($c === $color) ? ' checked' : ''
				);
			}

			$fields['average_colors'] = array(
				'label' => 'Average Colors',
				'input' => 'html',
				'html'  => $input,
				'value' => $color,
				'helps' => __('Select a primary color for this image')
			);
		}

		return $fields;
	}

	public function save_attachment($post, $attachment) {
		if (isset($attachment['_average_color'])) {
			update_post_meta($post['ID'], '_average_color', $attachment['_average_color']);
		}
	}

	public function get_image_color($id) {
		if ($color = get_post_meta($id, '_average_color', true)) {
			return $color;
		}

		return null;
	}
}

function wp_image_average_color($id) {
	return WP_Image_Average_Color::instance()->get_image_color($id);
}

if (defined('WP_CLI') && WP_CLI) {
	require_once(__DIR__ . '/wp-image-average-color-cli-command.php');
}

WP_Image_Average_Color::instance();
