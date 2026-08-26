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

	// An expired token file for some unrelated request, shaped exactly like a real one so the
	// sweep's glob pattern and its re-checks both accept it.
	private function writeStaleTokenFile(): string {
		$filename = sprintf(
			'%s/%s_%s_%s.%s',
			$this->tokenDirectory,
			$GLOBALS['simpleAuthTokenPrefix'],
			'c3RhbGU',
			randomUUID(),
			$GLOBALS['simpleAuthTokenExtension']
		);

		file_put_contents($filename, str_repeat('a', 64));
		touch($filename, time() - ($GLOBALS['simpleAuthTokenLifetime'] + 60));
		clearstatcache(true, $filename);

		return $filename;
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

	// #28: removing a user from $users must revoke their outstanding tokens immediately.
	// A token file is verified only by age and by an HMAC over username + UUID, neither of
	// which knows anything about $users - so the isset() guard in isValidUser() is what
	// makes the deletion take effect on the next request rather than up to a lifetime later.
	public function testValidTokenIsRejectedOnceTheUserIsRemoved(): void {
		$requestUUID = randomUUID();

		$this->request('dave', 'secr3t', $requestUUID);
		$this->assertTrue(isValidUser());

		$tokenFile = userTokenFilename();
		$this->assertFileExists($tokenFile);

		// The token is still on disk, still unexpired, and still carries a good signature.
		$this->request('dave', null, $requestUUID);
		$this->assertTrue(isValidUser(), 'precondition: the token authenticates while dave exists');
		$this->assertTrue(hasValidToken());

		// dave is deleted from configuration.php.
		unset($GLOBALS['users']['dave']);

		$this->request('dave', null, $requestUUID);
		$this->assertFalse(isValidUser(), 'a removed user must not authenticate on an outstanding token');

		// #32: and the rejected request removes the now-orphaned token file, so it doesn't sit
		// in the token directory until it ages out.
		$this->assertFileDoesNotExist($tokenFile);
		$this->assertFalse(hasValidToken());
	}

	// #32: cleanTempDir() removes a removed user's tokens however fresh they are, while
	// leaving a current user's fresh token alone.
	public function testCleanTempDirRemovesTokensOfRemovedUsers(): void {
		$this->request('dave', 'secr3t');
		$this->assertTrue(isValidUser());
		$daveToken = userTokenFilename();

		$this->request('numeric', '1000');
		$this->assertTrue(isValidUser());
		$numericToken = userTokenFilename();

		$this->assertFileExists($daveToken);
		$this->assertFileExists($numericToken);

		unset($GLOBALS['users']['numeric']);
		cleanTempDir();

		$this->assertFileExists($daveToken, 'a current user\'s fresh token must survive cleanup');
		$this->assertFileDoesNotExist($numericToken, 'a removed user\'s token must be purged even though it is fresh');
	}

	// A file whose username part isn't valid base64 can't belong to anyone and is purged too;
	// files that don't match the token shape at all are never touched.
	public function testCleanTempDirPurgesUndecodableOwnersButIgnoresForeignFiles(): void {
		$undecodable = $this->tokenDirectory . '/simpleAuthToken_***_' . randomUUID() . '.otago';
		$foreign = $this->tokenDirectory . '/notes.txt';
		file_put_contents($undecodable, 'x');
		file_put_contents($foreign, 'x');

		cleanTempDir();

		$this->assertFileDoesNotExist($undecodable);
		$this->assertFileExists($foreign);
		unlink($foreign);
	}

	public function testTokenOwnerRoundTrips(): void {
		foreach (array('dave', 'a~~~~', 'ünïcödé', 'with space', 'x') as $name) {
			$this->request($name, 'pw');
			$this->assertSame($name, simpleAuthTokenOwner(basename(userTokenFilename())));
		}
		$this->assertNull(simpleAuthTokenOwner('something-else.otago'));
		$this->assertNull(simpleAuthTokenOwner('simpleAuthToken__' . randomUUID() . '.otago'));
	}

	// And the password path is closed for a removed user too, even with the right password.
	public function testRemovedUserIsRejectedWithTheirOldPassword(): void {
		$this->request('dave', 'secr3t');
		$this->assertTrue(isValidUser());

		unset($GLOBALS['users']['dave']);

		$this->request('dave', 'secr3t');
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

	// #15: an expired token file belonging to some other request is reclaimed when somebody
	// authenticates with a password - that is now the only thing that triggers a sweep.
	public function testPasswordAuthenticationRemovesExpiredTokenFiles(): void {
		$staleToken = $this->writeStaleTokenFile();

		$this->request('dave', 'secr3t', randomUUID());
		$this->assertTrue(isValidUser());

		$this->assertFileDoesNotExist($staleToken, 'a password auth should sweep expired tokens');
		$this->assertFileExists(userTokenFilename(), 'the token just minted must survive the sweep');
	}

	// #15: token-only requests (manifest.php / ipa.php / apk.php, which is every request the
	// iOS installer makes) are the hot path and must not scan the token directory. Observable
	// proof: an expired sibling file is still there afterwards.
	public function testTokenAuthenticationDoesNotSweepTheTokenDirectory(): void {
		$requestUUID = randomUUID();
		$this->request('dave', 'secr3t', $requestUUID);
		$this->assertTrue(isValidUser());

		$staleToken = $this->writeStaleTokenFile();

		$this->request('dave', null, $requestUUID);
		$this->assertTrue(isValidUser(), 'precondition: the token still authenticates');

		$this->assertFileExists($staleToken, 'a token-path request should not scan the directory');
	}

	// A failed authentication shouldn't trigger a sweep either - only a real password auth does.
	public function testFailedAuthenticationDoesNotSweepTheTokenDirectory(): void {
		$staleToken = $this->writeStaleTokenFile();

		$this->request('dave', 'wrong', randomUUID());
		$this->assertFalse(isValidUser());

		$this->assertFileExists($staleToken);
	}

	// The sweep is scoped with glob('<prefix>_*.<extension>'), but every candidate is
	// re-checked before it is unlinked. Nothing outside that shape may be deleted, however old.
	public function testCleanTempDirLeavesNonTokenFilesAlone(): void {
		$expired = time() - ($GLOBALS['simpleAuthTokenLifetime'] + 60);

		$files = array(
			$this->tokenDirectory . '/somebody-elses-file.txt',
			$this->tokenDirectory . '/index.html',
			$this->tokenDirectory . '/.simpleAuthSecret',
			// Right extension, wrong prefix.
			$this->tokenDirectory . '/notOurPrefix_abc.otago',
			// Right prefix, wrong extension.
			$this->tokenDirectory . '/simpleAuthToken_abc.txt',
			// Our prefix but not followed by the '_' separator.
			$this->tokenDirectory . '/simpleAuthTokenish.otago',
		);

		foreach ($files as $file) {
			file_put_contents($file, 'leave me alone');
			touch($file, $expired);
		}
		clearstatcache();

		cleanTempDir();

		foreach ($files as $file) {
			$this->assertFileExists($file, basename($file) . ' should not have been deleted');
		}
	}

	// glob() returns false rather than an array when it can't read the directory. The old
	// dir()/->read() loop went fatal in that situation; this must simply be a no-op.
	public function testCleanTempDirIsQuietWhenTheDirectoryIsUnreadable(): void {
		if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
			$this->markTestSkipped('running as root: permissions are not enforced');
		}

		$unreadable = $this->tokenDirectory . '/unreadable';
		mkdir($unreadable, 0700, true);
		file_put_contents($unreadable . '/simpleAuthToken_abc.otago', 'x');
		chmod($unreadable, 0000);
		clearstatcache();

		if (is_readable($unreadable)) {
			chmod($unreadable, 0700);
			$this->markTestSkipped('the filesystem does not enforce directory permissions here');
		}

		$originalDirectory = $GLOBALS['simpleAuthTempDirectory'];
		$GLOBALS['simpleAuthTempDirectory'] = $unreadable;

		try {
			cleanTempDir();
			$this->assertTrue(true, 'cleanTempDir() returned without a fatal or a warning');
		}
		finally {
			$GLOBALS['simpleAuthTempDirectory'] = $originalDirectory;
			chmod($unreadable, 0700);
			@unlink($unreadable . '/simpleAuthToken_abc.otago');
			@rmdir($unreadable);
		}
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

	// #14: random_bytes() is the sole source, so every hex position carries real entropy.
	// The old implementation fell through to no CSPRNG at all if none of the three functions
	// existed, leaving $groups empty - which either raised an ArgumentCountError or (on older
	// PHP) produced a constant UUID, i.e. one shared token filename for every user.
	public function testRandomUUIDIsRandomInEveryPosition(): void {
		$seen = array_fill(0, 36, array());
		for ($i = 0; $i < 200; ++$i) {
			$uuid = randomUUID();
			$this->assertSame(36, strlen($uuid));
			for ($position = 0; $position < 36; ++$position) {
				$seen[$position][$uuid[$position]] = true;
			}
		}

		$separators = array(8, 13, 18, 23);
		for ($position = 0; $position < 36; ++$position) {
			if (in_array($position, $separators, true)) {
				$this->assertSame(array('-' => true), $seen[$position]);
				continue;
			}
			$this->assertGreaterThan(1, count($seen[$position]), 'position ' . $position . ' never varied');
		}
	}

	// #14: no silent degradation to a weaker (or absent) random source.
	public function testRandomUUIDHasNoNonCSPRNGFallback(): void {
		$function = new ReflectionFunction('randomUUID');
		$source = implode('', array_slice(
			file($function->getFileName()),
			$function->getStartLine() - 1,
			$function->getEndLine() - $function->getStartLine() + 1
		));

		$this->assertStringContainsString('random_bytes(', $source);
		$this->assertStringNotContainsString('mcrypt_create_iv', $source);
		$this->assertStringNotContainsString('openssl_random_pseudo_bytes', $source);
		$this->assertStringContainsString('RuntimeException', $source, 'a CSPRNG failure must be raised, not swallowed');
	}

	// #11: the bundled provider declares `never`, so PHP itself stops requestAuthentication()
	// from returning and letting a caller fall through to the protected content.
	public function testRequestAuthenticationNeverReturns(): void {
		$returnType = (new ReflectionFunction('requestAuthentication'))->getReturnType();
		$this->assertNotNull($returnType);
		$this->assertSame('never', (string)$returnType);
	}
}
