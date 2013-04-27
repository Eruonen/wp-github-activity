<?php
/*
Plugin Name:    WP GitHub Activity
Plugin URI:     https://github.com/Eruonen/wp-github-activity
Description:    This plugin allows Wordpress to show a GitHub user's public activity feed
Version:        1.0
Author:         Nathaniel Williams
Author URI:     http://coios.net/
License:        MIT
*/

// load language files
function wp_github_activity_load_languages() {
  load_plugin_textdomain( 'wp-github-activity', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action('init', 'wp_github_activity_load_languages');

class WP_GitHub_Activity {

	private $debug;
	protected $github_api_url = 'https://api.github.com/users/%s/events/public';

	public function __construct( $debug = false ) {
		$this->debug = $debug;
		add_shortcode( 'github_activity', array( $this, 'shortcode' ) );
		add_action( 'widgets_init', create_function( '', 'register_widget( "wp_github_activity_widget" );' ) );
	}

	public function shortcode( $attributes ) {
		// extract the attributes into variables
		extract( shortcode_atts( array(
		'user'  => 'eruonen',
		'limit' => 5,
		'cache' => 300
		), $attributes ) );

		return $this->get_github_activity( $user, $limit, $cache );
	}
	
	public function get_github_activity( $user, $limit, $cache_timeout = 300 ) {
		$cache_timeout = intval($cache_timeout);
		// get cache
		$transient = get_transient( 'wp-github-activity' );
		// check whether the cache exists and the user is still the same
		if ( $cache_timeout !== 0 && $transient !== false && $transient['user'] === $user ) {
	    	$result = $transient['api_data'];
		} else {
			// initialize curl and create the API link
			$ch = curl_init( sprintf( $this->github_api_url, $user ) );
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
			curl_setopt( $ch, CURLOPT_USERAGENT, get_option('admin_email') );


			// execute the curl and decode the json result into an associative array
			$result = json_decode( curl_exec($ch), true );
			// close curl
			curl_close( $ch );

			// set cache
			if($cache_timeout !== 0)
				set_transient('wp-github-activity', array( 'user' => $user, 'api_data' => $result ), $cache_timeout);
		}
		// check whether the user exists or not
		if ( isset( $result['message'] ) && $result['message'] === 'Not Found' )
			return __( 'User not found.', 'wp-github-activity' );

		// generate html and return it
		return $this->generate_html( $result, $limit );
	}

	protected function generate_html( $api_data, $limit ) {
		$html = '<ul class="github_activities">';
		// loop through the data and stop when either the end or limit is reached
		for ( $i = 0; $i < count( $api_data ) && $i < $limit; $i++ ) { 
			$html .= '<li class="activity">' . $this->build_activity_string( $api_data[$i] ) . '</li>';
		}
		$html .= '</ul>';
		return $html;
	}

	protected function build_activity_string( $activity ) {
		// generate a string based on the type of activity
		switch ( $activity['type'] ) {
			case 'CreateEvent':
				$activity_string = sprintf(
					__( '%1$s created repository %2$s', 'wp-github-activity' ),
					$this->get_user_link( $activity['actor']['login'] ),
					$this->get_repo_link( $activity['repo'] )
				);
				break;

			case 'PushEvent':
				$activity_string = sprintf(
					__('%1$s pushed to %2$s at %3$s', 'wp-github-activity'),
					$this->get_user_link( $activity['actor']['login'] ),
					$this->get_branch_link( $activity['payload'], $activity['repo'] ),
					$this->get_repo_link( $activity['repo'] )
				);
				for ( $i = count( $activity['payload']['commits'] ) - 1; $i >= 0; $i-- ) { 
					$activity_string .= '<br>' . $this->get_commit_message( $activity['payload']['commits'][$i], $activity['repo'] );
				}
				break;

			case 'FollowEvent':
				$activity_string = sprintf(
					__( '%1$s started following %2$s', 'wp-github-activity' ),
					$this->get_user_link( $activity['actor']['login'] ),
					$this->get_user_link( $activity['payload']['target']['login'] ) 
				);
				break;

			case 'WatchEvent':
				$activity_string = sprintf(
					__( '%1$s starred %2$s', 'wp-github-activity' ),
					$this->get_user_link( $activity['actor']['login'] ),
					$this->get_repo_link( $activity['repo'] )
				);
				break;

			case 'ForkEvent':
				$activity_string = sprintf(
					__( '%1$s forked %2$s to %3$s', 'wp-github-activity' ),
					$this->get_user_link( $activity['actor']['login'] ),
					$this->get_repo_link( $activity['repo'] ),
					$this->get_forked_repo_link( $activity['payload']['forkee'] )
				);
				break;

			case 'IssueCommentEvent':
				$activity_string = sprintf(
					__( '%1$s commented on %2$s', 'wp-github-activity' ),
					$this->get_user_link( $activity['actor']['login'] ),
					$this->get_issue_comment_link( $activity['payload']['issue'], $activity['repo'] )
				);
				break;
			
			default:
				if($this->debug) {
					$activity_string = __( 'Unrecognized activity type', 'wp-github-activity' ) . ': ' . $activity['type'];
				} else {
					$activity_string = '';
				}
				break;
		}
		return $activity_string;
	}

	protected function get_user_link( $login ) {
		return '<a class="user" href="https://github.com/' . $login . '">'.$login . '</a>';
	}

	protected function get_repo_link( $repo ) {
		return '<a class="repo" href="https://github.com/' . $repo['name'] . '">' . $repo['name'] . '</a>';
	}

	protected function get_forked_repo_link( $forkee ) {
		return '<a class="repo" href="' . $forkee['html_url'] . '">' . $forkee['full_name'] . '</a>';
	}

	protected function get_issue_comment_link( $issue, $repo ) {
		return '<a class="issue_comment" href="' . $issue['html_url'] . '">' . $repo['name'] . '#' . $issue['number'] . '</a>';
	}

	protected function get_branch_link( $payload, $repo ) {
		$branch = str_replace( 'refs/heads/', '', $payload['ref'] );
		return '<a class="branch" href="https://github.com/' . $repo['name'] . '/tree/' . $branch . '">' . $branch . '</a>';
	}

	protected function get_commit_message( $commit, $repo ) {
		return '<span class="commit_message"><a class="sha" href="https://github.com/' . $repo['name'] . '/commit/' . $commit['sha'] . '">' . substr($commit['sha'], 0, 7) . '</a> ' . $commit['message'] . '</span>';
	}
}

class WP_Github_Activity_Widget extends WP_Widget {
	private $wp_github_activity;

	public function __construct() {
		$this->wp_github_activity = new WP_GitHub_Activity();
		parent::__construct(
	 		'wp_github_activity_widget',
			'Github Activity Widget',
			array(
				'description' => __( 'A widget for showing a user\'s public GitHub activity feed', 'wp-github-activity' ), 
				'classname'   => 'wp_github_activity_widget',
			)
		);
	}

	public function widget( $args, $instance ) {
		extract( $args );
		$title = apply_filters( 'widget_title', $instance['title'] );
		$user = $instance['user'];
		$limit = $instance['limit'];
		$cache = $instance['cache'];
		echo $before_widget;
		if ( ! empty( $title ) )
			echo $before_title . $title . $after_title;
		echo $this->wp_github_activity->get_github_activity( $user, $limit, $cache );
		echo $after_widget;
	}

	public function update( $new_instance, $old_instance ) {
		$instance = array();
		$instance['title']  =  strip_tags( $new_instance['title'] );
		$instance['user']   =  strip_tags( $new_instance['user'] );
		$instance['limit']  =  strip_tags( $new_instance['limit'] );
		$instance['cache']  =  strip_tags( $new_instance['cache'] );
		return $instance;
	}

	public function form( $instance ) {
		$title  =  isset( $instance['title'] )  ?  $instance['title']  :  __( 'GitHub activity feed', 'wp-github-activity' );
		$user   =  isset( $instance['user'] )   ?  $instance['user']   :  '';
		$limit  =  isset( $instance['limit'] )  ?  $instance['limit']  :  5;
		$cache  =  isset( $instance['cache'] )  ?  $instance['cache']  :  300;
		?>
		<p>
			<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title', 'wp-github-activity' ); ?>:</label> 
			<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" />
		</p>
		<p>
			<label for="<?php echo $this->get_field_id( 'user' ); ?>"><?php _e( 'User', 'wp-github-activity' ); ?>:</label> 
			<input class="widefat" id="<?php echo $this->get_field_id( 'user' ); ?>" name="<?php echo $this->get_field_name( 'user' ); ?>" type="text" value="<?php echo esc_attr( $user ); ?>" />
		</p>
		<p>
			<label for="<?php echo $this->get_field_id( 'limit' ); ?>"><?php _e( 'Limit', 'wp-github-activity' ); ?>:</label> 
			<input class="widefat" id="<?php echo $this->get_field_id( 'limit' ); ?>" name="<?php echo $this->get_field_name( 'limit' ); ?>" type="text" value="<?php echo esc_attr( $limit ); ?>" />
		</p>
		<p>
			<label for="<?php echo $this->get_field_id( 'cache' ); ?>"><?php _e( 'Cache (0 to disable cache)', 'wp-github-activity' ); ?>:</label> 
			<input class="widefat" id="<?php echo $this->get_field_id( 'cache' ); ?>" name="<?php echo $this->get_field_name( 'cache' ); ?>" type="text" value="<?php echo esc_attr( $cache ); ?>" />
		</p>
		<?php 
	}
}

$github_activity = new WP_GitHub_Activity(true);

// template tag for developers
function get_github_user_activity( $user = 'eruonen', $limit = 5, $cache = 300 ) {
	$github_activity->get_github_activity( $user, $limit, $cache );
}