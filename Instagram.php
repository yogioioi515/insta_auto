<?php
class Constants
{
    const API_URL = 'https://i.instagram.com/api/v1/';
    const USER_AGENT = 'Instagram 76.0.0.15.395 Android (18/4.3; 320dpi; 720x1280; Xiaomi; HM 1SW; armani; qcom; en_US)';
    const IG_SIG_KEY = '19ce5f445dbfd9d29c59dc2a78c616a7fc090a8e018b9267bc4240a30244c53b';
    const SIG_KEY_VERSION = '4';
}

class Instagram
{
  protected $username;
  protected $password;
  protected $debug;

  protected $uuid;
  protected $device_id;
  protected $username_id;
  protected $token;
  protected $isLoggedIn = false;
  protected $rank_token;
  public $IGDataPath;

  public function __construct($username, $password)
  {
      $this->username = $username;
      $this->password = $password;
      $this->debug = false;

      $this->uuid = $this->generateUUID(true);
      $this->device_id = $this->generateDeviceId(md5($username.$password));

      if (!file_exists( 'cache' )) {
        mkdir('cache/' , 0777);
      }

      $this->IGDataPath = __DIR__ . DIRECTORY_SEPARATOR . 'cache/';

      if ((file_exists($this->IGDataPath."$this->username-cookies.log")) && (file_exists($this->IGDataPath."$this->username-userId.log"))
    && (file_exists($this->IGDataPath."$this->username-token.log"))) {
          $this->isLoggedIn = true;
          $this->username_id = trim(file_get_contents($this->IGDataPath."$username-userId.log"));
          $this->rank_token = $this->username_id.'_'.$this->uuid;
          $this->token = trim(file_get_contents($this->IGDataPath."$username-token.log"));
      }
  }

  public function login()
  {
      if (!$this->isLoggedIn) {
          $fetch = $this->request('si/fetch_headers/?challenge_type=signup&guid='.$this->generateUUID(false), null, true);
          preg_match('#Set-Cookie: csrftoken=([^;]+)#', $fetch[0], $token);

          $data = [
          'device_id'           => $this->device_id,
          'guid'                => $this->uuid,
          'phone_id'            => $this->generateUUID(true),
          'username'            => $this->username,
          'password'            => $this->password,
          'login_attempt_count' => '0',
           ];
          $login = $this->request('accounts/login/', $this->generateSignature(json_encode($data)), true);
          if($login[1]['status']!=='fail') {
                    $this->isLoggedIn = true;
                    $this->username_id = $login[1]['logged_in_user']['pk'];
                    file_put_contents($this->IGDataPath.$this->username.'-userId.log', $this->username_id);
                    $this->rank_token = $this->username_id.'_'.$this->uuid;
                    preg_match('#Set-Cookie: csrftoken=([^;]+)#', $login[0], $match);
                    $this->token = $match[1];
                    file_put_contents($this->IGDataPath.$this->username.'-token.log', $this->token);
          }
          return $login[1];
      }
  }
  
  public function timelineFeed()
  {
        return $this->request('feed/timeline/')[1];
  }
  
