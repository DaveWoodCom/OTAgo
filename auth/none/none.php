<?php
// OTAgo
// - OTA App Distribution System
// - https://github.com/DaveWoodCom/OTAgo
// - Copyright 2020 Dave Wood, Cerebral Gardens Inc.

// This effectively removes authentication, and allows anyone to install the app.
// You can swap in an alternate authentication system that connects to a database, or
// uses OAuth etc.

// You must include a function with this signature.
// Return true/false if the username/password is a valid combination.
function isValidUser($username, $password) {
	return true;
}

// You must include a function with this signature.
// Send a response to trigger an authentication request, then exit.
function requestAuthentication() {
	requestBasicAuthentication('Authentication Required');
    exit;
}
