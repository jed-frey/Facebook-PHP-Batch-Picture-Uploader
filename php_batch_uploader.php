#!/usr/bin/env php
<?php
####
# Here Be Dragons.
####
error_reporting(E_ALL | !E_STRICT);
# Required uploader include files
require_once ('config.inc.php');
require_once ('includes/facebook.inc.php');
require_once ('includes/functions.inc.php');
require_once ('includes/help.inc.php');
require_once ('includes/images.inc.php');
require_once ('includes/upload.inc.php');
# Include required facebook include files.
if (is_dir(dirname($argv[0])."/facebook-platform")) {
	include_once ("facebook-platform/php/facebook.php");
	include_once ("facebook-platform/php/facebook_desktop.php");
	include_once ("facebook-platform/php/facebookapi_php5_restlib.php");
} else {
	echo("Facebook PHP Platform not found.... attempting to download...\n");
	echo shell_exec('./getFacebookPHPlibrary.sh')."\n";
	echo ('If no errors were reported, try to run '.basename($argv[0]). " again.")."\n";
	die;
}
$start_time = microtime(true); # Start timer
$options = parseParameters(); # Parse input options and return an $options array.
# If no arguments are given.
# Key and Secret for php_batch_uploader.
$key = "7c984a9708b1a9f0eb0880017560e840";
$sec = "cfcec008079a87aace666875c0fcf3d9";
#
if ($argc == 1) {
	# Display Help.
	printHelp("php_batch_uploader http://github.com/jedediahfrey/Facebook-PHP-Batch-Picture-Uploader
Copyright: Copyright (C) 2011 Jedediah Frey <php_batch_uploader@exstatic.org>\n\n");
	die();
} elseif (array_key_exists("m", $options) && $options['m'] == "h") {
	# If the user asks for mode help.
	printModeHelp();
	die();
}
## Set defaults
# Set verbosity - Default 2.
$verbosity = array_key_exists("v", $options) ? intval($options["v"]) : 2;
# Set the upload mode - Default 1.
$mode = (array_key_exists("m", $options)) ? $options["m"] : 1;
$albumName = (array_key_exists("n", $options)) ? $options["n"] : NULL;
if ($mode != 2 && $mode != 1) disp("Invalid Mode: $mode", 1);
# Get the image converter to use.
getConverter((array_key_exists("c", $options)) ? $options["c"] : $converterPath);
disp("Init...", 6);
// Create Facebook Object
$fbo = new FacebookDesktop($key, $sec, true);
$auth = NULL;
if (array_key_exists("a", $options)) {
	getFacebookAuthorization($options["a"]);
}
# Check if authorization file exists.
if (!is_file(getenv('HOME') . "/.facebook_auth")) {
	printHelp("User has not been authorized.\n\n");
	die();
}
# Get saved authorization data.
disp("Loading session data. ", 6);
$auth = is_array($auth) ? $auth : unserialize(file_get_contents(getenv('HOME') . "/.facebook_auth"));
# Try to login with auth programs
try {
	disp("Checking Facebook Authorization.", 6);
	$fbo->api_client->session_key = $auth['session_key'];
	$fbo->secret = $auth['secret'];
	$fbo->api_client->secret = $auth['secret'];
	$uid = $fbo->api_client->users_getLoggedInUser();
	if (empty($uid)) throw new Exception('Failed Auth.');
	// Check if program is authorized to upload pictures
	if (!($fbo->api_client->users_hasAppPermission('photo_upload', $uid))) {
		disp("Warning: App not authorized to immediately publish photos. View the album after uploading to approve uploaded pictures.\n\nTo remove this warning and authorized direct uploads,\nvisit http://www.facebook.com/authorize.php?v=1.0&api_key=$key&ext_perm=photo_upload\n", 2);
	}
}
catch(Exception $e) {
	disp("Could not login. Try creating a new auth code at http://www.facebook.com/code_gen.php?v=1.0&api_key=$key", 1);
}
# Check if at least one folder was given
if (!array_key_exists(1, $options)) disp("Must select at least one folder to upload.", 1);
# For each input directory.
for ($i = 1;$i <= max(array_keys($options));$i++) {
	# Get full path of the directory w/ trailing slash.
	$dir = realpath($options[$i]);
	# Make sure that it is actually a directory and not a file.
	if (!is_dir($dir)) {
		disp("Warning: $dir is not a directory. Skipping.", 2);
		continue;
	}
	# Set the directory as the root directory so that everything is calculated relative to that.
	$root_dir = $dir;
	recursiveUpload($dir);
}
# Exit function.
die;
# recursiveUpload - Recursively upload photos
# Input: $dir - directory to start recursing from.
function recursiveUpload($dir) {
	global $fbo,$nr;
	# Start the recursive upload.
	disp("Recursively uploading: $dir", 6);
	# Scan the folder for directories and images
	$result = folderScan($dir);
	# If the number of images in directory is greater than 1.
	if (count($result['images']) > 0) {
		# Get album base name.
		$albumBase = getAlbumBase($result['images'][0]);
		# Get current albums associated with the base name.
		$imageAlbums = getImageAlbums($albumBase);
		uploadImages($result["images"], $imageAlbums);
	}
	# For each directory. Recursively upload photos
	if ($nr) return;
	foreach($result['directories'] as $dir) {
		recursiveUpload($dir);
	}
}