<?php

use Elementor\Core\DynamicTags\Tag;
use Elementor\Core\DynamicTags\Data_Tag;
use Elementor\Controls_Manager;
use Elementor\Modules\DynamicTags\Module as DynModule;

if ( ! defined( 'ABSPATH' ) ) exit;

function coffeebrk_x_get_tweet_select_options() : array {
	$options = [
		'latest'   => __( 'Latest published tweet', 'coffeebrk-core' ),
		'featured' => __( 'Featured tweet', 'coffeebrk-core' ),
	];

	$query = new WP_Query([
		'post_type'      => 'cbk_x_post',
		'post_status'    => 'publish',
		'orderby'        => 'date',
		'order'          => 'DESC',
		'posts_per_page' => 100,
	]);

	foreach ( $query->posts as $post ) {
		$snippet = mb_substr( $post->post_content, 0, 40 );
		$date    = $post->post_date ? date_i18n( 'Y-m-d', strtotime( $post->post_date ) ) : '';
		$author  = get_post_meta( $post->ID, '_cbk_x_author_username', true );
		$options[ (string) $post->ID ] = sprintf( '@%s: %s (%s)', $author, $snippet, $date );
	}

	return $options;
}

// Resolves which cbk_x_post to render. Inside an Elementor Loop Grid/Carousel
// item template, Elementor runs a real WP loop (the_post()/setup_postdata())
// over cbk_x_post — so the current post IS the tweet to show, and the
// tweet-picker control below is irrelevant there. Outside a loop (e.g. a
// standalone "show me the latest tweet" widget), fall back to the picker.
function coffeebrk_x_resolve_selected_post( array $settings ) : ?WP_Post {
	if ( get_post_type() === 'cbk_x_post' && in_the_loop() ) {
		$current = get_post();
		if ( $current ) return $current;
	}

	$tweet = isset( $settings['tweet'] ) ? (string) $settings['tweet'] : 'latest';

	if ( $tweet === 'featured' ) {
		$query = new WP_Query([
			'post_type'      => 'cbk_x_post',
			'post_status'    => 'publish',
			'orderby'        => 'date',
			'order'          => 'DESC',
			'posts_per_page' => 1,
			'meta_query'     => [ [ 'key' => '_cbk_x_is_featured', 'value' => 1, 'compare' => '=' ] ],
		]);
		if ( ! empty( $query->posts[0] ) ) {
			return $query->posts[0];
		}
		$tweet = 'latest';
	}

	if ( $tweet !== 'latest' ) {
		$id = (int) $tweet;
		if ( $id > 0 ) {
			$post = get_post( $id );
			if ( $post && $post->post_type === 'cbk_x_post' && $post->post_status === 'publish' ) {
				return $post;
			}
		}
	}

	$query = new WP_Query([
		'post_type'      => 'cbk_x_post',
		'post_status'    => 'publish',
		'orderby'        => 'date',
		'order'          => 'DESC',
		'posts_per_page' => 1,
	]);

	return $query->posts[0] ?? null;
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
			'label'   => __( 'Tweet (ignored inside a Loop Grid)', 'coffeebrk-core' ),
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
				'embed_html'          => __( 'X Embed Code (blockquote + script)', 'coffeebrk-core' ),
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
		$id = $post->ID;

		switch ( $field ) {
			case 'permalink':
				echo esc_url( (string) get_post_meta( $id, '_cbk_x_permalink', true ) );
				break;
			case 'author':
				echo esc_html( '@' . (string) get_post_meta( $id, '_cbk_x_author_username', true ) );
				break;
			case 'author_display_name':
				echo esc_html( (string) get_post_meta( $id, '_cbk_x_author_display_name', true ) );
				break;
			case 'author_followers':
				echo esc_html( (string) (int) get_post_meta( $id, '_cbk_x_author_followers', true ) );
				break;
			case 'posted_at':
				echo esc_html( (string) $post->post_date );
				break;
			case 'lang':
				echo esc_html( (string) get_post_meta( $id, '_cbk_x_lang', true ) );
				break;
			case 'like_count':
				echo esc_html( (string) (int) get_post_meta( $id, '_cbk_x_like_count', true ) );
				break;
			case 'retweet_count':
				echo esc_html( (string) (int) get_post_meta( $id, '_cbk_x_retweet_count', true ) );
				break;
			case 'reply_count':
				echo esc_html( (string) (int) get_post_meta( $id, '_cbk_x_reply_count', true ) );
				break;
			case 'view_count':
				echo esc_html( (string) (int) get_post_meta( $id, '_cbk_x_view_count', true ) );
				break;
			case 'quote_count':
				echo esc_html( (string) (int) get_post_meta( $id, '_cbk_x_quote_count', true ) );
				break;
			case 'bookmark_count':
				echo esc_html( (string) (int) get_post_meta( $id, '_cbk_x_bookmark_count', true ) );
				break;
			case 'is_reply':
				echo esc_html( get_post_meta( $id, '_cbk_x_is_reply', true ) ? __( 'Yes', 'coffeebrk-core' ) : __( 'No', 'coffeebrk-core' ) );
				break;
			case 'is_retweet':
				echo esc_html( get_post_meta( $id, '_cbk_x_is_retweet', true ) ? __( 'Yes', 'coffeebrk-core' ) : __( 'No', 'coffeebrk-core' ) );
				break;
			case 'is_quote':
				echo esc_html( get_post_meta( $id, '_cbk_x_is_quote', true ) ? __( 'Yes', 'coffeebrk-core' ) : __( 'No', 'coffeebrk-core' ) );
				break;
			case 'embed_html':
				// Raw markup on purpose (X's own widgets.js renders the real
				// card client-side) — only the URL is untrusted, so only it
				// is escaped; the rest is a fixed, hand-written template.
				$permalink = (string) get_post_meta( $id, '_cbk_x_permalink', true );
				if ( $permalink === '' ) break;
				echo '<blockquote class="twitter-tweet"><a href="' . esc_url( $permalink ) . '"></a></blockquote>'
					. '<script async src="https://platform.x.com/widgets.js" charset="utf-8"></script>';
				break;
			case 'text':
			default:
				echo esc_html( $post->post_content );
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
			'label'   => __( 'Tweet (ignored inside a Loop Grid)', 'coffeebrk-core' ),
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

		$media = json_decode( (string) get_post_meta( $post->ID, '_cbk_x_media_json', true ), true );
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
			'label'   => __( 'Tweet (ignored inside a Loop Grid)', 'coffeebrk-core' ),
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

		$url = (string) get_post_meta( $post->ID, '_cbk_x_author_avatar_url', true );

		return [
			'id'  => 0,
			'url' => $url ? esc_url( $url ) : '',
		];
	}
}
