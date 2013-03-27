<?php
/*
Plugin Name:    WP Github Activity
Plugin URI:     https://github.com/Eruonen/wp-github-activity
Description:    This plugin allows Wordpress to show a GitHub user's public activity feed
Version:        1.0
Author:         Nathaniel Williams
Author URI:     http://coios.net/
License:        MIT
*/

class WP_GitHub_Activity {

	private $debug;
	protected $github_api_url = 'https://api.github.com/users/%s/events/public';

	public function __construct( $debug = false ) {
		$this->debug = $debug;
		add_shortcode( 'github_activity', array( $this, 'shortcode' ) );
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
			return __( 'User not found.', 'github-activity' );

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
					__( '%1$s created repository %2$s', 'github-activity' ),
					$this->get_user_link( $activity['actor']['login'] ),
					$this->get_repo_link( $activity['repo'] )
				);
				break;

			case 'PushEvent':
				$activity_string = sprintf(
					__('%1$s pushed to %2$s at %3$s', 'github-activity'),
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
					__( '%1$s started following %2$s', 'github-activity' ),
					$this->get_user_link( $activity['actor']['login'] ),
					$this->get_user_link( $activity['payload']['target']['login'] ) 
				);
				break;
			
			default:
				if($this->debug) {
					$activity_string = __( 'Unrecognized activity type', 'github-activity' ) . ': ' . $activity['type'];
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

	protected function get_branch_link( $payload, $repo ) {
		$branch = str_replace( 'refs/heads/', '', $payload['ref'] );
		return '<a class="branch" href="https://github.com/' . $repo['name'] . '/tree/' . $branch . '">' . $branch . '</a>';
	}

	protected function get_commit_message( $commit, $repo ) {
		return '<span class="commit_message"><a class="sha" href="https://github.com/' . $repo['name'] . '/commit/' . $commit['sha'] . '">' . substr($commit['sha'], 0, 7) . '</a> ' . $commit['message'] . '</span>';
	}
}

$github_activity = new WP_GitHub_Activity();

// template tag for developers
function get_github_user_activity( $user = 'eruonen', $limit = 5, $cache = 300 ) {
	$github_activity->get_github_activity( $user, $limit, $cache );
}