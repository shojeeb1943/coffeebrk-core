<?php

use Elementor\Core\DynamicTags\Tag;
use Elementor\Core\DynamicTags\Data_Tag;
use Elementor\Modules\DynamicTags\Module as DynModule;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Converts an ISO 8601 duration (PT45S, PT1M5S) into M:SS for display.
 */
function coffeebrk_format_youtube_duration( string $iso8601 ) : string {
	if ( ! preg_match( '/^PT(?:(\d+)M)?(?:(\d+)S)?$/', $iso8601, $m ) ) {
		return '';
	}

	$minutes = isset( $m[1] ) ? (int) $m[1] : 0;
	$seconds = isset( $m[2] ) ? (int) $m[2] : 0;

	return sprintf( '%d:%02d', $minutes, $seconds );
}

class Coffeebrk_Story_Channel_Tag extends Tag {

	public function get_name() {
		return 'coffeebrk-story-channel';
	}

	public function get_title() {
		return __( 'Story Channel', 'coffeebrk-core' );
	}

	public function get_group() {
		return 'coffeebrk-story';
	}

	public function get_categories() {
		return [ DynModule::TEXT_CATEGORY ];
	}

	protected function register_controls() {}

	public function render() {
		echo esc_html( (string) get_post_meta( get_the_ID(), '_cbk_story_yt_channel', true ) );
	}
}

class Coffeebrk_Story_Views_Tag extends Tag {

	public function get_name() {
		return 'coffeebrk-story-views';
	}

	public function get_title() {
		return __( 'Story Views', 'coffeebrk-core' );
	}

	public function get_group() {
		return 'coffeebrk-story';
	}

	public function get_categories() {
		return [ DynModule::TEXT_CATEGORY ];
	}

	protected function register_controls() {}

	public function render() {
		echo esc_html( (string) (int) get_post_meta( get_the_ID(), '_cbk_story_yt_views', true ) );
	}
}

class Coffeebrk_Story_Duration_Tag extends Tag {

	public function get_name() {
		return 'coffeebrk-story-duration';
	}

	public function get_title() {
		return __( 'Story Duration', 'coffeebrk-core' );
	}

	public function get_group() {
		return 'coffeebrk-story';
	}

	public function get_categories() {
		return [ DynModule::TEXT_CATEGORY ];
	}

	protected function register_controls() {}

	public function render() {
		$iso = (string) get_post_meta( get_the_ID(), '_cbk_story_yt_duration', true );
		echo esc_html( coffeebrk_format_youtube_duration( $iso ) );
	}
}

class Coffeebrk_Story_Watch_Url_Tag extends Tag {

	public function get_name() {
		return 'coffeebrk-story-watch-url';
	}

	public function get_title() {
		return __( 'Story Watch URL', 'coffeebrk-core' );
	}

	public function get_group() {
		return 'coffeebrk-story';
	}

	public function get_categories() {
		return [ DynModule::TEXT_CATEGORY, DynModule::URL_CATEGORY ];
	}

	protected function register_controls() {}

	public function render() {
		echo esc_url( (string) get_post_meta( get_the_ID(), '_cbk_story_video_url', true ) );
	}
}

class Coffeebrk_Story_Thumbnail_Tag extends Data_Tag {

	public function get_name() {
		return 'coffeebrk-story-thumbnail';
	}

	public function get_title() {
		return __( 'Story Thumbnail', 'coffeebrk-core' );
	}

	public function get_group() {
		return 'coffeebrk-story';
	}

	public function get_categories() {
		return [ DynModule::IMAGE_CATEGORY ];
	}

	protected function register_controls() {}

	public function get_value( array $options = [] ) {
		$id = get_post_thumbnail_id( get_the_ID() );
		return [
			'id'  => (int) $id,
			'url' => $id ? wp_get_attachment_image_url( $id, 'full' ) : '',
		];
	}
}