  public function markStoryMediaSeen($userPk, $mediaPk, $takenAt, $is_vod=false)
  {
        $reels_real = [];
        $maxSeenAt = time();
        if(empty($_SESSION['viewReel_seenAt'])) $_SESSION['viewReel_seenAt']=$maxSeenAt;
        if(empty($_SESSION['viewReel_countSeen'])) $_SESSION['viewReel_countSeen']=1;
        $_SESSION['viewReel_seenAt'] = $maxSeenAt - (3 * $_SESSION['viewReel_countSeen']);
        $reelId = $mediaPk.'_'.$userPk;
        if ($_SESSION['viewReel_seenAt'] < $takenAt) {
            $_SESSION['viewReel_seenAt'] = $takenAt + 2;
        }
        if ($_SESSION['viewReel_seenAt'] > $maxSeenAt) {
            $_SESSION['viewReel_seenAt'] = $maxSeenAt;
        }
        $reels_real[$reelId] = $takenAt.'_'.$_SESSION['viewReel_seenAt'];
        $data = json_encode([
            '_uuid'      => $this->uuid,
            '_uid'       => $this->username_id,
            '_csrftoken' => $this->token,
            'reels'      => ($is_vod==false ? $reels_real : []),
            'live_vods'  => ($is_vod==true ? $reels_real : [])
        ]);
        $_SESSION['viewReel_seenAt']=$_SESSION['viewReel_seenAt']+rand(1, 3);
        $_SESSION['viewReel_countSeen']=$_SESSION['viewReel_countSeen']+1;
        $params = '?' . ($is_vod==false ? 'reel=1' : 'reel=0') . '&' . ($is_vod==true ? 'live_vod=1' : 'live_vod=0');
        return $this->request("media/seen/" . $params, $this->generateSignature($data))[1];
  }
  
  public function reelsFeed()
  {
        return $this->request('feed/reels_tray/')[1];
  }
  
  public function getTimeline($maxid = null)
  {
      $timeline = $this->request(
          "feed/timeline/?rank_token=$this->rank_token&ranked_content=true"
          .(!is_null($maxid) ? "&max_id=".$maxid : '')
      )[1];
      return $timeline;
  }

  public function getProfileData()
  {
      return $this->request('accounts/current_user/?edit=true', $this->generateSignature($data))[1];
  }
  
  public function getRecentActivity()
  {
      $activity = $this->request('news/inbox/?')[1];
      return $activity;
  }

  public function getUserId()
  {
      return $this->username_id;
  }

  public function searchUsers($query)
  {
      $query = $this->request('users/search/?ig_sig_key_version='.Constants::SIG_KEY_VERSION."&is_typeahead=true&query=$query&rank_token=$this->rank_token")[1];

      return $query;
  }
  
  public function getUserFeed($usernameId)
  {
      $userFeed = $this->request("feed/user/$usernameId/?rank_token=$this->rank_token&ranked_content=true&")[1];

      return $userFeed;
  }

  public function mediaInfo($mediaId)
  {
      $data = json_encode([
        '_uuid'      => $this->uuid,
        '_uid'       => $this->username_id,
        '_csrftoken' => $this->token,
        'media_id'   => $mediaId,
    ]);

      return $this->request("media/$mediaId/info/", $this->generateSignature($data))[1];
  }
  
  public function getMediaComments($mediaId)
  {
      return $this->request("media/$mediaId/comments/?")[1];
  }

  public function getUsernameInfo($usernameId)
  {
      return $this->request("users/$usernameId/info/")[1];
  }

  public function like($mediaId)
  {
      $data = json_encode([
        '_uuid'      => $this->uuid,
        '_uid'       => $this->username_id,
        '_csrftoken' => $this->token,
        'media_id'   => $mediaId,
    ]);

      return $this->request("media/$mediaId/like/", $this->generateSignature($data))[1];
  }

  public function unlike($mediaId)
  {
      $data = json_encode([
        '_uuid'      => $this->uuid,
        '_uid'       => $this->username_id,
        '_csrftoken' => $this->token,
        'media_id'   => $mediaId,
    ]);

      return $this->request("media/$mediaId/unlike/", $this->generateSignature($data))[1];
  }
  
  public function comment($mediaId, $commentText)
  {
      $data = json_encode([
        '_uuid'          => $this->uuid,
        '_uid'           => $this->username_id,
        '_csrftoken'     => $this->token,
        'comment_text'   => $commentText,
    ]);

      return $this->request("media/$mediaId/comment/", $this->generateSignature($data))[1];
  }
      
  public function deleteComment($mediaId, $commentId)
  {
      $data = json_encode([
        '_uuid'          => $this->uuid,
        '_uid'           => $this->username_id,
        '_csrftoken'     => $this->token
    ]);

      return $this->request("media/$mediaId/comment/$commentId/delete/", $this->generateSignature($data))[1];
  }

