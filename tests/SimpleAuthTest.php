<?php
// OTAgo
// - OTA App Distribution System
// - https://github.com/DaveWoodCom/OTAgo
// - Copyright 2020 Dave Wood, Cerebral Gardens Inc.

use PHPUnit\Framework\TestCase;

// Covers auth/simple/simpleAuth.php. The provider communicates through globals, so each test
// sets $users / $username / $password / $requestUUID and calls isValidUser() directly.
final class SimpleAuthTest extends TestCase {

	private string $tokenDirectory;

	protected function setUp(): void {
		$this->tokenDirectory = $GLOBALS['simpleAuthTempDirectory'];
		$this->emptyTokenDirectory();

		$GLOBALS['simpleAuthTokenLifetime'] = 3600;
		$GLOBALS['users'] = array(
			'dave' => 'secr3t',
			'numeric' => '1000',
		);
		$this->request('dave', null, randomUUID());
	}

	protected function tearDown(): void {
		$this->emptyTokenDirectory();
	}

	private function emptyTokenDirectory(): void {
		if (!is_dir($this->tokenDirectory)) {
			return;
		}
		foreach (scandir($this->tokenDirectory) as $entry) {
			if ($entry === '.' || $entry === '..') {
				continue;
			}
			@unlink($this->tokenDirectory . '/' . $entry);
		}
	}

	// Sets the globals a request would have produced. $password is null when the request
	// carried no Authorization header.
	private function request(?string $username, ?string $password, ?string $requestUUID = null): void {
		$GLOBALS['username'] = $username;
		$GLOBALS['password'] = $password;
		$GLOBALS['requestUUID'] = $requestUUID ?? randomUUID();
	}

	/////////////////////////////////////////////////////////////////////////////////////
	// Password authentication
	/////////////////////////////////////////////////////////////////////////////////////

	// Regression test for the NULL == NULL bypass: ?u=<unknown> with no Authorization header
	// left both sides NULL, and the loose comparison authenticated the request.
	public function testUnknownUsernameWithNoAuthorizationHeaderIsRejected(): void {
		$this->request('attacker', null);
		$this->assertFalse(isValidUser());
	}

	public function testUnknownUsernameWithAnEmptyPasswordIsRejected(): void {
		$this->request('attacker', '');
		$this->assertFalse(isValidUser());
	}

	public function testUnknownUsernameWithSomePasswordIsRejected(): void {
		$this->request('attacker', 'secr3t');
		$this->assertFalse(isValidUser());
	}

	public function testKnownUsernameWithNoAuthorizationHeaderIsRejected(): void {
		$this->request('dave', null);
		$this->assertFalse(isValidUser());
	}

	public function testEmptyPasswordIsRejected(): void {
		$this->request('dave', '');
		$this->assertFalse(isValidUser());
	}

	public function testWrongPasswordIsRejected(): void {
		$this->request('dave', 'wrong');
		$this->assertFalse(isValidUser());
	}

	public function testCorrectPasswordIsAccepted(): void {
		$this->request('dave', 'secr3t');
		$this->assertTrue(isValidUser());
	}

	public function testEmptyUsernameIsRejected(): void {
		$this->request('', 'secr3t');
		$this->assertFalse(isValidUser());
	}

	public function testMissingUsernameIsRejected(): void {
		$this->request(null, 'secr3t');
		$this->assertFalse(isValidUser());
	}

	// '1e3' == '1000' is true under PHP's loose comparison. hash_equals() compares bytes.
	public function testNumericStringPasswordsAreNotCoerced(): void {
		$this->request('numeric', '1e3');
		$this->assertFalse(isValidUser());

		$this->request('numeric', '1000');
		$this->assertTrue(isValidUser());
	}

	public function testPasswordComparisonIsCaseSensitive(): void {
		$this->request('dave', 'SECR3T');
		$this->assertFalse(isValidUser());
	}

	/////////////////////////////////////////////////////////////////////////////////////
	// Token authentication
	/////////////////////////////////////////////////////////////////////////////////////

	public function testSuccessfulPasswordAuthenticationMintsATokenFile(): void {
		$this->request('dave', 'secr3t');
		$this->assertTrue(isValidUser());

		$tokenFile = userTokenFilename();
		$this->assertFileExists($tokenFile);
		$this->assertSame('0600', substr(sprintf('%o', fileperms($tokenFile)), -4));
		$this->assertSame(64, strlen(trim(file_get_contents($tokenFile))), 'expected a hex SHA-256 HMAC');
	}

	public function testFailedPasswordAuthenticationDoesNotMintATokenFile(): void {
		$this->request('dave', 'wrong');
		$this->assertFalse(isValidUser());
		$this->assertFileDoesNotExist(userTokenFilename());
	}

	// The itms-services installer can't send an Authorization header, so it re-presents the
	// ?u= / ?t= pair from the manifest URL and authenticates on the token alone.
	public function testValidTokenAuthenticatesWithoutAPassword(): void {
		$requestUUID = randomUUID();

		$this->request('dave', 'secr3t', $requestUUID);
		$this->assertTrue(isValidUser());

		$this->request('dave', null, $requestUUID);
		$this->assertTrue(isValidUser());
	}

	public function testExpiredTokenIsRejectedAndDeleted(): void {
		$requestUUID = randomUUID();

		$this->request('dave', 'secr3t', $requestUUID);
		$this->assertTrue(isValidUser());

		$tokenFile = userTokenFilename();
		touch($tokenFile, time() - ($GLOBALS['simpleAuthTokenLifetime'] + 1));
		clearstatcache(true, $tokenFile);

		$this->request('dave', null, $requestUUID);
		$this->assertFalse(isValidUser());
		$this->assertFileDoesNotExist($tokenFile, 'an expired token should be removed on sight');
	}

