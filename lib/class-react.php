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
 		add_filter( 'wp_list_comments_args', array( $this, 'filter_comments' ), 10, 1 );
 		add_filter( 'get_comments_number', array( $this, 'count_comments' ), 10, 2 );

 		// can't use 'parse_comment_query' because it changes all comments queries oh
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
			$content .= "<div data-emoji='$emoji_raw' data-emoji-rendered='$emoji_rendered' data-count='$count' data-post='$post_id' class='emoji-reaction $emoji_class'><div class='emoji'>$emoji_rendered</div><div class='count'>$count</div></div>";
		}

		if ( $comments_open ){
			/* translators: This is the emoji used for the "Add new emoji reaction" button */
			$content .= "<div data-post='$post_id' class='emoji-reaction-add'><div class='emoji'>" . __( '😃', 'react' ) . '</div><div class="count">+</div></div>';
		}
		$content .= '</div>';
		return $content;
	}

	public function filter_comments( $r ){

		//I think this is all we can do? It's all or just one:/src/wp-includes/comment-template.php#L1865
		$r['type'] = 'comment'; // @todo core -- make this an array or something, needs a filter in the comment-template.php

		return $r;

	}

	// this returns the approved count of comment_type 'comment' -- this needs to be thought through and filtered whatever but for now we're limited by 'wp_list_comment_args' only taking a string
	// ooh but I can use get_comment_types so why not, this is probably not the right way but I made soooo 
	public function count_comments( $count, $post_id ){
		$comment_types = $this->get_comment_types( $post_id );
		return $comment_types['comment']['approve'];
	}

	// I don't think there's something like this in core? Or I can't find it, hmmm, that could easily be the case.
	// This returns a list of comment types in a particular post. We need it to filter wp_list_comments because
	// there are some we don't want shown. It would be nice to have type__not_in in wp_list_comments but this also
	// might be a nice thing too. Let's return the count of each type too for extensibility purposes like auto-moderation.
	// Also I am not certain on best caching practices so this isn't. 
	// 
	// Also also this should return approved and unapproved and all that, I haven't really thought it through
	// Oh fine, let's add in comment statuses too fine fine
	// 
	// this is kind of like get_comment_count but with types
	// 
	// this is what I'd use for moderation maybe (comment_type = 'moderation_check') - note to self: moderation check is like report this post, it'd replace it
	public function get_comment_types( $post_id = 0 ){
		
		global $wpdb;

		$post_id = ( $post_id === 0 ) ? get_the_ID() : (int)$post_id;
		if( get_post_status( $post_id ) === false ){
			return new WP_Error( 'rest_no_post', __( 'Sorry, that post does not exist.', 'react' ) ); 
		}

		// be nice to have an index on comment_post_id, comment_type, comment_approved maybe
		$comment_types_array = $wpdb->get_results(
			$wpdb->prepare(
				"
				SELECT comment_type as type, count(*) as count, comment_approved as approved 
				FROM $wpdb->comments 
				WHERE comment_post_id = %d
				GROUP BY comment_type, comment_approved
				",
				$post_id
				)
			);

		if( !is_array( $comment_types_array ) ){
			return false;
		}

		// @todo core hmmm get_comment_statuses needs a filter to add in additional statuses
		// no wait, get_comment_count uses different words for statuses src/wp-includes/comment.php#L338
		// let's use 'hold', 'approve' -- not sure on the other ones, so they go to default

		foreach( $comment_types_array as $comment_type_data ){
			$comment_type_data->type = ( empty( $comment_type_data->type ) ) ? 'comment' : $comment_type_data->type;
			switch( $comment_type_data->approved ){
				case 0:
					$comment_status = 'hold';
					break;
				case 1:
					$comment_status = 'approve';
					break;
				default:
					$comment_status = $comment_type_data->approved;
			}

			$comment_types[$comment_type_data->type][$comment_status] = $comment_type_data->count;
		}

		return $comment_types;
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
