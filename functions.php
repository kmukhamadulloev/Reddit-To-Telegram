<?
error_reporting(E_ALL);

function vardump($var){
	// Return var_dump as string
	ob_start();
	var_dump($var);
	$vars = ob_get_contents();
	ob_end_clean();
	return $vars;
}

function shortLink($link){
	if (strlen($link)<=47){
		return $link;
	} else {
		return substr($link, 0, 23) . '...' . substr($link, -23, 24);
	}
}

function tplToMsg($title, $link){
	global $msg_banners, $msg_template;
	if (isset($msg_banners) && count($msg_banners)>0){
		$banner = (mt_rand(1,3)==3) ? '' : "\n\n" . $msg_banners[array_rand($msg_banners, 1)];
	} else {
		$banner = "";
	}
	
	$title = urldecode($title);
	
	return str_replace(
		['%title%', '%link%', '%banner%'],
		[$title, $link, $banner],
		$msg_template
	);
}

function get($url, $ref = false) {
	global $useragent;
	
	$ch = curl_init();
	linklog("HTTP GET: " . $url);
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_FAILONERROR, 1); 
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_USERAGENT, $useragent);
	
	if ($ref) {
		curl_setopt($ch, CURLOPT_REFERER, $ref);
	}
	
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
	$res = curl_exec($ch);
	
	$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	$httpurl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
	
	curl_close($ch);
	
	if (defined("DEBUG") && DEBUG === 1) {
		linklog("HTTP GET: status code: {$httpcode}");
		linklog("HTTP GET: result url: {$httpurl}");
		// var_dump(preg_replace("#([^\d\s\v\w]*)#", '', substr($res, 0, 300)));
	}
	
	if ($httpcode == 302 && (stripos($httpurl, 'imgur.com/removed.') !== FALSE || stripos($httpurl, 'imgur.com/gallery.') !== FALSE)) {
		return false;
	} else {
		return $res;
	}
}

function post($url, $data = [], $timeout = null, $newConnect = false) {
	global $useragent;
	
	$ch = curl_init();
	
	if (defined("DEBUG") && DEBUG === 1) {
		linklog("HTTP POST: " . shortLink($url));
	}
	
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_FAILONERROR, 1);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_USERAGENT, $useragent);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
	curl_setopt($ch, CURLOPT_SAFE_UPLOAD, 1);
	curl_setopt($ch, CURLOPT_HEADER, 0);
	
	$timeout = ($timeout === null) ? 10 : $timeout;
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, ceil($timeout/2));
	curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
	
	if ($newConnect) {
		curl_setopt($ch, CURLOPT_FORBID_REUSE, 1);
		curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
	}
	
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
	$res = curl_exec($ch);
	curl_close($ch);
	return $res;
}

function imgur($url){
	global $useragent, $imgurclient, $imgurbase;
	$ch = curl_init(); 
	curl_setopt($ch, CURLOPT_URL, $imgurbase . $url);
	curl_setopt($ch, CURLOPT_FAILONERROR, 0); 
	curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
	curl_setopt($ch, CURLOPT_USERAGENT, $useragent);
	curl_setopt($ch, CURLOPT_HTTPHEADER, [
		"Authorization: Client-ID {$imgurclient}",
		"Accept: application/json"
	]);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
	$res = curl_exec($ch);
	curl_close($ch);
	return $res;
}

function imgur_album($album){
	$t = imgur("/gallery/album/{$album}/images");
	if ($t !== FALSE){
		$t = json_decode($t);
		if (isset($t->success) && $t->success){
			$i = 0;
			$links = [];
			foreach($t->data as $item){
				if ($i++ <= 8){
				//if ($i++ <= 2){
					array_push($links, $item->link);
				} else {
					break;
				}
			}
			
			return $links;
		} else {
			linklog("Imgur API returned error!", 'red');
			var_dump($t);
			return false;
		}
	} else {
		linklog("Imgur API error!", 'red');
		return false;
	}
}

function sendError($text){
	global $telegram_admin;
	linklog("Error: {$text}");
	$r = true;
	/*
	$r=telegramAPI('sendMessage', [
		'chat_id'=>$telegram_admin,
		'text'=>"* Reddit To VK *\n{$text}",
		// 'parse_mode' => "Markdown",
	]);
	*/
	return !!$r;
}

function tgText($text, $previewDisabled = 1){
	global $channelid;
	if (trim($text) === "") return false;
	
	$r = telegramAPI('sendMessage', [
		'chat_id' => $channelid,
		'text' => $text,
		'disable_web_page_preview' => $previewDisabled,
		// 'parse_mode' => "Markdown",
	]);
	
	return !!$r;
}

function telegramAPI($method, $data = []) {
	global $useragent, $telegram_token;
	
	if (defined("DEBUG") && DEBUG === 1) {
		linklog("TG API: {$method}");
	}
	
	usleep(300000);
	
	if (!isset($telegram_token)) return false;
	
	$res = post(
		"https://api.telegram.org/bot{$telegram_token}/{$method}",
		$data
	);

	$res = json_decode($res);
	
	if (defined("DEBUG") && DEBUG === 1) {
		linklog("TelegramAPI result:");
		var_dump($res);
	}
	
	if (isset($res->ok) && $res->ok===FALSE){
		return false;
	}
	
	return $res;
}


