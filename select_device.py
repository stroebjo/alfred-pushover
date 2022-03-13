import os
import json

response = {'items': []}

# Send to all devices
response['items'].append({
	'arg'  : '',
	'title': 'Send to all devices',
})

# check for devices
devices = os.getenv('DEVICES', False)
if devices:
	devices = [s.strip() for s in devices.split(',')]

	for device in devices:
		response['items'].append({
			'arg'  : device,
			'title': device,
		})

print(json.dumps(response))