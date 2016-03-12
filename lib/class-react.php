<?php

/**
 * Class WP_REST_React_Controller
 */

class React {

	/**
	 * API endpoints
	 * @var WP_REST_React_Controller
	 */
	public $api;

	/**
	 * React constructor.
	 */
	public function __construct() {
		$this->api = new WP_REST_React_Controller();

 		add_action( 'rest_api_init', array( $this->api, 'register_routes' ) );

 		if ( is_admin() ) {
 			return;
 		}

		$this->enqueue();

		add_action( 'wp_head',       array( $this,      'print_settings'  ) );
		add_action( 'wp_footer',     array( $this,      'print_selector'  ) );

 		add_filter( 'the_content',   array( $this,      'the_content'     ) );
	}

	/**
	 * Initialises the reactions.
	 *
	 * @return React Static instance of the React class.
	 */
	public static function init() {
		static $instance;

		if ( ! $instance ) {
			$instance = new React;
		}

		return $instance;
	}

	/**
	 * Print the JavaScript settings.
	 */
	public function print_settings() {
		?>
			<script type="text/javascript">
				window.wp = window.wp || {};
				window.wp.react = window.wp.react || {};
				window.wp.react.settings = {
					emoji_url: '<?php echo REACT_URL . '/static/emoji.json' ?>',
					endpoint:  '<?php echo get_rest_url( null, $this->api->namespace . '/' . $this->api->rest_base ); ?>'
				}
			</script>
		<?php
	}

	/**
	 * Enqueue relevant JS and CSS
	 */
	public function enqueue() {
		wp_enqueue_style( 'react-emoji', REACT_URL . '/static/react.css' );

		wp_enqueue_script( 'react-emoji', REACT_URL . '/static/react.js', array(), false, true );
		wp_localize_script( 'react-emoji', 'WP_API_Settings', array( 'nonce' => wp_create_nonce( 'wp_rest' ) ) );

	}

	/**
	 * Add the reaction buttons to the post content.
	 * @param  string $content The content HTML
	 * @return string The content HTML, with the react buttons attached
	 */
	public function the_content( $content ) {
		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return $content;
		}

		$reactions = get_comments( array(
			'post_id' => $post_id,
			'type'    => 'reaction',
			'status'  => 'approve' // only show approved comments
		) );

		$reactions_summary = array();
		foreach ( $reactions as $reaction ) {
			if ( ! isset( $reactions_summary[ $reaction->comment_content ] ) ) {
				$reactions_summary[ $reaction->comment_content ] = 0;
			}

			$reactions_summary[ $reaction->comment_content ]++;
		}

		$content .= '<div class="emoji-reactions">';

		$comments_open =  comments_open( $post_id ) && ( !empty( get_option( 'comment_registration' ) ) && is_user_logged_in() ) || empty( get_option( 'comment_registration' ) ); // are the comments open for this post?
		$emoji_class = ( $comments_open ) ? 'open' : 'closed';

		foreach ( $reactions_summary as $emoji_raw => $count ) {
			$emoji_array = $emoji_rendered_array = array();

			// Mmmm, don't undo the emojis already there, ok? I mean, later maybe but for now.
			$emoji_array = explode( ',', $emoji_raw );
			foreach( $emoji_array as $emoji ){
				$emoji_rendered_array[] = ( substr( $emoji, 0, 2 ) === '0x' ) ?  '&#' . substr( $emoji, 1 ) : $emoji;
			}
			$emoji_rendered = implode( '', $emoji_rendered_array );
			$content .= "<div data-emoji='$emoji_raw' data-count='$count' data-post='$post_id' class='emoji-reaction $emoji_class'><div class='emoji'>$emoji_rendered</div><div class='count'>$count</div></div>";
		}

		if ( $comments_open ){
			/* translators: This is the emoji used for the "Add new emoji reaction" button */
			$content .= "<div data-post='$post_id' class='emoji-reaction-add'><div class='emoji'>" . __( '😃', 'react' ) . '</div><div class="count">+</div></div>';
		}
		$content .= '</div>';
		return $content;
	}

	public function print_selector() {
		?>
			<div id="emoji-reaction-selector" style="display: none;">
				<div class="tabs">
					<div data-tab="0" alt="<?php echo __( 'People',   'react' ); ?>" class="emoji-reaction-tab"><?php echo __( '😀', 'react' ); ?></div>
					<div data-tab="1" alt="<?php echo __( 'Nature',   'react' ); ?>" class="emoji-reaction-tab"><?php echo __( '🌿', 'react' ); ?></div>
					<div data-tab="2" alt="<?php echo __( 'Food',     'react' ); ?>" class="emoji-reaction-tab"><?php echo __( '🍔', 'react' ); ?></div>
					<div data-tab="3" alt="<?php echo __( 'Activity', 'react' ); ?>" class="emoji-reaction-tab"><?php echo __( '⚽️', 'react' ); ?></div>
					<div data-tab="4" alt="<?php echo __( 'Places',   'react' ); ?>" class="emoji-reaction-tab"><?php echo __( '✈️', 'react' ); ?></div>
					<div data-tab="5" alt="<?php echo __( 'Objects',  'react' ); ?>" class="emoji-reaction-tab"><?php echo __( '💡', 'react' ); ?></div>
					<div data-tab="6" alt="<?php echo __( 'Symbols',  'react' ); ?>" class="emoji-reaction-tab"><?php echo __( '❤', 'react' ); ?></div>
					<div data-tab="7" alt="<?php echo __( 'Flags',    'react' ); ?>" class="emoji-reaction-tab"><?php echo __( '🇺🇸', 'react' ); ?></div>
				</div>
				<div class="container container-0"></div>
				<div class="container container-1"></div>
				<div class="container container-2"></div>
				<div class="container container-3"></div>
				<div class="container container-4"></div>
				<div class="container container-5"></div>
				<div class="container container-6"></div>
				<div class="container container-7"></div>
			</div>
		<?php
	}
}
