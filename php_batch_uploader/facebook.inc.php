<?php
# getAlbums - Get all current facebook albums
function getAlbums() {
	global $fbo;
	disp("Getting albums", 6);
	# Create the album.
	$albums = $fbo->api_client->photos_getAlbums("", "");
	return $albums;
}
# getAlbums - Get all current facebook albums
function createAlbums($name) {
	global $fbo;
	disp("Creating album: $name", 6);
	try {
		$album = $fbo->api_client->photos_createAlbum($name);
	}
	catch(Exception $e) {
		disp("Failed to create album $album", 1);
	}
	# Created albums do not have the following parameters.
	$albums[0]["can_upload"] = 1;
	$albums[0]["size"] = 0;
	$albums[0]["type"] = "normal";
	return $albums;
	return $albums;
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
	if ($a == 1) {
		printHelp("You must give your athorization code.\nVisit http://www.facebook.com/code_gen.php?v=1.0&api_key=187d16837396c6d5ecb4b48b7b8fa038 to get one for php_batch_uploader.\n\n");
		die();
	}
	try {
		$auth = $fbo->do_get_session($a);
		if (empty($auth)) throw new Exception('Empty Code.');
	}
	catch(Exception $e) {
		disp("Invalid auth code or could not authorize session.\nPlease check your auth code or generate a new one at: http://www.facebook.com/code_gen.php?v=1.0&api_key=187d16837396c6d5ecb4b48b7b8fa038", 1);
	}
	disp("Executed facebook authorization.", 6);
	// Store authorization code in authentication array
	$auth['code'] = $a;
	// Save to users home directory
	file_put_contents(getenv('HOME') . "/.facebook_auth", serialize($auth));
	disp("You are now authenticated! Re-run this application with a list of directories\nyou would like uploaded to facebook.", 1);
}
