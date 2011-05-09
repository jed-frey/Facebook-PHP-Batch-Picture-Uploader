<?php
# getAlbums - Get all current facebook albums
function getAlbums() {
	global $fbo, $uid;
	disp("Getting albums", 5);
	# Create the album.
	#$albums = $fbo->api_client->photos_getAlbums($fbo->api_client->users_getLoggedInUser(), "");
	$albums = $fbo->api_client->photos_getAlbums($uid, "");
	return $albums;
}

function showAuth() {
    global $fbcmdPrefs, $urlAccess, $urlAuth;
    print "\n";
    print "php_batch_uploader needs to be authorized to access your Facebook account.\n";
    print "\n";
    print "Step 1: Allow basic (initial) access to your account via this url:\n\n";
    print "{$urlAccess}\n";
    print "\n";
    print "Step 2: Generate an offline authorization code at this url:\n\n";
    print "{$urlAuth}\n";
    print "\n";
    print "obtain your authorization code (XXXXXX) and then execute: php_batch_uploader.php -a XXXXXX\n\n";
}

# getAlbums - Get all current facebook albums
function createAlbum($name) {
	global $fbo, $uid, $description, $location, $privacy;
	disp("Creating album: $name", 5);
	try {
		$album = $fbo->api_client->photos_createAlbum($name,$description,$location,$privacy,$uid);
	}
	catch(Exception $e) {
		disp("Failed to create album $album", 1);
	}
	# Created albums do not have the following parameters.
	$album["can_upload"] = 1;
	$album["size"] = 0;
	$album["type"] = "normal";
	return $album;
}
# getImages - Get all current images album(s)
function getImages($aids) {
	if (!is_array($aids)) {
		$aids = array($aids);
	}
	foreach($aids as $aid) {
	}
}
function getFacebookAuthorization($a = 1) {
	global $fbo, $key, $urlAccess, $urlAuth;
	if ($a == 1) {
		printHelp("You must give your athorization code.\nVisit http://www.facebook.com/code_gen.php?v=1.0&api_key=$key to get one for php_batch_uploader.\n\n");
		die();
	}
	try {
		$auth = $fbo->do_get_session($a);
		if (empty($auth)) throw new Exception('Empty Code.');
		$fbReturn = $fbo->api_client->users_getInfo($fbo->api_client->users_getLoggedInUser(), array('name'));
		$name=$fbReturn[0]["name"];
	}
	catch(Exception $e) {
		disp("Invalid auth code or could not authorize session.\nPlease check your auth code or generate a new one at: {$urlAuth}\n\nIf you removed php_batch_uploader from your privacy settings, you will need to reauthoize it at {$urlAccess}", 1);
	}
	disp("Executed facebook authorization.", 5);
	// Store authorization code in authentication array
	$auth['code'] = $a;
	// Save to users home directory
	file_put_contents(getenv('HOME') . "/.facebook_auth", serialize($auth));
	disp("You are now authenticated {$name}! Re-run php_batch_uploader with a list of directories you would like uploaded to facebook.", 1);
}
