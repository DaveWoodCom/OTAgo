<?php
// OTAgo
// - OTA App Distribution System
// - https://github.com/DaveWoodCom/OTAgo
// - Copyright 2020 Dave Wood, Cerebral Gardens Inc.

// OTAgo requires PHP 8.1 or newer (PHP 7 reached end of life in November 2022). This file
// deliberately avoids PHP 8-only syntax so that an older interpreter gets this clear message
// instead of a parse error from one of the files loaded after it.
if (PHP_VERSION_ID < 80100) {
	http_response_code(500);
	header('Content-Type: text/plain');
	exit('OTAgo requires PHP 8.1 or newer; this server is running PHP ' . PHP_VERSION . '.');
}

// OTAgo's own version. Bump this with each release (see the release steps in README.md).
define('OTAGO_VERSION', '1.2.0');

// Placeholder token replaced with OTAGO_VERSION when rendering the web template.
// Not user-configurable, so it lives here rather than in configuration.php.
define('OTAGO_VERSION_PLACEHOLDER', '{{OTAGOVERSION}}');

// Builds the base URL OTAgo links back to itself with, from a $_SERVER-shaped array.
//
// The scheme is worked out portably: REQUEST_SCHEME is Apache-only and unset on nginx/php-fpm
// and PHP's built-in server, so HTTPS and SERVER_PORT are consulted too. HTTP_HOST already
// includes the port when it's a non-standard one, so SERVER_PORT is never appended.
//
// Behind a TLS-terminating proxy the request that reaches PHP is plain HTTP, so the only way
// to know the browser used https:// is the proxy's X-Forwarded-Proto / X-Forwarded-Host
// headers. Those are client-settable when there is no proxy in front, so they are only
// honoured when $trustProxyHeaders is true (set it in configuration.php only if every request
// really does arrive through a proxy that overwrites them).
//
// If detection still isn't right for your setup, set $baseURL in configuration.php directly.
function detectBaseURL($server, $trustProxyHeaders = false) {
	$scheme = null;
	$host = isset($server['HTTP_HOST']) ? $server['HTTP_HOST'] : '';

	if ($trustProxyHeaders) {
		if (!empty($server['HTTP_X_FORWARDED_PROTO'])) {
			// A chain of proxies appends values: "https, http". The first is the client's.
			$forwarded = strtolower(trim(explode(',', $server['HTTP_X_FORWARDED_PROTO'])[0]));
			if ($forwarded === 'https' || $forwarded === 'http') {
				$scheme = $forwarded;
			}
		}
		if (!empty($server['HTTP_X_FORWARDED_HOST'])) {
			$host = trim(explode(',', $server['HTTP_X_FORWARDED_HOST'])[0]);
		}
	}

	if ($scheme === null) {
		if (!empty($server['HTTPS']) && strtolower($server['HTTPS']) !== 'off') {
			$scheme = 'https';
		}
		elseif (!empty($server['REQUEST_SCHEME'])) {
			$scheme = strtolower($server['REQUEST_SCHEME']);
		}
		elseif (isset($server['SERVER_PORT']) && (int)$server['SERVER_PORT'] === 443) {
			$scheme = 'https';
		}
		else {
			$scheme = 'http';
		}
	}

	return $scheme . '://' . $host . '/';
}

// Sets $baseURL unless configuration.php already did. Call this after configuration.php has
// been loaded so that $trustProxyHeaders is known; the entry points that build URLs do.
function resolveBaseURL() {
	if (isset($GLOBALS['baseURL'])) {
		return;
	}
	$GLOBALS['baseURL'] = detectBaseURL($_SERVER, !empty($GLOBALS['trustProxyHeaders']));
}

function preventCaching() {
	header('Cache-Control: no-cache, no-store, must-revalidate');
	header('Pragma: no-cache');
	header('Expires: 0');

	// Basic security headers. Note: Strict-Transport-Security is deliberately not sent here;
	// HSTS is a sticky, site-wide policy that belongs in your web server's configuration.
	header('X-Content-Type-Options: nosniff');
	header('Referrer-Policy: no-referrer');
}

function requestBasicAuthentication($realm) {
	header('WWW-Authenticate: Basic realm="' . $realm . '"');
    http_response_code(401);
    exit;
}

function returnInvalidConfiguration() {
    http_response_code(501);
    exit;
}

function makeURLQueryString($parameters, $joinString) {
	$pairs = array();
	
	foreach ($parameters as $name => $value) {
		$pairs[] = urlencode($name) . '=' . urlencode($value);
	}
	
	return implode($joinString, $pairs);
}

// Back-compat for the pre-2.0 "PlacehHolder" typo in configuration.php. If the new name is
// unset but the old one is, copy it across and emit a deprecation notice. Call this after
// configuration.php has been loaded. The old names will be dropped in a future release.
function resolveDeprecatedPlaceholderNames() {
	$renames = array(
		'installURLPlacehHolder' => 'installURLPlaceholder',
		'ipaURLPlacehHolder' => 'ipaURLPlaceholder',
	);
	foreach ($renames as $old => $new) {
		if (!isset($GLOBALS[$new]) && isset($GLOBALS[$old])) {
			$GLOBALS[$new] = $GLOBALS[$old];
			trigger_error('$' . $old . ' is deprecated; rename it to $' . $new . ' in configuration.php', E_USER_DEPRECATED);
		}
	}
}

