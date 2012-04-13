<?php
/*
Plugin Name: Twitterz
Description: Fading Twitter feed display.
Version: 1.0
Author: Loud Dog
Author URI: http://www.louddog.com
*/

new Twitterz;
class Twitterz {
	var $cache_time = 1800; // seconds
	
	function __construct() {
		add_shortcode('twitterz', array($this, 'shortcode'));
	}
	
	// return cached array of tweets
	function tweets($username) {
		if (true || get_transient('twitterz_cache_tweets_'.$username) === false) {
			if ($tweets = $this->get_tweets($username)) {
				update_option('twitterz_tweets_'.$username, $tweets);
				set_transient('twitterz_cache_tweets_'.$username, true, $this->cache_time);
			} else echo "<p>no tweets</p>";
		} else echo "<p>cached</p>";

		return get_option('twitterz_tweets_'.$username, array());
	}

	// fetch tweets from user
	function get_tweets($username) {
		$tweets = array();
		
		$response = wp_remote_get("http://api.twitter.com/1/statuses/user_timeline.json?screen_name=".$username);
		
		if (is_wp_error($response) || $response['response']['code'] != '200') return $tweets;
		
		$data = json_decode($response['body']);
		foreach ($data as $tweet) {
			$tweets['id-'.$tweet->id_str] = (object) array(
				'date' => strtotime($tweet->created_at),
				'text' => $this->linkify($tweet->text),
			);
		}

		return $tweets;
	}
	
	// link up urls, mentions and hashtags
	function linkify($text) {
		$replace = array(
			'/(https?:\/\/[^ ]+)/' => '<a href="$1" target="_blank">$1</a>',
			'/@([^ ]+)/' => '<a href="http://twitter.com/$1" target="_blank">@$1</a>',
			'/#([^ ]+)/' => '<a href="http://twitter.com/search/%23$1" target="_blank">#$1</a>',
		);
		
		return preg_replace(array_keys($replace), array_values($replace), $text);
	}
	
	function shortcode($atts) {
		extract(shortcode_atts(array(
			'username' => false,
			'count' => 5,
			'no_tweets' => false,
		), $atts));
		
		if (!$username) return;
		ob_start();
		
		if ($tweets = $this->tweets($username)) { ?>
			<ul class="tweets">
				<?php foreach (array_slice($tweets, 0, $count) as $tweet): ?>
					<li class="tweet">
						<a href="http://twitter.com/<?=$username?>" target="_blank" class="at">@<?=$username?></a>:
						<?=$tweet->text?>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php } else if ($no_tweets) { ?>
			<p><?=$no_tweets?></p>
		<?php }
		
		
		return ob_get_clean();	
	}
}