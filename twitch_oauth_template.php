<pre>
<code>
if (isset($_GET['error']) || isset($_GET['error_description'])) {
    $error = $_GET['error'] ?? '';
    $error_description = $_GET['error_description'] ?? '';
    echo "Error [$error] [$error_description]";
    return;
}


/**
 * Decode JSON input from php://input.
 * PHP doesn't handle multi-part JSON correctly without this workaround.
 */
$_JSON = json_decode(file_get_contents("php://input"), true);
if ($_JSON) foreach ($_JSON as $key => $value) { $_POST[$key] = $value; }

$client_id = '';
$client_secret = '';
$redirect_uri = 'https://www.yourwebsite.com/oauth2/';
$state = bin2hex(random_bytes(16)); // Generate a random state string for CSRF protection

// Save the state in the session to verify it later
$_SESSION['oauth2_state'] = $state;

// https://dev.twitch.tv/docs/authentication/scopes/
$scopes = [
    'analytics:read:extensions',
    'analytics:read:games',
    'bits:read',
    'channel:bot',
    'channel:manage:ads',
    'channel:read:ads',
    'channel:manage:broadcast',
    'channel:read:charity',
    'channel:edit:commercial',
    'channel:read:editors',
    'channel:manage:extensions',
    'channel:read:goals',
    'channel:read:guest_star',
    'channel:manage:guest_star',
    'channel:read:hype_train',
    'channel:manage:moderators',
    'channel:read:polls',
    'channel:manage:polls',
    'channel:read:predictions',
    'channel:manage:predictions',
    'channel:manage:raids',
    'channel:read:redemptions',
    'channel:manage:redemptions',
    'channel:manage:schedule',
    'channel:read:stream_key',
    'channel:read:subscriptions',
    'channel:manage:videos',
    'channel:read:vips',
    'channel:manage:vips',
    'clips:edit',
    'moderation:read',
    'moderator:manage:announcements',
    'moderator:manage:automod',
    'moderator:read:automod_settings',
    'moderator:manage:automod_settings',
    'moderator:read:banned_users',
    'moderator:manage:banned_users',
    'moderator:read:blocked_terms',
    'moderator:read:chat_messages',
    'moderator:manage:blocked_terms',
    'moderator:manage:chat_messages',
    'moderator:read:chat_settings',
    'moderator:manage:chat_settings',
    'moderator:read:chatters',
    'moderator:read:followers',
    'moderator:read:guest_star',
    'moderator:manage:guest_star',
    'moderator:read:moderators',
    'moderator:read:shield_mode',
    'moderator:manage:shield_mode',
    'moderator:read:shoutouts',
    'moderator:manage:shoutouts',
    'moderator:read:suspicious_users',
    'moderator:read:unban_requests',
    'moderator:manage:unban_requests',
    'moderator:read:vips',
    'moderator:read:warnings',
    'moderator:manage:warnings',
    'user:bot',
    'user:edit',
    'user:edit:broadcast',
    'user:read:blocked_users',
    'user:manage:blocked_users',
    'user:read:broadcast',
    'user:read:chat',
    'user:manage:chat_color',
    'user:read:email',
    'user:read:emotes',
    'user:read:follows',
    'user:read:moderated_channels',
    'user:read:subscriptions',
    'user:read:whispers',
    'user:manage:whispers',
    'user:write:chat',
    'chat:edit',
    'chat:read',
    'whispers:read',
];
$scope_string = implode(' ', $scopes);

