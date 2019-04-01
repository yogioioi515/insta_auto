<?php
require 'Instagram.php';
@ini_set('output_buffering',0);
@ini_set('display_errors', 0);
@ini_set('max_execution_time',0);
@set_time_limit(0);
@ignore_user_abort(1);
error_reporting(0);
date_default_timezone_set('Asia/Jakarta');
header("Content-Type: text/plain");
$dataPath='data/';
$log_err=$dataPath.'igerror.log';
////////////
$username='ygihrdi90';
$password='yogiediot1';
// login to ig
$ig = new Instagram($username, $password);
$login=$ig->login();
if($login['status']=='fail') {
	$fileArray = array(
		"cache/".$username."-cookies.log",
		"cache/".$username."-token.log",
		"cache/".$username."-userId.log"
	);
	foreach ($fileArray as $value) {
		if (file_exists($value)) {
			unlink($value);
		}
	}
	die('login: ' . $login['message']);
}
$timelineFeed=$ig->timelineFeed();
if($timelineFeed['status']=='fail') {
	$fileArray = array(
		"cache/".$username."-cookies.log",
		"cache/".$username."-token.log",
		"cache/".$username."-userId.log"
	);
	foreach ($fileArray as $value) {
		if (file_exists($value)) {
			unlink($value);
		}
	}
	die('timelineFeed: ' . $timelineFeed['message']);
}
$userId=$ig->getUserId();
$log=$dataPath.$userId.'_likeTimeline.log';
if(!file_exists( $log )) {
	fopen($log,'a');
}
for($i = 0; $i <= count($timelineFeed['items']); $i++) {
	if(isset($timelineFeed['items'][$i]['id'])&&empty($timelineFeed['items'][$i]['dr_ad_type'])&&$timelineFeed['items'][$i]['has_liked']==false) {
		$media_id=$timelineFeed['items'][$i]['id'];
		$media_id=explode('_', $media_id)[0];
		$log_data=file_get_contents($log);
		$log_data=explode("\r\n", $log_data);
		if(!in_array($media_id, $log_data)) {
			// like to media_id
			$do_like=$ig->like($media_id);
			if($do_like==false) {
				file_put_contents($log_err, "(".date('Y/m/d H:i:s').") [LIKE_MEDIA] => ".$media_id." (NOT_FOUND)\n", FILE_APPEND);
				echo "[NOT_FOUND] " . $media_id . "\n";
			}
			if($do_like['status']=='fail') {
				file_put_contents($log_err, "(".date('Y/m/d H:i:s').") [LIKE_MEDIA] => ".$media_id." (ERROR) => ".json_encode($do_like)."\n", FILE_APPEND);
				echo "[ERROR] " . $media_id . "\n";
			}
			if($do_like['status']=='ok') {
				// insert to log
				file_put_contents($log, $media_id . "\r\n", FILE_APPEND);
				echo "[SUCCESS] " . $media_id . "\n";
			}
		}
	}
}