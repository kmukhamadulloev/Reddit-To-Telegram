<?
if (PHP_SAPI!='cli') die('Only CLI Access allowed!');
require(__DIR__ . '/config.php');
require(__DIR__ . '/functions.php');

$sqc=new mysqli($mysql_host,$mysql_user,$mysql_password,$mysql_database,$mysql_port);
if ($sqc->connect_error) die("Can't conenct to DB!");
$sqc->set_charset("utf8");

linklog("> Script started", 'green');

$multiList = glob(__DIR__ . '/multi/*.php');
foreach($multiList as $configPath){
	linklog("Preparing multiconfig: {$configPath}", 'yellow');
	unset($channelid, $subreddits, $msg_template, $configEnabled);
	@include($configPath);
	if (isset($channelid) && isset($subreddits) && isset($msg_template)){
		if (!isset($configEnabled) || (isset($configEnabled) && $configEnabled === TRUE)) {
			linklog("Starting multiconfig: {$configPath}", 'yellow');
			require(__DIR__ . '/tasks.php');
		} else {
			linklog("Disabled multiconfig: {$configPath}", 'red');
		}
	} else {
		linklog("Malformed multiconfig: {$configPath}", 'red');
	}
}

linklog("> Script completed", 'green');
?>