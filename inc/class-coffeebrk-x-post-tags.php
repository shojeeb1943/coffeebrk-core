<?php

use Elementor\Core\DynamicTags\Tag;
use Elementor\Core\DynamicTags\Data_Tag;
use Elementor\Controls_Manager;
use Elementor\Modules\DynamicTags\Module as DynModule;

if ( ! defined( 'ABSPATH' ) ) exit;

function coffeebrk_x_get_tweet_select_options() : array {
	static $options = null;
	if ( $options !== null ) {
		return $options;
	}

	$options = [
		'latest'   => __( 'Latest published tweet', 'coffeebrk-core' ),
		'featured' => __( 'Featured tweet', 'coffeebrk-core' ),
	];

	$result = coffeebrk_x_get_posts( [
		'status'   => 'published',
		'orderby'  => 'posted_at',
		'order'    => 'DESC',
		'per_page' => 100,
	] );

	foreach ( $result['posts'] as $post ) {
		$snippet = mb_substr( (string) $post['text'], 0, 40 );
		$date    = $post['posted_at'] ? date_i18n( 'Y-m-d', strtotime( (string) $post['posted_at'] ) ) : '';
		$options[ (string) $post['id'] ] = sprintf( '@%s: %s (%s)', $post['author_username'], $snippet, $date );
	}

	return $options;
}

function coffeebrk_x_resolve_selected_post( array $settings ) : ?array {
	$tweet = isset( $settings['tweet'] ) ? (string) $settings['tweet'] : 'latest';

	if ( $tweet === 'featured' ) {
		$result = coffeebrk_x_get_posts( [
			'status'   => 'published',
			'featured' => true,
			'orderby'  => 'posted_at',
			'order'    => 'DESC',
			'per_page' => 1,
		] );
		if ( ! empty( $result['posts'][0] ) ) {
			return $result['posts'][0];
		}
		$tweet = 'latest';
	}

	if ( $tweet !== 'latest' ) {
		$id = (int) $tweet;
		if ( $id > 0 ) {
			$post = coffeebrk_x_get_post( $id );
			if ( $post && $post['status'] === 'published' ) {
				return $post;
			}
		}
	}

	$result = coffeebrk_x_get_posts( [
		'status'   => 'published',
		'orderby'  => 'posted_at',
		'order'    => 'DESC',
		'per_page' => 1,
	] );

	return $result['posts'][0] ?? null;
}

class Coffeebrk_X_Post_Field_Tag extends Tag {

	public function get_name() {
		return 'coffeebrk-x-post-field';
	}

	public function get_title() {
		return __( 'X Post Field', 'coffeebrk-core' );
	}

	public function get_group() {
		return 'coffeebrk-x-social';
	}

	public function get_categories() {
		return [ DynModule::TEXT_CATEGORY, DynModule::URL_CATEGORY ];
	}

	protected function register_controls() {
		$this->add_control( 'tweet', [
			'label'   => __( 'Tweet', 'coffeebrk-core' ),
			'type'    => Controls_Manager::SELECT,
			'default' => 'latest',
			'options' => coffeebrk_x_get_tweet_select_options(),
		] );

		$this->add_control( 'field', [
			'label'   => __( 'Field', 'coffeebrk-core' ),
			'type'    => Controls_Manager::SELECT,
			'default' => 'text',
			'options' => [
				'text'                => __( 'Text', 'coffeebrk-core' ),
				'author'              => __( 'Author (@handle)', 'coffeebrk-core' ),
				'author_display_name' => __( 'Author (display name)', 'coffeebrk-core' ),
				'author_followers'    => __( 'Author Followers', 'coffeebrk-core' ),
				'permalink'           => __( 'Permalink URL', 'coffeebrk-core' ),
				'posted_at'           => __( 'Posted Date', 'coffeebrk-core' ),
				'lang'                => __( 'Language', 'coffeebrk-core' ),
				'like_count'          => __( 'Likes', 'coffeebrk-core' ),
				'retweet_count'       => __( 'Retweets', 'coffeebrk-core' ),
				'reply_count'         => __( 'Replies', 'coffeebrk-core' ),
				'view_count'          => __( 'Views', 'coffeebrk-core' ),
				'quote_count'         => __( 'Quotes', 'coffeebrk-core' ),
				'bookmark_count'      => __( 'Bookmarks', 'coffeebrk-core' ),
				'is_reply'            => __( 'Is Reply (Yes/No)', 'coffeebrk-core' ),
				'is_retweet'          => __( 'Is Retweet (Yes/No)', 'coffeebrk-core' ),
				'is_quote'            => __( 'Is Quote (Yes/No)', 'coffeebrk-core' ),
			],
		] );
	}

