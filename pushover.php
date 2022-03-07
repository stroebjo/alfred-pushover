<?php
error_reporting(E_ALL);

// the device may, or may not be set in argv[1]
$device = $argv[1];

// the actual message is set prior into an variable.
// trim() it to remove potential line breaks from clipboard
// => URL detection would fail.
$query = trim(getenv('message'));

/**
 * Max size for an attachment in bytes.
 * 
 * @see https://pushover.net/api#attachments
 * 
 */
define('PUSHOVER_ATTACHMENT_MAX_SIZE',  2621440);



$PREVIEW_REMOTE = (getenv('PREVIEW_REMOTE') !== false ? intval(getenv('PREVIEW_REMOTE')) : 0);
$PREVIEW_LOCAL = (getenv('PREVIEW_LOCAL') !== false ? intval(getenv('PREVIEW_LOCAL')) : 0);

$params = array(
	"token"     => getenv('APP_TOKEN'),
	"user"      => getenv('USER_KEY'),
	'timestamp' => time(),

	'message'   => $query,
);

if (!empty($device)) {
	$params['device'] = $device;
}

$temp_pointer = null;

// check if message is an URL and if so get it's <title>
if ($PREVIEW_REMOTE === 1 && filter_var($query, FILTER_VALIDATE_URL)) {

	$ch = curl_init();

    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_URL, $query);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);

	$response            = curl_exec($ch);
	list($header, $body) = explode("\r\n\r\n", $response, 2);
	$httpcode            = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	$content_type        = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
	$content_size        = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
	curl_close($ch);

	// set Domain Name as default title.
	$params['title'] = parse_url($query, PHP_URL_HOST);
	$params['url']   = $query;

	// if Response OK and is HTML, try to get a <title>
	if ($httpcode === 200 && $content_type === 'text/html') {
		$doc = new DOMDocument();
		@$doc->loadHTML($body);
		$nodes = $doc->getElementsByTagName('title');

		// check for existing title and if is set
		if ($nodes->length > 0) {
			$title = $nodes->item(0)->nodeValue;

			if (!empty($title)) {
				$params['title']     = $title;
				$params['url_title'] = substr(parse_url($query, PHP_URL_HOST), 0, 100);
			}
		}
	} else if ($httpcode === 200 && strpos(strtolower($content_type), 'image') === 0) {
		// image preview
		if ($content_size <= PUSHOVER_ATTACHMENT_MAX_SIZE) {
			$filename = basename(parse_url($query, PHP_URL_PATH));

			$temp_pointer = tmpfile(); 
			$metaDatas = stream_get_meta_data($temp_pointer);
			$tmpFilename = $metaDatas['uri'];
			fwrite($temp_pointer, $body); 

			$params['attachment'] = new CurlFile($tmpFilename, $content_type, $filename);

			// tmpfile gets closed after curl_exec, so CurlFile can read it
		} else {
			$params['message'] .= "\n\n" . sprintf('The image was to large (%d bytes, max. is %s bytes).', number_format($content_size), number_format(PUSHOVER_ATTACHMENT_MAX_SIZE));
		}
	}

	// no OK response, show status code
	if ($httpcode != 200) {
		$params['message'] .= "\n\n" . sprintf('The URL returned the status code %s.', $httpcode);
	}
} else if ($PREVIEW_LOCAL === 1 && strpos($query, '/') === 0 && @file_exists($query)) {
	// if the first char of the query is an `/` it *might* by an local path
	// check with file_exists if it's an actual file

	$content_type = mime_content_type($query);
	$filename = basename($query);
	$filesize = filesize($query);

	if(strpos(strtolower($content_type), 'image') === 0 && $filesize <= PUSHOVER_ATTACHMENT_MAX_SIZE) {
		$params['attachment'] = new CurlFile($query, $content_type, $filename);
	} else {
		$params['message'] .= "\n\n" . sprintf('The image was to large (%s bytes, max. is %s bytes).',  number_format($filesize), number_format(PUSHOVER_ATTACHMENT_MAX_SIZE));
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

// remove tmp file for image attachment AFTER curl_exec
if (!is_null($temp_pointer)) {
	fclose($temp_pointer);
}

// check for pushover errors
$response = json_decode($success);

if (isset($response->status) && $response->status != 1) {
	echo implode(', ', $response->errors);
}

?>