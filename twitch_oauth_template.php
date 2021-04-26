</php
//Fill in your own values before running this script from your website
$client_id = ''; // Obtained from https://dev.twitch.tv/console/apps
$client_secret = ''; // Obtained from https://dev.twitch.tv/console/apps
$redirect_uri = ''; // OAuth Redirect URL set by you in https://dev.twitch.tv/console/apps

function doCurl(string $url, string $type = 'GET', array $headers = [], array $post_fields = [], string $user_agent = '', string $referrer = '', bool $follow = true, bool $use_ssl = false, int $con_timeout = 10, int $timeout = 40)
{
    $crl = curl_init($url);
    curl_setopt($crl, CURLOPT_CUSTOMREQUEST, $type);
    curl_setopt($crl, CURLOPT_USERAGENT, $user_agent);
    curl_setopt($crl, CURLOPT_REFERER, $referrer);
    if ($type == 'POST') {
        curl_setopt($crl, CURLOPT_POST, true);
        if (!empty($post_fields)) {
            curl_setopt($crl, CURLOPT_POSTFIELDS, $post_fields);
        }
    }
    if (!empty($headers)) {
        curl_setopt($crl, CURLOPT_HTTPHEADER, $headers);
    }
    curl_setopt($crl, CURLOPT_FOLLOWLOCATION, $follow);
    curl_setopt($crl, CURLOPT_CONNECTTIMEOUT, $con_timeout);
    curl_setopt($crl, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($crl, CURLOPT_SSL_VERIFYHOST, $use_ssl);
    curl_setopt($crl, CURLOPT_SSL_VERIFYPEER, $use_ssl);
    curl_setopt($crl, CURLOPT_ENCODING, 'gzip,deflate'); 
    curl_setopt($crl, CURLOPT_RETURNTRANSFER, true);
    $call_response = curl_exec($crl);
    curl_close($crl);
    return $call_response;//Return data
}

$check = $_GET['code'];
$access_token = $_GET['access_token'];

if ($access_token){
	echo "access_token:" . $_GET['access_token'] .
	"refresh_token:" . $_GET['refresh_token'] .
	"expires_in:" . $_GET['expires_in'] .
	"scope:" . $_GET['scope'] .
	"token_type:" . $_GET['token_type'];
	return;
}

if ( is_null($access_token) && !is_null($check) ) {
	echo doCurl("https://id.twitch.tv/oauth2/token?client_id=$client_id&client_secret=$client_secret&code=$check&grant_type=authorization_code&redirect_uri=$redirect_uri", 'POST');
	return;
} else {
	$scopes = array(
		'viewing_activity_read' => 1,
		'user:edit' => 1,
		'user:read:email' => 1,
		/*'user_follows_edit' => 1,
		'user_blocks_read' => 1,*/
	);

	$req_scope = '';
	foreach ($scopes as $scope => $allow) {
		if ($allow) {
			$req_scope .= $scope . '+';
		}
	}
	$req_scope = substr($req_scope, 0, -1);

	$auth_url = 'https://id.twitch.tv/oauth2/authorize?response_type=code';
	$auth_url .= '&client_id=' . $client_id;
	$auth_url .= '&redirect_uri=' . $redirect_uri;
	$auth_url .= '&scope=' . $req_scope;
	$auth_url .= '&force_verify=true';

	echo '<a href="' . $auth_url . '">Please Click this Link to Authenticate with Twitch</a>';
}
?>