	public function testTokenExactlyAtTheLifetimeBoundaryIsRejected(): void {
		$requestUUID = randomUUID();

		$this->request('dave', 'secr3t', $requestUUID);
		$this->assertTrue(isValidUser());

		$tokenFile = userTokenFilename();
		touch($tokenFile, time() - $GLOBALS['simpleAuthTokenLifetime']);
		clearstatcache(true, $tokenFile);

		$this->request('dave', null, $requestUUID);
		$this->assertFalse(isValidUser());
	}

	public function testUnknownRequestUUIDIsRejected(): void {
		$this->request('dave', 'secr3t', randomUUID());
		$this->assertTrue(isValidUser());

		$this->request('dave', null, randomUUID());
		$this->assertFalse(isValidUser());
	}

	// A token file with no valid HMAC - including the empty files older versions wrote.
	public function testTokenFileWithoutAValidSignatureIsRejected(): void {
		$requestUUID = randomUUID();

		$this->request('dave', 'secr3t', $requestUUID);
		$this->assertTrue(isValidUser());
		$tokenFile = userTokenFilename();

		file_put_contents($tokenFile, '');
		$this->request('dave', null, $requestUUID);
		$this->assertFalse(isValidUser(), 'an empty (pre-HMAC) token file should not authenticate');

		file_put_contents($tokenFile, str_repeat('a', 64));
		$this->request('dave', null, $requestUUID);
		$this->assertFalse(isValidUser(), 'a forged signature should not authenticate');
	}

	public function testTokenIsBoundToTheUsernameItWasIssuedFor(): void {
		$requestUUID = randomUUID();

		$this->request('dave', 'secr3t', $requestUUID);
		$this->assertTrue(isValidUser());
		$davesSignature = file_get_contents(userTokenFilename());

		$GLOBALS['users']['mallory'] = 'other';

		// Same UUID, different user: different filename, so nothing to authenticate against.
		$this->request('mallory', null, $requestUUID);
		$this->assertFalse(isValidUser());

		// And dave's signature copied into mallory's token file doesn't verify either.
		file_put_contents(userTokenFilename(), $davesSignature);
		$this->assertFalse(isValidUser());
	}

	// base64 can contain '/', which would place the token file outside the token directory.
	public function testTokenFilenameStaysInsideTheTokenDirectory(): void {
		$this->request('a~~~~', 'x');
		$tokenFile = userTokenFilename();

		$this->assertSame($this->tokenDirectory, dirname($tokenFile));
		$this->assertStringNotContainsString('/', basename($tokenFile));
	}

	public function testCleanTempDirIsQuietWhenTheDirectoryIsMissing(): void {
		$originalDirectory = $GLOBALS['simpleAuthTempDirectory'];
		$GLOBALS['simpleAuthTempDirectory'] = sys_get_temp_dir() . '/otago-does-not-exist-' . getmypid();

		try {
			cleanTempDir();
			$this->assertTrue(true, 'cleanTempDir() returned without emitting a warning');
		}
		finally {
			$GLOBALS['simpleAuthTempDirectory'] = $originalDirectory;
		}
	}

	public function testCleanTempDirRemovesOnlyExpiredTokenFiles(): void {
		$requestUUID = randomUUID();
		$this->request('dave', 'secr3t', $requestUUID);
		$this->assertTrue(isValidUser());
		$freshToken = userTokenFilename();

		$this->request('dave', 'secr3t', randomUUID());
		$this->assertTrue(isValidUser());
		$staleToken = userTokenFilename();
		touch($staleToken, time() - ($GLOBALS['simpleAuthTokenLifetime'] + 1));

		$unrelatedFile = $this->tokenDirectory . '/somebody-elses-file.txt';
		file_put_contents($unrelatedFile, 'leave me alone');

		cleanTempDir();

		$this->assertFileExists($freshToken);
		$this->assertFileDoesNotExist($staleToken);
		$this->assertFileExists($unrelatedFile);
	}

	/////////////////////////////////////////////////////////////////////////////////////
	// Query string parameters
	/////////////////////////////////////////////////////////////////////////////////////

	// These are what carry the credential into the itms-services install URL.
	public function testQueryStringAuthParametersCarryTheUsernameAndToken(): void {
		$requestUUID = randomUUID();
		$this->request('dave', 'secr3t', $requestUUID);

		$this->assertSame(array('u' => 'dave', 't' => $requestUUID), queryStringAuthParameters());
	}

	/////////////////////////////////////////////////////////////////////////////////////
	// randomUUID()
	/////////////////////////////////////////////////////////////////////////////////////

	public function testRandomUUIDHasTheExpectedShape(): void {
		for ($i = 0; $i < 100; ++$i) {
			$this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', randomUUID());
		}
	}

	// The UUID is the token, so collisions would be a security problem, not a cosmetic one.
	public function testRandomUUIDsDoNotRepeat(): void {
		$uuids = array();
		for ($i = 0; $i < 2000; ++$i) {
			$uuids[randomUUID()] = true;
		}
		$this->assertCount(2000, $uuids);
	}

	// #11: the bundled provider declares `never`, so PHP itself stops requestAuthentication()
	// from returning and letting a caller fall through to the protected content.
	public function testRequestAuthenticationNeverReturns(): void {
		$returnType = (new ReflectionFunction('requestAuthentication'))->getReturnType();
		$this->assertNotNull($returnType);
		$this->assertSame('never', (string)$returnType);
	}
}
