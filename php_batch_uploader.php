#!/usr/bin/php
<?
$options = parseParameters();
/*
Thanks to fbcmd (http://www.cs.ubc.ca/~davet/fbcmd), reading through his code helped me get a jump start on this.
License: Do what ever the hell you want with it. If it mames you, deletes all your files or causes your wife to divorse you. It's not my fault.
*/
# Include required facebook include files.
$ver="0.1";
require_once ("facebook-platform/php/facebook.php");
require_once ("facebook-platform/php/facebook_desktop.php");
require_once ("facebook-platform/php/facebookapi_php5_restlib.php");
# Key and Secret. Used for the Facebook App.
$key = "187d16837396c6d5ecb4b48b7b8fa038";
$sec = "dc7a883649f0eac4f3caa8163b7e2a31";
# Generate URLs for informational purposes.
$url = "http://www.facebook.com/code_gen.php?v=1.0&api_key=$key";
$url2 = "http://www.facebook.com/authorize.php?v=1.0&api_key=$key&ext_perm=photo_upload";
# Create new facebook object.
if ($argc == 1) {
	# If Arument list
	echo "Version: php_batch_uploader $ver http://exstatic.org/php_batch_uploader/
Copyright: Copyright (C) 2009 Jedediah Frey\n\n";
	printHelp();
	die();
} elseif (array_key_exists("a", $options)) {
	if ($options["a"] == 1) {
		echo "You must give your athorization code.\nVisit $url to get one for this app.\n\n";
		printHelp();
		die();
	}
	// Create Facebook Object
	$fbo = new FacebookDesktop($key, $sec, true);
	try {
		$auth = $fbo->do_get_session($options["a"]);
		if (empty($auth)) throw new Exception('Empty Code.');
	}
	catch(Exception $e) {
		die("Invalid auth code or could not authorize session. Please check your auth code or generate a new one at: $url\n\n");
	}
	// Store authorization code in authentication array
	$auth['code'] = $options["a"];
	// Save to users home directory
	file_put_contents(getenv('HOME') . "/.facebook_auth", serialize($auth));
	die("You are now authenticated! Re-run this application with a list of directories you would like uploaded to facebook.\n\n");
}
// Check if authorization file exists.
if (!is_file(getenv('HOME') . "/.facebook_auth")) {
	echo ("User has not been authorized.\n\n");
	printHelp();
	die();
}
$auth = unserialize(file_get_contents(getenv('HOME') . "/.facebook_auth"));
# Try to login with auth programs
try {
	$fbo = new FacebookDesktop($key, $sec, true);
	$fbo->api_client->session_key = $auth['session_key'];
	$fbo->secret = $auth['secret'];
	$fbo->api_client->secret = $auth['secret'];
	$uid = $fbo->api_client->users_getLoggedInUser();
	if (empty($uid)) throw new Exception('Failed Auth.');
	// Check if program is authorized to upload pictures
	if (!($fbo->api_client->users_hasAppPermission('photo_upload', $uid))) {
		echo "Warning: App not authorized to immediately publish photos. View the album after uploading to Approve Uploaded Pictures.\n\nTo remove this warning and authorized direct uploads, visit $url2\n\n";
	}
}
catch(Exception $e) {
	die("Could not login. Try creating a new auth code at $url.\n\n");
}
# Set the upload mode.
$mode = (array_key_exists("m", $options)) ? $options["m"] : 1;
# Check if at least one folder was given
if (!array_key_exists(1, $options)) die("Must select at least one upload folder.\n\n");
# Generate a temp file where the thumbnails will be put before uploading.
$temp_file = tempnam("/tmp", "fbi_");
# For each input directory.
for ($i = 1;$i <= max(array_keys($options));$i++) {
	# Get full path of the directory w/ trailing slash.
	$dir = realpath($options[$i]);
	$root_dir = $dir;
	# Make sure that it is actually a directory
	if (!is_dir($dir)) {
		echo ($dir . " is not a directory. Skipping.");
		continue;
	}
	# Start the recursive upload.
	recursiveUpload($dir);
}
# Exit function.
die;
# Recursively upload photos
function recursiveUpload($dir) {
	global $fbo;
	# Scan the folder for directories and images
	$result = folder_scan($dir);
	# If the number of images per directory is greater than 1.
	if (count($result['images']) > 0) {
		// Get current albums associated with the folder
		$aids = getAlbumId(getAlbumBase($result['images'][0]));
		// Get pictures in all albums associated with the folder. In batch mode
		$fbo->api_client->begin_batch();
		for ($i = 0;$i < count($aids);$i++) {
			$pictures[$i] = & $fbo->api_client->photos_get("", $aids[$i], "");
		}
		$fbo->api_client->end_batch();
		foreach($result['images'] as $image) {
			# Check if the image already exists.
			if (imageExists($pictures, $image)) {
				echo "Image Exists:" . $image . " ... skipping\n";
			} else {
				$aids = uploadImage($aids, $image);
			}
		}
	}
	# For each directory. Recursively upload photos
	foreach($result['directories'] as $dir) {
		recursiveUpload($dir);
	}
}
# Help Function
function printHelp() {
	echo "Usage: php_batch_uploader [-a AUTH] [-m MODE] dir";
	echo "You need serious help\n";
}
# Upload the photo
function uploadImage($aids, $image) {
	global $fbo, $temp_file;
	$errors = 1;
	while (1) {
		try {
			# Make the thumbnail.
			makeThumb($image);
			# Upload the photo
			$caption = getCaption($image);
			$fbReturn = $fbo->api_client->photos_upload($temp_file, end($aids), $caption);
			echo "Uploaded: $image\n";
			break;
		} catch(Exception $e) {
			if ($e->getMessage() == "Album is full") {
				$dir_structure = explode('/', $image);
				$aids = getAlbumId(getAlbumBase($image));
				// Give the uploader 2 chances to generate thumbnail and upload picture
			} elseif ($errors >= 2) {
				# Display error and continue on
				echo ("Unexpected Error #$errors: " . $e->getMessage() . ", skipping $image\n");
			} else {
				echo ("Unexpected Error #$errors: " . $e->getMessage() . "\n");
				$errors++;
				# Calm. Cool. Collected. Occasionally happens when there are too many API requests.
				sleep(2);
			}
		}
	}
	echo "Uploaded: $image\n";
	return $aids;
}
# Get the album ID if the album exists, else create the album and return the ID.
function getAlbumId($albumName, $description = "") {
	global $fbo, $uid;
	// Get a list of user albums
	$albums = $fbo->api_client->photos_getAlbums($uid, NULL);
	$i = 0;
	$aids = array();
	$album = "";
	while ($i < count($albums)) {
		if ($albums[$i]['name'] == $albumName) {
			# Check if album is full.
			if ($albums[$i]['size'] == 200) {
				// If the album is full, generate a new name.
				echo "$albumName is full.\n";
				// Build $aid array of all aids associated with current folder.
				$aids[] = $albums[$i]['aid'];
				//
				$albumName = genAlbumName($albumName);
				// Reset search index
				$i = 0;
				continue;
			} else {
				// If it is not full, find out the aid.
				$aids[] = $albums[$i]['aid'];
				return $aids;
				break;
			}
		}
		$i++;
	}
	// If the album isn't found,  create it.
	$album = $fbo->api_client->photos_createAlbum($albumName, $description, "", "friends");
	$aids[] = $album['aid'];
	return $aids;
}
# Get the base name of an album based on the mode.
function getAlbumBase($image) {
	global $root_dir, $mode;
	if ($mode == 1) {
		$album_name = basename(dirname($image));
	} elseif ($mode == 2) {
		$album_name = basename($root_dir);
	} else {
		die("Invalid Mode");
	}
	return $album_name;
}
# Generate a new album name.
function genAlbumName($albumName) {
	// Determine if the album name 'My Album #2' etc is in use.
	if (preg_match('/([^#]+) #([\\d]+)/', $albumName, $regs)) {
		// If so, increment the number by 1.
		$newName = $regs[1] . " #" . (intval($regs[2]) + 1);
	} else {
		// Else, album name is #2.
		$newName = $albumName . " #2";
	}
	// Return the new name
	return $newName;
}
#
function getCaption($image) {
	global $root_dir, $mode;
	if ($mode == 1) {
		$caption = pathinfo($image, PATHINFO_FILENAME);
	} elseif ($mode == 2) {
		$glue = " - ";
		$dir_structure = explode('/', str_replace($root_dir . "/", "", $image));
		$caption = pathinfo(implode($glue, $dir_structure), PATHINFO_FILENAME);
	} else {
		die("Invalid Mode");
	}
	return trim($caption);
}
# Check if an image exists already in a list of images
function imageExists($pictures, $new_picture) {
	if (!is_array($pictures[0]) || empty($pictures[0])) {
		return 0;
	}	
	if (!is_array($pictures[0][0]) || empty($pictures[0][0])) {
		return 0;
	}
	foreach($pictures as $album_pictures) {
		if (!is_array($album_pictures) || empty($pictures)) {
			continue;
		}
		foreach($album_pictures as $picture) {
			# If the caption matches, which the uploader assigns to the filename minus extension.
			if (@$picture['caption'] == getCaption($new_picture)) return 1;
		}
	}
	return 0;
}
#
function makeThumb($file) {
	global $temp_file;
	# Img quality
	$quality = 80;
	# Resize to max facebook photo size, why is it this size? Who the hell knows.
	$resize = "604x604";
	# Input File
	$input = escapeshellarg($file);
	# Output file.
	$output = escapeshellarg($temp_file);
	# Create the temporary thumbnail.
	$command = "convert -format JPG -quality $quality -size $resize -resize $resize +profile '*' $input $output";
	exec($command);
	usleep(250);
}
function parseParameters($noopt = array()) {
	$result = array();
	$params = $GLOBALS['argv'];
	// could use getopt() here (since PHP 5.3.0), but it doesn't work relyingly
	reset($params);
	while (list($tmp, $p) = each($params)) {
		if ($p{0} == '-') {
			$pname = substr($p, 1);
			$value = true;
			if ($pname{0} == '-') {
				// long-opt (--<param>)
				$pname = substr($pname, 1);
				if (strpos($p, '=') !== false) {
					// value specified inline (--<param>=<value>)
					list($pname, $value) = explode('=', substr($p, 2), 2);
				}
			}
			// check if next parameter is a descriptor or a value
			$nextparm = current($params);
			if (!in_array($pname, $noopt) && $value === true && $nextparm !== false && $nextparm{0} != '-') list($tmp, $value) = each($params);
			$result[$pname] = $value;
		} else {
			// param doesn't belong to any option
			$result[] = $p;
		}
	}
	return $result;
}
function folder_scan($dir) {
	// Add trailing slash to directory
	$dir = substr($dir, -1) == "/" ? $dir : $dir . "/";
	# Check for invalid folders.
	if (!($files = @scandir($dir))) {
		# Move up one level.
		exit("Unknown directory error: " . $dir);
	}
	# Create arrays
	$result['directories'] = Array();
	$result['images'] = Array();
	# If there were files found.
	if (count($files) > 0) {
		foreach($files as $file) {
			# Skip all Unix hidden images.
			if (substr($file, 0, 1) == ".") {
				continue;
			}
			# Skip cache and bbclone directories.
			if (is_dir($dir . $file)) {
				$result['directories'][] = $dir . $file;
			}
			if (is_file($dir . $file) && hasExt($file, array('jpg', 'jpeg', 'png', 'gif'))) {
				$result['images'][] = $dir . $file;
			}
		}
	}
	sort($result['directories']);
	sort($result['images']);
	return $result;
}
function hasExt($file, $findExt) {
	if (!is_array($findExt)) {
		$findExt = array($findExt);
	}
	$ext = end(explode(".", $file));
	return in_array(strtolower($ext), $findExt);
}
?>