  public function getPendingInbox($maxid = null)
  {
      if (is_null($maxid)) {
          $endpoint = "direct_v2/pending_inbox/?rank_token=$this->rank_token&ranked_content=true&";
      } else {
          $endpoint = "direct_v2/pending_inbox/?max_id=".$maxid."&rank_token=$this->rank_token&ranked_content=true&";
      }
      $getPendingInbox = $this->request($endpoint)[1];
      return $getPendingInbox;
  }

  public function getInbox($maxid = null)
  {
      if (is_null($maxid)) {
          $endpoint = "direct_v2/inbox/?rank_token=$this->rank_token&ranked_content=true&";
      } else {
          $endpoint = "direct_v2/inbox/?max_id=".$maxid."&rank_token=$this->rank_token&ranked_content=true&";
      }
      $getInbox = $this->request($endpoint)[1];
      return $getInbox;
  }

  public function directThreadAction($threadId, $threadAction)  // Thread Action 'approve' OR 'decline' OR 'block'
  {
      $data = json_encode([
        '_uuid'      => $this->uuid,
        '_uid'       => $this->username_id,
        'user_id'    => $userId,
        '_csrftoken' => $this->token,
    ]);

      return $this->request("direct_v2/threads/$threadId/$threadAction/", $this->generateSignature($data))[1];
  }

  public function follow($userId)
  {
      $data = json_encode([
        '_uuid'      => $this->uuid,
        '_uid'       => $this->username_id,
        'user_id'    => $userId,
        '_csrftoken' => $this->token,
    ]);

      return $this->request("friendships/create/$userId/", $this->generateSignature($data))[1];
  }

  public function unfollow($userId)
  {
      $data = json_encode([
        '_uuid'      => $this->uuid,
        '_uid'       => $this->username_id,
        'user_id'    => $userId,
        '_csrftoken' => $this->token,
    ]);

      return $this->request("friendships/destroy/$userId/", $this->generateSignature($data))[1];
  }

    public function generateSignature($data)
    {
        $hash = hash_hmac('sha256', $data, Constants::IG_SIG_KEY);

        return 'ig_sig_key_version='.Constants::SIG_KEY_VERSION.'&signed_body='.$hash.'.'.urlencode($data);
    }

    public function generateDeviceId($seed)
    {
        $volatile_seed = filemtime(__DIR__);
        return 'android-'.substr(md5($seed.$volatile_seed), 16);
    }

    public function generateUUID($type)
    {
        $uuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
      mt_rand(0, 0xffff), mt_rand(0, 0xffff),
      mt_rand(0, 0xffff),
      mt_rand(0, 0x0fff) | 0x4000,
      mt_rand(0, 0x3fff) | 0x8000,
      mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );

