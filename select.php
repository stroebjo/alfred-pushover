<?php

error_reporting(E_ALL);

$response = ['items' => []];

// Send to all devices
$response['items'][] = [
	'arg'   => '',
	'title' => 'Send to all devices',
];

// check for devices
if (getenv('devices') !== false) {
	$devices = array_map('trim', explode(',', getenv('devices')));
	foreach($devices as $device) {
		$response['items'][] = [
			'arg'   => htmlspecialchars($device),
			'title' => htmlspecialchars($device),
		];
	}
}

echo json_encode($response);
