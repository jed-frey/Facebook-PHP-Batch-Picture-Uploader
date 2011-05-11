#!/usr/bin/env php
<?php
////////
// Here Be Dragons. All configuration is in config.inc.php
////////
error_reporting(E_ALL | !E_STRICT);
// Required uploader include files
require_once ('config.inc.php');
require_once ('includes/facebook.inc.php');
require_once ('includes/functions.inc.php');
require_once ('includes/help.inc.php');
require_once ('includes/images.inc.php');
require_once ('includes/upload.inc.php');
// Include required facebook include files.
include_once ("facebook/facebook.php");
include_once ("facebook/facebook_desktop.php");

$start_time = microtime(true); // Start timer
$options = parseParameters(); // Parse input options and return an $options array.
if ($argv[0]!=$options[0]) { // For some reason parseParameters does weird things 
	$options[1]=$options[0]; // depending on the order of calls and sometimes puts the directory into [0]
}
//
if ($argc == 1) {
// Check if authorization file exists.
	if (!is_file(getenv('HOME') . "/.facebook_auth")) {
		echo<<<EOF
It looks like this is the first time you have run php_batch_uploader on this machine.

If you have not already authorized php_batch_uploader access to your facebook account you must do that first at:
{$urlAccess}

You must then generate a one-time code at 
{$urlAuth} 

And run php_batch_uploader with the '-a' switch using the generated code.


EOF;
	} else {
	// Display Help.
	printHelp("php_batch_uploader http://github.com/jedediahfrey/Facebook-PHP-Batch-Picture-Uploader
Copyright: Copyright (C) 2011 Jedediah Frey <php_batch_uploader@exstatic.org>\n\n");
	die();
	}
} elseif (array_key_exists("m", $options) && $options['m'] == "h") {
	// If the user asks for mode help.
	printModeHelp();
	die();
}
//// Set defaults
// Set verbosity - Default 2.
$verbosity = array_key_exists("v", $options) ? intval($options["v"]) : 2;
// Set the upload mode - Default 1.
$mode = (array_key_exists("m", $options)) ? $options["m"] : 1;
$albumName   = (array_key_exists("n", $options)) ? $options["n"] : NULL;
$location    = (array_key_exists("l", $options)) ? $options["l"] : NULL;
$description = (array_key_exists("d", $options)) ? $options["d"] : NULL;
$no_recurse  = (array_key_exists("nr", $options)) ? $options["nr"] : FALSE;
$nohash  = (array_key_exists("nohash", $options)) ? TRUE : FALSE;
$glue        = (array_key_exists("g", $options)) ? $options["g"] : " - "; // Glue to separate file names in mode 2.
// Set the privacy settings for newly created albums.
if (array_key_exists("p", $options)) {
	switch ($options["p"]) {
			case "friends":
			case "friends-of-friends":
			case "networks":
			case "everyone":
			$privacy=$options["p"];
			break;
		default:
			// Just use the default.
			disp("Unknown privacy option: ".$options["p"],2);
	}
}
// If defaultSD is set to true in the config. Determine
if ($defaultSD==true) {
	$photoSize = (array_key_exists("hd", $options)) ? $photoSizeHD : $photoSizeSD;
} else {
	$photoSize = (array_key_exists("sd", $options)) ? $photoSizeSD : $photoSizeHD;
}
// Get the mode.
if ($mode != 2 && $mode != 1) disp("Invalid Mode: $mode", 1);
// Get the image converter to use.
getConverter((array_key_exists("c", $options)) ? $options["c"] : $converterPath);
disp("Init...", 5);
// Create Facebook Object
$fbo = new FacebookDesktop($key, $sec, true);
$auth = NULL;
if (array_key_exists("a", $options)) {
	getFacebookAuthorization($options["a"]);
}
// Get saved authorization data.
disp("Loading session data. ", 5);
$auth = is_array($auth) ? $auth : unserialize(file_get_contents(getenv('HOME') . "/.facebook_auth"));
// Try to login with auth programs
try {
	disp("Checking Facebook Authorization.", 5);
	$fbo->api_client->session_key = $auth['session_key'];
	$fbo->secret = $auth['secret'];
	$fbo->api_client->secret = $auth['secret'];
	$uid = $fbo->api_client->users_getLoggedInUser();
	if (empty($uid)) throw new Exception('Failed Auth.');
	// Check if program is authorized to upload pictures
	if (!($fbo->api_client->users_hasAppPermission('photo_upload', $uid))) {
		disp("Warning: App not authorized to immediately publish photos. View the album after uploading to approve uploaded pictures.\n\nTo remove this warning and authorized direct uploads,\nvisit $urlUpload\n", 2);
	}
} catch(Exception $e) {
	disp("Invalid auth code or could not authorize session.\nPlease check your auth code or generate a new one at:\n\t{$urlAuth}\n\nIf you removed php_batch_uploader from your privacy settings, you will need to reauthoize it at\n\t {$urlAccess}", 1);
}
// If the user opts to upload for another page, use that uid instead.
$uid = (array_key_exists("u", $options)) ? $options["u"] : $uid;
// Check if at least one folder was given
if (!array_key_exists(1, $options)) disp("Must select at least one folder to upload.", 1);
// For each input directory.
for ($i = 1;$i <= max(array_keys($options));$i++) {
	// Get full path of the directory w/ trailing slash.
	$dir = realpath($options[$i]);
	// Make sure that it is actually a directory and not a file.
	if (!is_dir($dir)) {
		disp("Warning: \"$options[$i]\" is not a directory. Skipping.", 2);
		continue;
	}
	// Set the directory as the root directory so that everything is calculated relative to that.
	$root_dir = $dir;
	recursiveUpload($dir);
}
// Exit function.
die;
// recursiveUpload - Recursively upload photos
// Input: $dir - directory to start recursing from.
function recursiveUpload($dir) {
	global $fbo,$no_recurse;
	// Start the recursive upload.
	disp("Recursively uploading: $dir", 5);
	// Scan the folder for directories and images
	$result = folderScan($dir);
	// If the number of images in directory is greater than 1.
	if (count($result['images']) > 0) {
		// Get album base name.
		$albumBase = getAlbumBase($result['images'][0]);
		// Get current albums associated with the base name.
		$imageAlbums = getImageAlbums($albumBase);
		uploadImages($result["images"], $imageAlbums);
	}
	// For each directory. Recursively upload photos
	if ($no_recurse) disp("No Recursion, stopping.", 0);
	foreach($result['directories'] as $dir) {
		recursiveUpload($dir);
	}
}