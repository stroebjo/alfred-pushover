<?php
error_reporting(E_ALL);

// the device may, or may not be set in argv[1]
$device = $argv[1];

// the actual message is set prio into an variable.
// trim() it to remove potential linebreaks from clipboard
// => URL detection would fail.
$query = trim($_ENV['message']);

$params = array(
	"token"     => $_ENV['APP_TOKEN'],
	"user"      => $_ENV['USER_KEY'],
	'timestamp' => time(),

	'message'   => $query,
);

if (!empty($device)) {
	$params['device'] = $device;
}

// check if message is an URL and if so get it's <title>
if (filter_var($query, FILTER_VALIDATE_URL)) {

	$ch = curl_init();

    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_URL, $query);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);

	$html     = curl_exec($ch);
	$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

	curl_close($ch);

	// set Domain Name as default title.
	$params['title'] = parse_url($query, PHP_URL_HOST);
	$params['url']   = $query;

	//parsing begins here:
	if ($httpcode === 200) {
		$doc = new DOMDocument();
		@$doc->loadHTML($html);
		$nodes = $doc->getElementsByTagName('title');

		// check for existing title and if is set
		if ($nodes->length > 0) {
			$title = $nodes->item(0)->nodeValue;

			if (!empty($title)) {
				$params['title']     = $title;
				$params['url_title'] = substr(parse_url($query, PHP_URL_HOST), 0, 100);
			}
		}

	} else {
		// no OK response, show status code
		$params['message'] .= "\n\n" . sprintf('The URL returned the status code %s.', $httpcode);
	}
}

$ch = curl_init();
curl_setopt_array($ch, array(
	CURLOPT_URL            => "https://api.pushover.net/1/messages.json",
	CURLOPT_POSTFIELDS     => $params,
	CURLOPT_SAFE_UPLOAD    => true,
	CURLOPT_RETURNTRANSFER => true,

));
$success = curl_exec($ch);

// check for cURL errors
if (false === $success) {
	$error = curl_error($ch);
	echo $error;
}
curl_close($ch);

// check for pushover errors
$response = json_decode($success);

if (isset($response->status) && $response->status != 1) {
	echo implode(', ', $response->errors);
}

?>