	public function render() {
		$settings = $this->get_settings();
		$post = coffeebrk_x_resolve_selected_post( $settings );
		if ( ! $post ) {
			return;
		}

		$field = $settings['field'] ?? 'text';

		switch ( $field ) {
			case 'permalink':
				echo esc_url( (string) $post['permalink'] );
				break;
			case 'author':
				echo esc_html( '@' . (string) $post['author_username'] );
				break;
			case 'author_display_name':
			case 'author_followers':
				$profile = coffeebrk_x_get_profile( (int) $post['profile_id'] );
				if ( ! $profile ) {
					break;
				}
				if ( $field === 'author_display_name' ) {
					echo esc_html( (string) ( $profile['display_name'] ?? '' ) );
				} else {
					echo esc_html( (string) (int) ( $profile['followers_count'] ?? 0 ) );
				}
				break;
			case 'posted_at':
				echo esc_html( (string) $post['posted_at'] );
				break;
			case 'lang':
				echo esc_html( (string) ( $post['lang'] ?? '' ) );
				break;
			case 'like_count':
			case 'retweet_count':
			case 'reply_count':
			case 'view_count':
			case 'quote_count':
			case 'bookmark_count':
				echo esc_html( (string) (int) ( $post[ $field ] ?? 0 ) );
				break;
			case 'is_reply':
			case 'is_retweet':
			case 'is_quote':
				echo esc_html( ! empty( $post[ $field ] ) ? __( 'Yes', 'coffeebrk-core' ) : __( 'No', 'coffeebrk-core' ) );
				break;
			case 'text':
			default:
				echo esc_html( (string) $post['text'] );
				break;
		}
	}
}

class Coffeebrk_X_Post_Image_Tag extends Data_Tag {

	public function get_name() {
		return 'coffeebrk-x-post-image';
	}

	public function get_title() {
		return __( 'X Post Image', 'coffeebrk-core' );
	}

	public function get_group() {
		return 'coffeebrk-x-social';
	}

	public function get_categories() {
		return [ DynModule::IMAGE_CATEGORY ];
	}

	protected function register_controls() {
		$this->add_control( 'tweet', [
			'label'   => __( 'Tweet', 'coffeebrk-core' ),
			'type'    => Controls_Manager::SELECT,
			'default' => 'latest',
			'options' => coffeebrk_x_get_tweet_select_options(),
		] );
	}

	public function get_value( array $options = [] ) {
		$settings = $this->get_settings();
		$post = coffeebrk_x_resolve_selected_post( $settings );
		if ( ! $post ) {
			return [ 'id' => 0, 'url' => '' ];
		}

		$media = json_decode( (string) ( $post['media_json'] ?? '' ), true );
		if ( ! is_array( $media ) ) {
			$media = [];
		}

		$url = '';
		if ( ! empty( $media[0]['url'] ) && is_string( $media[0]['url'] ) ) {
			$url = $media[0]['url'];
		}

		return [
			'id'  => 0,
			'url' => $url ? esc_url( $url ) : '',
		];
	}
}

class Coffeebrk_X_Post_Author_Image_Tag extends Data_Tag {

	public function get_name() {
		return 'coffeebrk-x-post-author-image';
	}

	public function get_title() {
		return __( 'X Post Author Image', 'coffeebrk-core' );
	}

	public function get_group() {
		return 'coffeebrk-x-social';
	}

	public function get_categories() {
		return [ DynModule::IMAGE_CATEGORY ];
	}

	protected function register_controls() {
		$this->add_control( 'tweet', [
			'label'   => __( 'Tweet', 'coffeebrk-core' ),
			'type'    => Controls_Manager::SELECT,
			'default' => 'latest',
			'options' => coffeebrk_x_get_tweet_select_options(),
		] );
	}

	public function get_value( array $options = [] ) {
		$settings = $this->get_settings();
		$post = coffeebrk_x_resolve_selected_post( $settings );
		if ( ! $post ) {
			return [ 'id' => 0, 'url' => '' ];
		}

		$profile = coffeebrk_x_get_profile( (int) $post['profile_id'] );
		$url = ( $profile && ! empty( $profile['avatar_url'] ) ) ? (string) $profile['avatar_url'] : '';

		return [
			'id'  => 0,
			'url' => $url ? esc_url( $url ) : '',
		];
	}
}