// Parses a `Range:` request header against a known file size.
//
// Returns:
//   array(start, end)  an inclusive byte range to serve as a 206
//   null               no usable range - serve the whole file as a 200 (no header, an unknown
//                      unit, a syntactically invalid range, or a multi-range request we choose
//                      not to split into a multipart/byteranges response)
//   false              a well-formed but unsatisfiable range - the caller should send a 416
//
// Split out of sendFile() so the parsing can be unit tested without an HTTP request. Per
// RFC 9110 an unparsable Range header is ignored (full response) while a valid one that falls
// outside the file is a 416, which is why "ignore" and "unsatisfiable" are distinct results.
function parseRangeHeader($header, $size) {
	if (!is_string($header) || $header === '' || $size < 0) {
		return null;
	}

	// Only the `bytes` unit is defined for ordinary file downloads; anything else is ignored.
	if (strncasecmp($header, 'bytes=', 6) !== 0) {
		return null;
	}
	$spec = trim(substr($header, 6));

	// A multi-range request would need a multipart/byteranges body. Serving the whole file is
	// a legal response to any Range request, so take that simpler route.
	if ($spec === '' || strpos($spec, ',') !== false) {
		return null;
	}

	$dash = strpos($spec, '-');
	if ($dash === false) {
		return null;
	}
	$firstPart = trim(substr($spec, 0, $dash));
	$lastPart = trim(substr($spec, $dash + 1));

	// An empty file can't satisfy any range.
	if ($size === 0) {
		return false;
	}

	if ($firstPart === '') {
		// Suffix form: "bytes=-n" asks for the final n bytes.
		if ($lastPart === '' || !ctype_digit($lastPart)) {
			return null;
		}
		$suffixLength = (int)$lastPart;
		if ($suffixLength === 0) {
			return false;
		}
		$start = $suffixLength >= $size ? 0 : $size - $suffixLength;

		return array($start, $size - 1);
	}

	if (!ctype_digit($firstPart)) {
		return null;
	}
	$start = (int)$firstPart;

	if ($lastPart === '') {
		// "bytes=a-" - from a to the end of the file.
		if ($start >= $size) {
			return false;
		}

		return array($start, $size - 1);
	}

	if (!ctype_digit($lastPart)) {
		return null;
	}
	$end = (int)$lastPart;

	// A last-byte-pos before the first is invalid rather than unsatisfiable, so it's ignored.
	if ($end < $start) {
		return null;
	}
	if ($start >= $size) {
		return false;
	}
	if ($end >= $size) {
		$end = $size - 1;
	}

	return array($start, $end);
}

// Streams a file to the client with the headers a mobile package installer needs, honouring a
// single-range `Range:` request so an interrupted download can be resumed rather than restarted.
//
// Reads in chunks through fopen()/fread() rather than readfile() so that a multi-hundred-megabyte
// build never has to fit in memory_limit, and drops any output buffering first for the same
// reason (and so the Content-Length we advertise is the length we actually send).
function sendFile($path, $contentType) {
	$size = is_file($path) && is_readable($path) ? @filesize($path) : false;
	$handle = $size === false ? false : @fopen($path, 'rb');
	if ($handle === false) {
		// Don't echo the path: it's a server-side location the client has no business seeing.
		http_response_code(404);
		header('Content-Type: text/plain');
		echo "Not found\n";

		return false;
	}

	$range = parseRangeHeader(isset($_SERVER['HTTP_RANGE']) ? $_SERVER['HTTP_RANGE'] : '', $size);

	header('Content-Type: ' . $contentType);
	header('Content-Disposition: attachment; filename="' . basename($path) . '"');
	header('Accept-Ranges: bytes');

	if ($range === false) {
		fclose($handle);
		http_response_code(416);
		header('Content-Range: bytes */' . $size);
		header('Content-Length: 0');

		return false;
	}

	if ($range === null) {
		$start = 0;
		$end = $size > 0 ? $size - 1 : -1;
	}
	else {
		$start = $range[0];
		$end = $range[1];
		http_response_code(206);
		header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
	}

	$length = $end - $start + 1;
	header('Content-Length: ' . $length);

	// A HEAD response carries the same headers as the GET would, but no body.
	if (isset($_SERVER['REQUEST_METHOD']) && strtoupper($_SERVER['REQUEST_METHOD']) === 'HEAD') {
		fclose($handle);

		return true;
	}

	// Anything already buffered would be counted against Content-Length and held in memory.
	while (ob_get_level()) {
		ob_end_clean();
	}

	if ($start > 0 && fseek($handle, $start) !== 0) {
		fclose($handle);

		return false;
	}

	$chunkSize = 8 * 1024 * 1024;
	$remaining = $length;
	while ($remaining > 0 && !feof($handle)) {
		$buffer = fread($handle, (int)min($chunkSize, $remaining));
		if ($buffer === false || $buffer === '') {
			break;
		}
		echo $buffer;
		$remaining -= strlen($buffer);
		flush();
	}
	fclose($handle);

	return true;
}
