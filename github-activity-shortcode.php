<?php
/*
Plugin Name:    GitHub User Activity Shortcode
Plugin URI:     https://github.com/Eruonen/github-activity-shortcode
Description:    This plugin allows Wordpress to show a GitHub user's activity feed from within a post or page using a shortcode.
Version:        1.0
Author:         Nathaniel Williams
Author URI:     http://coios.net/
License:        MIT
*/

function get_github_user_activity( $attributes ) {
	extract( shortcode_atts( array(
		'user' => 'eruonen',
		'limit' => 5
	), $attributes ) );
	$github_url = sprintf('https://api.github.com/users/%s/events/public', $user);
	$ch = curl_init( $github_url );
	$curl_opts = array(
		CURLOPT_RETURNTRANSFER => true,
	);
	curl_setopt_array($ch, $curl_opts);
	$result = json_decode(curl_exec($ch), true);
	curl_close($ch);
	if (isset($result['message']) && $result['message'] === 'Not Found')
		return __('User not found.', 'github-activity');
	
	$html = '<ul class="github_activities">';
	for ($i=0; $i < count($result) && $i < $limit; $i++) { 
		$html .= '<li class="activity">' . build_activity_string($result[$i]) . '</li>';
	}
	$html .= '</ul>';

	unset($result);
	return $html;
}

function build_activity_string( $activity ) {
	switch ($activity["type"]) {
		case 'CreateEvent':
			$activity_string = sprintf(
				__('%1$s created repository %2$s', 'github-activity'),
				get_user_link($activity['actor']['login']),
				get_repo_link($activity['repo'])
			);
			break;

		case 'PushEvent':
			$activity_string = sprintf(
				__('%1$s pushed to %2$s at %3$s', 'github-activity'),
				get_user_link( $activity['actor']['login'] ),
				get_branch_link( $activity['payload'], $activity['repo'] ),
				get_repo_link( $activity['repo'] )
			);
			break;

		case 'FollowEvent':
			$activity_string = sprintf(
				__('%1$s started following %2$s', 'github-activity'),
				get_user_link($activity['actor']['login']),
				get_user_link($activity['payload']['target']['login']) 
			);
			break;
		
		default:
			$activity = $activity["type"];
			break;
	}
	return $activity_string;
}

function get_user_link( $login ) {
	return '<a class="user" href="https://github.com/' . $login . '">'.$login . '</a>';
}

function get_repo_link( $repo ) {
	return '<a class="repo" href="https://github.com/' . $repo['name'] . '">' . $repo['name'] . '</a>';
}

function get_branch_link( $payload, $repo ) {
	$branch = str_replace( 'refs/heads/', '', $payload['ref'] );
	return '<a class="branch" href="https://github.com/' . $repo['name'] . '/tree/' . $branch . '">' . $branch . '</a>';
}

add_shortcode( 'github_activity', 'get_github_user_activity' );