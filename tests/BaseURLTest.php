<?php
// OTAgo
// - OTA App Distribution System
// - https://github.com/DaveWoodCom/OTAgo
// - Copyright 2020 Dave Wood, Cerebral Gardens Inc.

use PHPUnit\Framework\TestCase;

// Covers detectBaseURL() in common.php - the value every install/manifest/IPA URL is built
// from. These tests pin the current behaviour across the server setups OTAgo gets deployed
// on, including the ones where detection can't get it right and $baseURL has to be set in
// configuration.php.
final class BaseURLTest extends TestCase {

	public function testApacheOverHTTPS(): void {
		$this->assertSame('https://ota.example.com/', detectBaseURL(array(
			'REQUEST_SCHEME' => 'https',
			'HTTP_HOST' => 'ota.example.com',
			'SERVER_PORT' => '443',
		)));
	}

	public function testApacheOverPlainHTTP(): void {
		$this->assertSame('http://ota.example.com/', detectBaseURL(array(
			'REQUEST_SCHEME' => 'http',
			'HTTP_HOST' => 'ota.example.com',
			'SERVER_PORT' => '80',
		)));
	}

	// HTTP_HOST carries the port when it isn't the default for the scheme, so SERVER_PORT
	// must not be appended a second time.
	public function testNonStandardPortComesFromHTTPHostOnly(): void {
		$this->assertSame('https://ota.example.com:8443/', detectBaseURL(array(
			'REQUEST_SCHEME' => 'https',
			'HTTP_HOST' => 'ota.example.com:8443',
			'SERVER_PORT' => '8443',
		)));

		$this->assertSame('http://ota.example.com:8080/', detectBaseURL(array(
			'REQUEST_SCHEME' => 'http',
			'HTTP_HOST' => 'ota.example.com:8080',
			'SERVER_PORT' => '8080',
		)));
	}

	public function testSubdomainAndIPv6Hosts(): void {
		$this->assertSame('https://builds.ci.example.co.uk/', detectBaseURL(array(
			'REQUEST_SCHEME' => 'https',
			'HTTP_HOST' => 'builds.ci.example.co.uk',
		)));

		$this->assertSame('http://[2001:db8::1]:8080/', detectBaseURL(array(
			'REQUEST_SCHEME' => 'http',
			'HTTP_HOST' => '[2001:db8::1]:8080',
		)));
	}

	// nginx + php-fpm sets REQUEST_SCHEME through fastcgi_params on most distributions, so
	// the common case is the same as Apache's.
	public function testNginxWithRequestSchemeSet(): void {
		$this->assertSame('https://ota.example.com/', detectBaseURL(array(
			'REQUEST_SCHEME' => 'https',
			'HTTP_HOST' => 'ota.example.com',
			'HTTPS' => 'on',
		)));
	}

	// KNOWN GAP, pinned deliberately: REQUEST_SCHEME is not a CGI standard variable. On an
	// nginx/php-fpm setup that doesn't pass it, detection produces a broken '://host/' and
	// raises an undefined-key warning. $baseURL has to be set in configuration.php there.
	// Left as-is for now so this issue stays test-only - see the follow-up note on #21.
	public function testMissingRequestSchemeProducesABrokenURL(): void {
		$warnings = array();
		set_error_handler(function ($number, $message) use (&$warnings) {
			$warnings[] = $message;
			return true;
		});

		try {
			$baseURL = detectBaseURL(array('HTTP_HOST' => 'ota.example.com'));
		}
		finally {
			restore_error_handler();
		}

		$this->assertSame('://ota.example.com/', $baseURL);
		$this->assertCount(1, $warnings);
		$this->assertStringContainsString('REQUEST_SCHEME', $warnings[0]);
	}

	// KNOWN GAP, pinned deliberately: behind a TLS-terminating proxy the request reaching PHP
	// is plain HTTP, so detection reports http:// even though the browser used https://.
	// X-Forwarded-Proto is intentionally not trusted (it's client-settable when the app isn't
	// actually behind a proxy). Set $baseURL in configuration.php for proxied deployments.
	public function testProxiedHTTPSIsReportedAsHTTP(): void {
		$this->assertSame('http://ota.example.com/', detectBaseURL(array(
			'REQUEST_SCHEME' => 'http',
			'HTTP_HOST' => 'ota.example.com',
			'HTTP_X_FORWARDED_PROTO' => 'https',
			'HTTP_X_FORWARDED_HOST' => 'ota.example.com',
			'SERVER_PORT' => '8080',
		)));
	}

	// Whatever the base URL is, the links built on it have to stay well formed.
	public function testBaseURLComposesIntoAManifestURL(): void {
		$baseURL = detectBaseURL(array(
			'REQUEST_SCHEME' => 'https',
			'HTTP_HOST' => 'ota.example.com:8443',
		));

		$manifestURL = $baseURL . 'manifest.php?' . makeURLQueryString(array(
			'u' => 'dave',
			't' => '2b4d1f6c-1f0e-4a5b-9c3d-0e1f2a3b4c5d',
		), '&');

		$this->assertSame('https://ota.example.com:8443/manifest.php?u=dave&t=2b4d1f6c-1f0e-4a5b-9c3d-0e1f2a3b4c5d', $manifestURL);
	}

	public function testMakeURLQueryStringEscapesValues(): void {
		$this->assertSame('u=a%2Bb+c&t=x%26y', makeURLQueryString(array('u' => 'a+b c', 't' => 'x&y'), '&'));
	}
}