        return $type ? $uuid : str_replace('-', '', $uuid);
    }

    protected function buildBody($bodies, $boundary)
    {
        $body = '';
        foreach ($bodies as $b) {
            $body .= '--'.$boundary."\r\n";
            $body .= 'Content-Disposition: '.$b['type'].'; name="'.$b['name'].'"';
            if (isset($b['filename'])) {
                $ext = pathinfo($b['filename'], PATHINFO_EXTENSION);
                $body .= '; filename="'.'pending_media_'.number_format(round(microtime(true) * 1000), 0, '', '').'.'.$ext.'"';
            }
            if (isset($b['headers']) && is_array($b['headers'])) {
                foreach ($b['headers'] as $header) {
                    $body .= "\r\n".$header;
                }
            }

            $body .= "\r\n\r\n".$b['data']."\r\n";
        }
        $body .= '--'.$boundary.'--';

        return $body;
    }
    public function dm_thread($recipients, $thread_id, $text)
    {
        if (!is_array($recipients)) {
            $recipients = [$recipients];
        }
        $string = [];
        foreach ($recipients as $recipient) {
            $string[] = "\"$recipient\"";
        }
        $recipient_users = implode(',', $string);
        $endpoint = Constants::API_URL.'direct_v2/threads/broadcast/text/';
        $boundary = $this->uuid;
        $bodies = [
            [
                'type' => 'form-data',
                'name' => 'recipient_users',
                'data' => "[[$recipient_users]]",
            ],
            [
                'type' => 'form-data',
                'name' => 'client_context',
                'data' => $this->uuid,
            ],
            [
                'type' => 'form-data',
                'name' => 'thread_ids',
                'data' => '["'.$thread_id.'"]',
            ],
            [
                'type' => 'form-data',
                'name' => 'text',
                'data' => is_null($text) ? '' : $text,
            ],
        ];
        $data = $this->buildBody($bodies, $boundary);
        $headers = [
                'Proxy-Connection: keep-alive',
                'Connection: keep-alive',
                'Accept: */*',
                'Content-type: multipart/form-data; boundary='.$boundary,
                'Accept-Language: en-en',
        ];
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_USERAGENT, Constants::USER_AGENT);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_VERBOSE, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->IGDataPath."$this->username-cookies.log");
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->IGDataPath."$this->username-cookies.log");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        $resp = curl_exec($ch);
        $header_len = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $header = substr($resp, 0, $header_len);
        $upload = json_decode(substr($resp, $header_len), true);
        curl_close($ch);

        return $upload;
    }
    public function direct_message($recipients, $text)
    {
        if (!is_array($recipients)) {
            $recipients = [$recipients];
        }
        $string = [];
        foreach ($recipients as $recipient) {
            $string[] = "\"$recipient\"";
        }
        $recipient_users = implode(',', $string);
        $endpoint = Constants::API_URL.'direct_v2/threads/broadcast/text/';
        $boundary = $this->uuid;
        $bodies = [
            [
                'type' => 'form-data',
                'name' => 'recipient_users',
                'data' => "[[$recipient_users]]",
            ],
            [
                'type' => 'form-data',
                'name' => 'client_context',
                'data' => $this->uuid,
            ],
            [
                'type' => 'form-data',
                'name' => 'thread_ids',
                'data' => '["0"]',
            ],
            [
                'type' => 'form-data',
                'name' => 'text',
                'data' => is_null($text) ? '' : $text,
            ],
        ];
        $data = $this->buildBody($bodies, $boundary);
        $headers = [
                'Proxy-Connection: keep-alive',
                'Connection: keep-alive',
                'Accept: */*',
                'Content-type: multipart/form-data; boundary='.$boundary,
                'Accept-Language: en-en',
        ];
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_USERAGENT, Constants::USER_AGENT);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_VERBOSE, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->IGDataPath."$this->username-cookies.log");
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->IGDataPath."$this->username-cookies.log");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        $resp = curl_exec($ch);
        $header_len = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $header = substr($resp, 0, $header_len);
        $upload = json_decode(substr($resp, $header_len), true);
        curl_close($ch);

        return $upload;
    }

    protected function request($endpoint, $post = null, $login = false)
    {

        $headers = [
        'Connection: close',
        'Accept: */*',
        'Content-type: application/x-www-form-urlencoded; charset=UTF-8',
        'Cookie2: $Version=1',
        'Accept-Language: en-US',
        ];

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, Constants::API_URL.$endpoint);
        curl_setopt($ch, CURLOPT_USERAGENT, Constants::USER_AGENT);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_VERBOSE, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->IGDataPath."$this->username-cookies.log");
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->IGDataPath."$this->username-cookies.log");

        if ($post) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        }

        $resp = curl_exec($ch);
        $header_len = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $header = substr($resp, 0, $header_len);
        $body = substr($resp, $header_len);

        curl_close($ch);

        if ($this->debug) {
            echo "REQUEST: $endpoint\n";
            if (!is_null($post)) {
                if (!is_array($post)) {
                    echo 'DATA: '.urldecode($post)."\n";
                }
            }
            echo "RESPONSE: $body\n\n";
        }

        return [$header, json_decode($body, true)];
    }
}
