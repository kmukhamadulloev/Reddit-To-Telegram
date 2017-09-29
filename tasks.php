<?
// posts we made so far, initially = 0
$publishedPosts = 0;
$failedPosts = 0;
$switchAttempts = -1;
$skipPosts = array();
$maxPublishedPosts = 1;

// checking time frame
$currentHour = date("H");
$minHours = 9;
$maxHours = 23;

// checking for skipping time check
$skipTimeCheck = (is_array($argv) && in_array("-notime", $argv));

if (!$skipTimeCheck){
	if (!($currentHour >= $minHours && $currentHour <= $maxHours)){
		linklog("It's too early or too late to post. Exiting.", 'red');
		exit;
	}
}

// if ($currentHour + $maxPublishedPosts > $maxHours){
// 	// decreasing max post count if we are near maxHour
// 	$maxPublishedPosts = ($maxHours - $currentHour < 0) ? 0 : (($maxHours - $currentHour > $maxPublishedPosts) ? $maxPublishedPosts : $maxHours - $currentHour);
// }

// let's begin
while (true){
	$switchAttempts++;
	if ($switchAttempts >= 10){
		linklog("Switch Limit exceeded, exiting", 'red');
		break;
	}
	
	if ($failedPosts >= 5){
		linklog("Failed Posts Limit exceeded, exiting", 'red');
		break;
	}
	
	if ($publishedPosts >= $maxPublishedPosts){
		linklog("There are enough posts, exiting", 'gray');
		break;
	}
	
	$feed = fetchFeed();
	if ($feed !== FALSE){
		foreach($feed->data->children as $item){
			$id = $item->data->id;
			if (in_array($id, $skipPosts)){
				continue;
			}
			
			$title = str_replace("*", "* ", urldecode($item->data->title));
			if (!checkTitle($title)){
				linklog("== {$id}: {$title} ==");
				linklog("Skipped because of banned words in title");
				continue;
			}
			
			$sql = $sqc->query("SELECT id FROM reddittotg WHERE id LIKE '{$id}';");
			if ($sql->num_rows == 0){
				$skipPosts[] = $id;
				if ((isset($item->data->thumbnail) && $item->data->thumbnail == 'nsfw') ||
				   (isset($item->data->over_18) && $item->data->over_18 == true))
				{
					linklog("Post {$id} is NSFW.");
					$skipPosts[] = $id;
					continue;
				}
				
				if (isset($item->data->url) && preg_match('#imgur\.com/(a|album|gallery)/([a-zA-Z0-9]{1,20})#', $item->data->url, $m)){
					// imgur: album
					linklog("== {$id}: {$title} ==");
					linklog("{$item->data->url} | Album: {$m[2]}");
					$ia = imgur_album($m[2]);
					$title = urldecode($title);
					$postResult = createPost($id, $title, $ia);
					if ($postResult){
						$publishedPosts++;
					} else {
						$failedPosts++;
					}
					break;
				} else if (isset($item->data->url) && preg_match('#imgur\.com/([a-zA-Z0-9,]{1,20})#', $item->data->url, $m)){
					// imgur: single photo / array of photos
					linklog("== {$id}: {$title} ==");
					linklog("{$item->data->url} | {$m[1]}");
					if (strpos($m[1], ",") !== FALSE){
						$arr = explode(",", $m[1]);
						$ia = array();
						foreach ($arr as $curr_id){
							$ia[] = "https://imgur.com/{$curr_id}.jpg";
						}
					} else {
						$ia = array("https://imgur.com/{$m[1]}.jpg");
					}
					$postResult = createPost($id, $title, $ia);
					if ($postResult){
						$publishedPosts++;
					} else {
						$failedPosts++;
					}
					break;
				} else if (isset($item->data->url) && preg_match('#i\.reddituploads\.com/(.*)#', $item->data->url, $m)){
					// reddit uploads
					linklog("== {$id}: {$title} ==");
					$furl = str_replace('&amp;', '&', urldecode($item->data->url));
					linklog("{$furl}");
					$ia = array($furl);
					$postResult = createPost($id, $title, $ia);
					if ($postResult){
						$publishedPosts++;
					} else {
						$failedPosts++;
					}
					break;
				} else if (isset($item->data->url) && preg_match('#i\.redd\.it/([a-zA-Z0-9\-_]{3,})\.jpg#', $item->data->url, $m)){
					// reddit image storage
					linklog("== {$id}: {$title} ==");
					linklog("{$item->data->url}");
					$ia = array($item->data->url);
					$postResult = createPost($id, $title, $ia);
					if ($postResult){
						$publishedPosts++;
					} else {
						$failedPosts++;
					}
					break;
				} else {
					// linklog("== {$id}: {$title} ==");
					// linklog("> Unknown image host.");
				}
			} else {
				linklog("Post {$id} is already transferred to VK.");
				$skipPosts[] = $id;
			}
		}
	} else {
		linklog("Reddit Parse Error!");
		$failedPosts++;
	}

	linklog("Switching feeds... [Published: {$publishedPosts}, Failed: {$failedPosts}, Switches: {$switchAttempts}]", 'gray');
}
?>