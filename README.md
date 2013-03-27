WP Github Activity
==================

Wordpress plugin for showing github user activity from a post or page.

## Usage
### Shortcode
```
[github-activity user="eruonen" limit=5 cache=300]
```
- `user` The github login name whose activity list you wish to show. Default value: `eruonen`
- `limit` The maximum number of lines you wish to show (up to 30). Default value: `5`
- `cache` The number of seconds the api data should be cached (0 for no cache). Default value: `300` (5 minutes)

### Template tag
```php
get_github_user_activity( $user = 'eruonen', $limit = 5, $cache_timeout = 300 )
```
- `$user` The github login name whose activity list you wish to show
- `$limit` The maximum number of lines you wish to show (up to 30)
- `$cache_timeout` The number of seconds the api data should be cached (0 for no cache)

Examples:
```php
// shows the five most recent github user activities for user eruonen
echo get_github_user_activity( 'eruonen');
// shows only the latest github user activity for user eruonen
echo get_github_user_activity( 'eruonen', 1 );
// shows only the latest github user activity for user eruonen without caching the results
echo get_github_user_activity( 'eruonen', 1, 0 );
```
