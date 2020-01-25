<?php
// OTAgo
// - OTA App Distribution System
// - https://github.com/DaveWoodCom/OTAgo
// - Copyright 2020 Dave Wood, Cerebral Gardens Inc.

// This is a simple authentication system, using an array of valid username/password pairs.
// You can swap in an alternate authentication system that connects to a database, or
// uses OAuth etc.

if (!isset($users)) {
	$users = array();
}

// You must include a function with this signature.
// Return true/false if the username/password is a valid combination.
function isValidUser($username, $password) {
	global $users;
	
	if (empty($username) || empty($password)) {
		return false;
	}

	return $users[$username] == $password;
}

// You must include a function with this signature.
// Send a response to trigger an authentication request, then exit.
function requestAuthentication() {
	requestBasicAuthentication('Authentication Required');
    exit;
}