function doCurl(string $url, string $type = 'GET', array $headers = [], array $post_fields = [], string $user_agent = '', string $referrer = '', bool $follow = true, bool $use_ssl = false, int $con_timeout = 10, int $timeout = 40)
{
    $crl = curl_init($url);
    curl_setopt($crl, CURLOPT_CUSTOMREQUEST, $type);
    curl_setopt($crl, CURLOPT_USERAGENT, $user_agent);
    curl_setopt($crl, CURLOPT_REFERER, $referrer);
    if ($type == 'POST') {
        curl_setopt($crl, CURLOPT_POST, true);
        if (! empty($post_fields)) curl_setopt($crl, CURLOPT_POSTFIELDS, $post_fields);
    }
    if (! empty($headers)) curl_setopt($crl, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($crl, CURLOPT_FOLLOWLOCATION, $follow);
    curl_setopt($crl, CURLOPT_CONNECTTIMEOUT, $con_timeout);
    curl_setopt($crl, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($crl, CURLOPT_SSL_VERIFYHOST, $use_ssl);
    curl_setopt($crl, CURLOPT_SSL_VERIFYPEER, $use_ssl);
    curl_setopt($crl, CURLOPT_ENCODING, 'gzip,deflate'); 
    curl_setopt($crl, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($crl);
    curl_close($crl);
    return $response;
}

function noCurl(string $url, string $type = 'GET', array $headers = [], array $post_fields = [], string $user_agent = '', string $referrer = '', bool $follow = true, bool $use_ssl = false, int $con_timeout = 10, int $timeout = 40)
{
    $options = [
        'http' => [
            'method' => $type,
            'header' => '',
            'timeout' => $timeout,
            'follow_location' => $follow ? 1 : 0,
        ],
        'ssl' => [
            'verify_peer' => $use_ssl,
            'verify_peer_name' => $use_ssl,
        ],
    ];

    if (!empty($user_agent)) $options['http']['header'] .= "User-Agent: $user_agent\r\n";

    if (!empty($referrer)) $options['http']['header'] .= "Referer: $referrer\r\n";

    if (!empty($headers)) foreach ($headers as $header) $options['http']['header'] .= "$header\r\n";

    if ($type == 'POST' && !empty($post_fields)) {
        $options['http']['content'] = json_encode($post_fields);
        $options['http']['header'] .= "Content-Type: application/json\r\n";
    }

    $context = stream_context_create($options);
    $call_response = file_get_contents($url, false, $context);

    return $call_response;
}

$check = $_GET['code'] ?? null;
$access_token = $_GET['access_token'] ?? null;

if ( is_null($access_token) && !is_null($check) ) {
    echo (function_exists('curl_version')
        ? doCurl("https://id.twitch.tv/oauth2/token?client_id=$client_id&client_secret=$client_secret&code=$check&grant_type=authorization_code&redirect_uri=$redirect_uri", 'POST')
        : noCurl("https://id.twitch.tv/oauth2/token?client_id=$client_id&client_secret=$client_secret&code=$check&grant_type=authorization_code&redirect_uri=$redirect_uri", 'POST')
    );
    $response_data = json_decode($response, true);
    if (isset($response_data['access_token'])) echo "Access Token: " . $response_data['access_token'];
    else echo "Error retrieving access token: " . $response;
    return;
} else {
    $auth_url = 'https://id.twitch.tv/oauth2/authorize';
    $auth_url .= '?response_type=token';
    $auth_url .= '&client_id=' . $client_id;
    $auth_url .= '&redirect_uri=' . urlencode($redirect_uri);
    $auth_url .= '&scope=' . urlencode($scope_string);
    $auth_url .= '&state=' . $state;
    $auth_url .= '&force_verify=true';

    echo '&lt;a href="' . $auth_url . '"&gt;' . "Please Click this Link to Authenticate with Twitch&lt;/a&gt;" . PHP_EOL;
    //header('Location: ' . $auth_url);
    //exit();

    $access_token = $_GET['access_token'] ?? null;
    $state = $_GET['state'] ?? null;

    //header('Content-Type: application/json');

    if ($access_token) {
        header('Location: ' . $auth_url);
        echo json_encode($_GET);
    }
}
?>
&lt;script&gt;
// Extract the parameters from the URL fragment
const hash = window.location.hash.substring(1);
const params = new URLSearchParams(hash);
const paramsObject = {};

// Convert the parameters to an object
params.forEach((value, key) => {
    paramsObject[key] = value;
});

// Format the "scope" parameter as an array
if (paramsObject.scope) {
    paramsObject.scope = paramsObject.scope.split(' ');
}

// Set the page's header to JSON
document.head.innerHTML = '&lt;meta http-equiv="Content-Type" content="application/json"&gt';

// Output all parameters as pretty-printed JSON
document.body.innerHTML = '&lt;pre&gt' + JSON.stringify(paramsObject, null, 2) + '&lt;/pre&gt;';
&lt;/script&gt;
</code>