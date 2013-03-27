WP Github Activity
==================

Wordpress plugin for showing github user activity from a post or page.

## Usage
### Shortcode
```
[github-activity user="eruonen" limit=5]
```
Where `user` is the github login name whose activity list you wish to show and `limit` is the maximum number of lines you wish to show (up to 30).

### Template tag
```php
<?php echo get_github_user_activity( 'eruonen', 5 ); ?>
```