function tgPhoto($photoUrl, $caption=false){
	global $useragent, $telegram_token, $channelid;
	
	if (!isset($telegram_token)) return false;
	
	$url = "https://api.telegram.org/bot{$telegram_token}/sendPhoto";
	
	linklog("TG PHOTO: " . shortLink($photoUrl) . ", channel: {$channelid}");
	
	$tempName = sys_get_temp_dir() . "/vktg_" . md5(time() . mt_rand(100, 999)) . ".jpg";
	$image = get($photoUrl);
	if ($image !== FALSE) {
		if (file_put_contents($tempName, $image)) {
			if (defined("DEBUG") && DEBUG === 1) {
				linklog("Image saved to: {$tempName}, uploading to Telegram");
			}
			
			$fType = @exif_imagetype($tempName);
			if ($fType !== FALSE || $fType >= 4) {
				switch ($fType) {
					case 1:
						$fMime = "image/jpeg";
						$fName = "image.jpg";
						break;
					case 2:
						$fMime = "image/gif";
						$fName = "image.gif";
						break;
					case 3:
						$fMime = "image/png";
						$fName = "image.png";
						break;
				}
				
				if (defined("DEBUG") && DEBUG === 1) {
					linklog("Type: {$fType}, Mime: {$fMime}, Name: {$fName}");
				}
				
				$curlFile = new \CURLFile($tempName, $fMime, $fName);

				$postFields = [
					'chat_id' => $channelid,
					'photo'   => $curlFile
				];
				
				if ($caption !== FALSE){
					$postFields['caption'] = substr($caption, 0, 180);
				}
				
				$res = post($url, $postFields, null, true);
				
				@unlink($tempName);
				
				if (defined("DEBUG") && DEBUG === 1) {
					linklog("Upload result:");
					var_dump($res);
				}
				
				return $res;
			} else {
				@unlink($tempName);
				linklog("TG PHOTO: unable to determine image type");
				return false;
			}
		} else {
			@unlink($tempName);
			linklog("TG PHOTO: error saving source image");
			return false;
		}
	} else {
		@unlink($tempName);
		linklog("TG PHOTO: error downloading source image");
		return false;
	}
}

function linklog($text, $color='normal'){
	$date="[" . date("H:i") . "] ";
	switch($color){
		case "red": $clr="\033[1;31m"; break;
		case "blue": $clr="\033[1;36m"; break;
		case "green": $clr="\033[1;32m"; break;
		case "yellow": $clr="\033[1;33m"; break;
		case "gray": $clr="\033[1;30m"; break;
		default: $clr="\033[0m";
	}
	
	$line="{$clr}{$date}{$text}\033[0m";
	echo $line . "\n";
}

function getPostponedNews(){
	global $groupid;
	
	$t=vkapi("execute.getPostponedNews", [
		'owner_id'=>"-{$groupid}"
	]);
	
	return (isset($t->response)) ? $t->response : FALSE;
}

function fetchFeed(){
	global $subreddits;
	
	$subreddit = $subreddits[array_rand($subreddits, 1)];
	
	linklog("Fetching feed from /r/{$subreddit}");
	// $feed = get("https://www.reddit.com/r/{$subreddit}/top/.json?sort=top&t=month");
	$feed = get("http://www.reddit.com/r/{$subreddit}/.json");
	$feed = ($feed === FALSE) ? FALSE : json_decode($feed);
	
	return $feed;
}

// Checking for banned words: true = okay, false = skip post
function checkTitle($title){
	global $bannedWords, $bannedWordsSubreddit;
	
	$bannedWordsSubreddit = (is_array($bannedWordsSubreddit)) ? $bannedWordsSubreddit : [];
	$tmp = array_merge($bannedWords, $bannedWordsSubreddit);
	
	foreach ($tmp as $word){
		if (stripos($title, $word) !== FALSE){
			return false; // banned word
		}
	}
	
	return true;
}

function createPost($id, $title, $photoArray){
	global $sqc, $temp_dir;
	
	$result = false;

	if (is_array($photoArray) && count($photoArray) > 0){
		$msg=tplToMsg($title, "redd.it/{$id}");
		// tgText($msg);
		$result = true;
		for ($i = 0; $i < count($photoArray); $i++) {
			$try = 0;
			$res = false;
			
			while ($try < 3 && !$res) {
				$try++;
				$res = tgPhoto($photoArray[$i], $msg);
				if ($res === FALSE) {
				// 	tgText("Error uploading photo from URL: {$photoArray[$i]}");
					linklog("> Uploading photo failed, attempt {$try}.");
				}
			}
			
			if ($res === FALSE) {
				$result = false;
				break;
			}
			
			if ($i >= 5) {
				break;
			}
		}
		
		if ($result === TRUE) {
			$sql=$sqc->query("INSERT INTO reddittotg (id, result) VALUES ('{$id}', 'ok');");
			linklog("> Post published [id: {$id}].");
			$result = true;
		} else {
			$sql=$sqc->query("INSERT INTO reddittotg (id, result) VALUES ('{$id}', 'error');");
			linklog("> Error while publishing post [id: {$id}].");
			$result = false;
		}
	} else {
		$sql=$sqc->query("INSERT INTO reddittotg (id, result) VALUES ('{$id}', 'error');");
		sendError("Can't get post images [id: {$id}]!");
		$result = false;
	}
	
	return $result;
}
?>