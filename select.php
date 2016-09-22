<?php

error_reporting(E_ALL);

$query = $argv[1];

$xml = "<?xml version=\"1.0\"?>\n<items>\n";
$response = ['items' => []];



// Send to all devices
if (!$query) {
	$response['items'][] = [
		'arg'   => '',
		'title' => 'Send to all devices',
	];
}

// check for devices
$devices = array_map('trim', explode(',', $_ENV['devices']));


foreach($devices as $device) {

	if ($query) {
		$query_matched = stripos($device, $query);

		if ($query_matched === false) {
			continue;
		}
	}

	$response['items'][] = [
		'arg' => htmlspecialchars($device),
		'title' => htmlspecialchars($device),
	];
}

echo json_encode($response);


?>
