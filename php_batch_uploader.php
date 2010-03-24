#!/opt/local/bin/php
<?php
# Batch Branch
# Test
$converterPath = NULL; # To permanently change the image converter, set it here, otherwise use -c on the command line to set it.
$albumLimit= 200; # Limit the number of photos per album to this. Currently 200 in facebook.
$photoSize = "720x720"; # Resize to max facebook photo size. Currently 720x720 in facebook.
$photoQuality = 80; # JPEG Quality to resize with.
####
# Here Be Dragons.
####
error_reporting(E_ALL | !E_STRICT);

# Required uploader include files
require_once('php_batch_uploader/facebook.inc.php');
require_once('php_batch_uploader/functions.inc.php');
require_once('php_batch_uploader/help.inc.php');
require_once('php_batch_uploader/images.inc.php');
require_once('php_batch_uploader/upload.inc.php');

# Include required facebook include files.
if (is_file("facebook-platform/php/facebook.php")) {
	require_once ("facebook-platform/php/facebook.php");
	require_once ("facebook-platform/php/facebook_desktop.php");
	require_once ("facebook-platform/php/facebookapi_php5_restlib.php");
} else {
	disp("Facebook PHP Platform not found. Run getFacebookPHPlibrary.sh", 0);
}

$start_time = microtime(true);
# Parse input options and return an $options array.
$options = parseParameters();
# If no arguments are given.
if ($argc == 1) {
	# Display Help.
	printHelp("php_batch_uploader http://github.com/jedediahfrey/Facebook-PHP-Batch-Picture-Uploader
Copyright: Copyright (C) 2010 Jedediah Frey <facebook_batch@exstatic.org>\n\n");
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
# Get the image converter to use.
getConverter((array_key_exists("c", $options)) ? $options["c"] : $converterPath);
disp("Init...", 6);
// Create Facebook Object
# Key and Secret for php_batch_uploader.
$key = "187d16837396c6d5ecb4b48b7b8fa038";
$sec = "dc7a883649f0eac4f3caa8163b7e2a31";
$fbo = new FacebookDesktop($key, $sec, true);

$auth=NULL;
if (array_key_exists("a", $options)) {
	if ($options["a"] == 1) {
		printHelp("You must give your athorization code.\nVisit http://www.facebook.com/code_gen.php?v=1.0&api_key=187d16837396c6d5ecb4b48b7b8fa038 to get one for php_batch_uploader.\n\n");
		die();
	}
	try {
		$auth = $fbo->do_get_session($options["a"]);
		if (empty($auth)) throw new Exception('Empty Code.');
	} catch(Exception $e) {
		disp("Invalid auth code or could not authorize session.\nPlease check your auth code or generate a new one at: http://www.facebook.com/code_gen.php?v=1.0&api_key=187d16837396c6d5ecb4b48b7b8fa038", 1);
	}
	disp("Executed code authorization.", 6);
	// Store authorization code in authentication array
	$auth['code'] = $options["a"];
	// Save to users home directory
	file_put_contents(getenv('HOME') . "/.facebook_auth", serialize($auth));
	disp("You are now authenticated! Re-run this application with a list of directories\nyou would like uploaded to facebook.", 1);
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
	$fbo->api_client->session_key = $auth['session_key'];
	$fbo->secret = $auth['secret'];
	$fbo->api_client->secret = $auth['secret'];
	$uid = $fbo->api_client->users_getLoggedInUser();
	if (empty($uid)) throw new Exception('Failed Auth.');
	// Check if program is authorized to upload pictures
	if (!($fbo->api_client->users_hasAppPermission('photo_upload', $uid))) {
		disp("Warning: App not authorized to immediately publish photos. View the album after uploading to approve uploaded pictures.\n\nTo remove this warning and authorized direct uploads,\nvisit http://www.facebook.com/authorize.php?v=1.0&api_key=187d16837396c6d5ecb4b48b7b8fa038&ext_perm=photo_upload\n", 2);
	}
} catch(Exception $e) {
	disp("Could not login. Try creating a new auth code at http://www.facebook.com/code_gen.php?v=1.0&api_key=187d16837396c6d5ecb4b48b7b8fa038", 1);
}
disp("Facebook Authorization.", 6);

# Check if at least one folder was given
if (!array_key_exists(1, $options)) disp("Must select at least one upload folder.", 1);
# For each input directory.
for ($i = 1;$i <= max(array_keys($options));$i++) {
	# Get full path of the directory w/ trailing slash.
	$dir = realpath($options[$i]);
	disp("Real directory: $dir", 6);
	# Set the directory as the root directory so that everything is calculated relative to that.
	$root_dir = $dir;
	# Make sure that it is actually a directory and not a file.
	if (!is_dir($dir)) {
		disp("Warning: $dir is not a directory. Skipping.", 2);
		continue;
	}
	recursiveUpload($dir);
}
# Exit function.
die;
# recursiveUpload - Recursively upload photos
# Input: $dir - directory to start recursing from.
function recursiveUpload($dir) {
	global $fbo;
	# Start the recursive upload.
	disp("Recursively uploading: $dir", 6);
	# Scan the folder for directories and images
	$result = folderScan($dir);
	# If the number of images in directory is greater than 1.
	if (count($result['images']) > 0) {
		disp("Get Album ID", 6);
		uploadImages($result["images"]);
		// Get current albums associated with the image
		$aids = getAlbumIds(getAlbumBase($result['images'][0]));
		# If you have a large directory that you've already partially uploaded, you will hit the
		# API request limit and have to take a time out.
		$errors = 1;
		while (1) {
			try {
				# Get pictures in all albums associated with the folder. In batch mode
				$fbo->api_client->begin_batch();
				for ($i = 0;$i < count($aids);$i++) {
					$pictures[$i] = & $fbo->api_client->photos_get("", $aids[$i], "");
				}
				$fbo->api_client->end_batch();
				break;
			}
			catch(Exception $e) {
				if ($errors > 20) disp("Too many errors checking for photos.", 1);
				# Walk it off
				sleep(5);
				$errors++;
			}
		}
		disp("Building 'Seen Photos' Array.", 6);
		# For each image
		foreach($result['images'] as $image) {
			# Check if the image already exists.
			if (imageExists($pictures, $image)) {
				disp("Image Exists:" . $image . " ... skipping", 3);
			} else {
				$imagesToUpload[] = $image;
			}
		}
		$batchSize = 10;
		$c = count($imagesToUpload);
		for ($i = 0;$i < $c;$i+= $batchSize) {
			for ($j = 0;($j < $batchSize & ($j + $i) < $c);$j++) {
				$k = $i + $j;
				list($process[$j], $images[$j]) = makeThumb($imagesToUpload[$k]);
			}
			$batch = batchPrep($images);
			print_r($process);
			die;
			waitToProcess($process);
			uploadImages($process);
			die;
		}
		die;
		$aids = uploadImage($aids, $image);
	}
	# For each directory. Recursively upload photos
	foreach($result['directories'] as $dir) {
		recursiveUpload($dir);
	}
}
