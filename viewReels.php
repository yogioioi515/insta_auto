<?php
require 'Instagram.php';
@ini_set('output_buffering',0);
@ini_set('display_errors', 0);
@ini_set('max_execution_time',0);
@set_time_limit(0);
@ignore_user_abort(1);
error_reporting(0);
date_default_timezone_set('UTC');
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
while(true) {
    $reelsFeed=$ig->reelsFeed();
    if($reelsFeed['status']=='fail') {
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
    	die('reelsFeed: ' . $reelsFeed['message']);
        sleep(5);
    }
    $userId=$ig->getUserId();
    $log=$dataPath.$userId.'_viewReels.log';
    if(!file_exists( $log )) {
    	fopen($log,'a');
    }
    // story
    if(isset($reelsFeed['tray'])) {
        for($i = 0; $i <= count($reelsFeed['tray']); $i++) {
            if(isset($reelsFeed['tray'][$i]['reel_type'])) {
                if($reelsFeed['tray'][$i]['reel_type']=='user_reel') {
                    if(isset($reelsFeed['tray'][$i]['items'])) {
                        for($ii = 0; $ii <= count($reelsFeed['tray'][$i]['items']); $ii++) {
                            if(isset($reelsFeed['tray'][$i]['items'][$ii]['pk']) && isset($reelsFeed['tray'][$i]['items'][$ii]['taken_at'])) {
                                $userPk = $reelsFeed['tray'][$i]['items'][$ii]['user']['pk'];
                                $mediaPk = $reelsFeed['tray'][$i]['items'][$ii]['pk'];
                                $takenAt = $reelsFeed['tray'][$i]['items'][$ii]['taken_at'];
                                $log_media=$userPk.'_'.$mediaPk.'_'.$takenAt;
                        		$log_data=file_get_contents($log);
                        		$log_data=explode("\r\n", $log_data);
                        		if(!in_array($log_media, $log_data)) {
                        			// view to story reel
                        			$do_view=$ig->markStoryMediaSeen($userPk, $mediaPk, $takenAt);
                        			if($do_view==false) {
                        				file_put_contents($log_err, "(".date('Y/m/d H:i:s').") [VIEW_MEDIA] => ".$log_media." (NOT_FOUND)\n", FILE_APPEND);
                        				echo "[NOT_FOUND] " . $log_media . "\n";
                        			}
                        			if($do_view['status']=='fail') {
                        				file_put_contents($log_err, "(".date('Y/m/d H:i:s').") [VIEW_MEDIA] => ".$log_media." (ERROR) => ".json_encode($do_view)."\n", FILE_APPEND);
                        				echo "[ERROR] " . $log_media . "\n";
                        			}
                        			if($do_view['status']=='ok') {
                        				// insert to log
                        				file_put_contents($log, $log_media . "\r\n", FILE_APPEND);
                        				echo "[SUCCESS] " . $log_media . "\n";
                        			}
                        		}
                            }
                        }
                    }
                }
            }
        }
    }
    // live
    if(isset($reelsFeed['post_live'])) {
        if(isset($reelsFeed['post_live']['post_live_items'])) {
            for($i = 0; $i <= count($reelsFeed['post_live']['post_live_items']); $i++) {
                for($ii = 0; $ii <= count($reelsFeed['post_live']['post_live_items'][$i]['broadcasts']); $ii++) {
                    for($iii = 0; $iii <= count($reelsFeed['post_live']['post_live_items'][$i]['broadcasts']); $iii++) {
                        if(isset($reelsFeed['post_live']['post_live_items'][$ii]['broadcasts'][$iii]['published_time'])) {
                            $userPk = $reelsFeed['post_live']['post_live_items'][$ii]['broadcasts'][$iii]['broadcast_owner']['pk'];
                            $mediaPk = $reelsFeed['post_live']['post_live_items'][$ii]['broadcasts'][$iii]['id'];
                            $takenAt = $reelsFeed['post_live']['post_live_items'][$ii]['broadcasts'][$iii]['published_time'];
                            $log_media=$userPk.'_'.$mediaPk.'_'.$takenAt;
                    		$log_data=file_get_contents($log);
                    		$log_data=explode("\r\n", $log_data);
                    		if(!in_array($log_media, $log_data)) {
                    			// view to live reel
                    			$do_view=$ig->markStoryMediaSeen($userPk, $mediaPk, $takenAt, true);
                    			if($do_view==false) {
                    				file_put_contents($log_err, "(".date('Y/m/d H:i:s').") [VIEW_MEDIA] => ".$log_media." (NOT_FOUND) => ".json_encode($do_view)."\n", FILE_APPEND);
                    				echo "[NOT_FOUND] " . $log_media . " => ".json_encode($do_view)."\n";
                    			}
                    			if($do_view['status']=='fail') {
                    				file_put_contents($log_err, "(".date('Y/m/d H:i:s').") [VIEW_MEDIA] => ".$log_media." (ERROR) => ".json_encode($do_view)."\n", FILE_APPEND);
                    				echo "[ERROR] " . $log_media . " => ".json_encode($do_view)."\n";
                    			}
                    			if($do_view['status']=='ok') {
                    				// insert to log
                    				file_put_contents($log, $log_media . "\r\n", FILE_APPEND);
                    				echo "[SUCCESS] " . $log_media . "\n";
                    			}
                    		}
                        }
                    }
                }
            }
        }
    }
    sleep(5);
}