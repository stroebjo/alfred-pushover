import time
import sys
import os
import tempfile
import json

import http.client, urllib
import urllib.request
from urllib.parse import urlparse
from os.path import exists
import mimetypes

try:
	import requests
	requests_not_available = False
except ImportError:
	requests_not_available  = True


requests_warning = "You need to install the requests library for image attachments. Install with macOS system python: $ /usr/bin/python3 -m pip install requests"


# https://stackoverflow.com/a/38020041/723769
def uri_validator(x):
    try:
        result = urlparse(x)
        return all([result.scheme, result.netloc])
    except:
        return False

# the device may, or may not be set in argv[1]
device = sys.argv[1]

# the actual message is set prior into an variable.
# trim() it to remove potential line breaks from clipboard
# => URL detection would fail.
query = os.getenv('MESSAGE', '').strip()


# Max size for an attachment in bytes.
# @see https://pushover.net/api#attachments 
PUSHOVER_ATTACHMENT_MAX_SIZE = 2621440

PREVIEW_REMOTE = int(os.getenv('PREVIEW_REMOTE', 0))
PREVIEW_LOCAL  = int(os.getenv('PREVIEW_LOCAL', 0))

params = {
	"token"     : os.getenv('APP_TOKEN'),
	"user"      : os.getenv('USER_KEY'),
	'timestamp' : time.time(),

	'message'   : query,
}
files = {}

if len(device) > 0:
	params['device'] = device

fp = None # pointer for potentially tmp file


# check if message is an URL and if so get it's <title>
if (PREVIEW_REMOTE == 1 and uri_validator(query)):
	with urllib.request.urlopen(query) as response:
		body = response.read()
		info = response.info()
		http_code    = response.getcode()
		content_size = int(response.headers['content-length'])

		print(content_size)

		# set Domain Name as default title.
		url = urlparse(query)
		params['title'] = url.hostname
		params['url']   = query

		# if Response OK and is HTML, try to get a <title>
		if (http_code == 200 and info.get_content_type() == 'text/html'):
			#$doc = new DOMDocument();
			#@$doc->loadHTML($body);
			#$nodes = $doc->getElementsByTagName('title');

			# check for existing title and if is set
			#if ($nodes->length > 0) {
			#	$title = $nodes->item(0)->nodeValue;
			#
			#	if (!empty($title)) {
			#		$params['title']     = $title;
			#		$params['url_title'] = substr(parse_url($query, PHP_URL_HOST), 0, 100);
			#	}
			#}
			pass
		elif (http_code == 200 and info.get_content_maintype() == 'image'):
			# image preview
			if (requests_not_available):
				params['message'] += "\n\n"+requests_warning
				print(requests_warning)

			if (content_size <= PUSHOVER_ATTACHMENT_MAX_SIZE):
				filename = os.path.basename(url.path)
				fp = tempfile.NamedTemporaryFile()
				tmp_filename = fp.name
				fp.write(body)
				files['attachment'] = (filename, open(tmp_filename, "rb"), info.get_content_type())
				# fp gets closed after curl_exec, so request can read it
			else:
				params['message'] += "\n\nThe image was to large ({} bytes, max. is {} bytes).".format(content_size, PUSHOVER_ATTACHMENT_MAX_SIZE)
			
		# no OK response, show status code
		if (http_code != 200):
			params['message'] += "\n\nThe URL returned the status code {}.".format(http_code)
elif PREVIEW_LOCAL == 1 and query[0] == '/' and exists(query):
	# if the first char of the query is an `/` it *might* by an local path
	content_type = mimetypes.MimeTypes().guess_type(query)[0]
	filename = os.path.basename(query)
	filesize = os.path.getsize(query)

	if (requests_not_available):
		params['message'] += "\n\n"+requests_warning
		print(requests_warning)

	# is it an image that we can send?
	if (content_type is not None) and  (content_type.split('/')[0] == 'image'):
		# is the size allowed by pushover?
		if (filesize <= PUSHOVER_ATTACHMENT_MAX_SIZE):
			files['attachment'] = (filename, open(query, "rb"), content_type)
		else:
			params['message'] += "\n\nThe image was to large (%s bytes, max. is %s bytes).".format(filesize, PUSHOVER_ATTACHMENT_MAX_SIZE)
	else:
		params['message'] += "\n\nOnly image can pe previewed (was detected as %s).".format(content_type)

# Send request. Use 3rd party requests library if available.
# For sending images it's recommended by Pushover https://support.pushover.net/i44-example-code-and-pushover-libraries#python-image
if requests_not_available:
	conn = http.client.HTTPSConnection("api.pushover.net:443")
	conn.request("POST", "/1/messages.json",
	urllib.parse.urlencode(params), { "Content-type": "application/x-www-form-urlencoded" })
	r = conn.getresponse()

	# check for errors
	if (r.status != 200):
		print(r.reason)

	raw = r.read()
	conn.close()
	resp = json.loads(raw)
else:
	try:
		r = requests.post("https://api.pushover.net/1/messages.json", data = params, files = files)
		r.raise_for_status()
	except requests.exceptions.HTTPError as err:
		raise SystemExit(err)

	resp = json.loads(r.text)

# remove tmp file for image attachment AFTER sending
if (fp != None):
	fp.close()

# check for pushover errors
if ('status' in resp and resp['status'] != 1):
	print(resp['errors'].join(', '))
