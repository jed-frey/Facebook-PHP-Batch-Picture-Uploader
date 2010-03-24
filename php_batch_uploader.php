#!/opt/local/bin/php
<?php
# Batch Branch
# Test
$converterPath = NULL; # To permanently change the image converter, set it here, otherwise use -c on the command line to set it.
####
# Here Be Dragons.
####
error_reporting(E_ALL | !E_STRICT);
$start_time = microtime(true);
# Include required facebook include files.
if (is_file("facebook-platform/php/facebook.php")) {
	require_once ("facebook-platform/php/facebook.php");
	require_once ("facebook-platform/php/facebook_desktop.php");
	require_once ("facebook-platform/php/facebookapi_php5_restlib.php");
} else {
	disp("Facebook PHP Platform not found. Run getFacebookPHPlibrary.sh", 0);
}
# If no arguments are given.
if ($argc == 1) {
	# Display information
	echo "php_batch_uploader http://github.com/jedediahfrey/Facebook-PHP-Batch-Picture-Uploader
Copyright: Copyright (C) 2010 Jedediah Frey <facebook_batch@exstatic.org>\n\n";
	# Display Help.
	printHelp();
	die();
} elseif (array_key_exists("m", $options) && $options['m'] == "h") {
	# If the user asks for mode help.
	printModeHelp();
	die();
}
# Parse input options and return an $options array.
$options = parseParameters();
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
if (array_key_exists("a", $options)) {
	if ($options["a"] == 1) {
		echo "You must give your athorization code.\nVisit http://www.facebook.com/code_gen.php?v=1.0&api_key=187d16837396c6d5ecb4b48b7b8fa038 to get one for php_batch_uploader.\n\n";
		printHelp();
		die();
	}
	try {
		$auth = $fbo->do_get_session($options["a"]);
		if (empty($auth)) throw new Exception('Empty Code.');
	}
	catch(Exception $e) {
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
	echo ("User has not been authorized.\n\n");
	printHelp();
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
}
catch(Exception $e) {
	disp("Could not login. Try creating a new auth code at http://www.facebook.com/code_gen.php?v=1.0&api_key=187d16837396c6d5ecb4b48b7b8fa038", 2);
}
disp("Facebook Authorization.", 6);
}
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
# getAlbums - Get all current facebook albums
function getAlbums() {
	global $albums;
	$albums = $facebook->api_client->photos_getAlbums();
}
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
		// Get current albums associated with the image
		$aids = getAlbumId(getAlbumBase($result['images'][0]));
		disp("Get Album ID", 6);
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
function batchPrep($images) {
}
# Wait for all thumbnail processing threads to finish.
function waitToProcess($procs) {
	do {
		# Set the count of running processes to 0.
		$running = 0;
		# For each process
		foreach($procs as $proc) {
			# Get process status
			$r = proc_get_status($proc);
			# Increment the number of running threads.
			if ($r["running"]) $running++;
		}
	}
	while ($running != 0); # While the number running process isn't 0, keep checking.
	
}
# Help Function
function printHelp() {
	$help = <<<EOF
Usage:  php_batch_uploader.php [-m MODE] [-v VERBOSITY] dirs
        php_batch_uploader.php -a AUTH
	   
  -a    Facebook Authentication Code. Must be used the first time the script is run.
            Visit http://www.facebook.com/code_gen.php?v=1.0&api_key=187d16837396c6d5ecb4b48b7b8fa038
            to authorize php_batch_uploader and generate code.

            To authorize direct uploading of pictures, you have to authorize php_batch_uploader direct upload access.
			This can be granted here:
            http://www.facebook.com/authorize.php?v=1.0&api_key=187d16837396c6d5ecb4b48b7b8fa038&ext_perm=photo_upload
  -m    Upload Mode.
            1: Upload each directory & subdirectory as album name. Caption based on image name.[Default]
            2: Use the top level directory input as album name. Create caption based on subdirectories & image name
            h: Display detailed information about how each of the modes works, with examples
  -v    Script verbosity.
            0: Display nothing, not even warnings or errors
            1: Display only errors which cause the script to exit.
            2: Display errors and warnings. [Default]
            3: Display everything. (When file is uploaded, when a file is skipped, errors & warnings)
            4: Display everything w/time stamp when event occured since script start.
			5: Display everything w/time stamp since last message.
			6: Debug. Display debug w/time stamp since last message.
			
  dirs  Directories passed to script. These are the folders that are uploaded to facebook.


EOF;
	echo $help;
}
function printModeHelp() {
	$help = <<<EOF
Modes Explained:
    Each of the modes will recursively upload all images and folders in a given directory. 
    The only way in which they differ is how the files are captioned and the album names that they are put into.
    
    Mode 1 (-m 1):
        Uses the directory that the file is in as the Album Name. 
        The image is then captioned as the image name, minus extension.
    Mode 2 (-m 2):
        Uses the directory(s) passed to the script as the as Album Name. 
        The image is then captioned with the relative path to the image and the image, minus extension.

    Example, for the sample folder structure below:
        1) ~/pictures/2008/Road Trips/Road Trip#.jpg, etc
        2) ~/pictures/2008/Road Trips/Vegas/Vegas #.jpg, etc
        3) ~/pictures/2008/Road Trips/Grand Canyon/GC # .jpg,etc 
        4) ~/pictures/2009/New Years Eve/Down Town/Fireworks/FireWorks #.jpg, etc
        5) ~/pictures/2009/Road Trips/Road Trip #.jpg, etc
 
    Called with "[php] php_batch_uploader ~/pictures/2008 ~/pictures/2009"
    Mode 1:
        1) Album "Road Trips" is created and all images are uploaded with caption "Road Trip #"
        2) Album "Vegas" is created and and all images is uploaded with caption "Vegas #"
        3) Album "Grand Canyon" is created and and all images is uploaded with caption "GC #"
        4) Album "Fireworks" is created and and all images is uploaded with caption "FireWorks #"
        5) Because album "Road Trips" already exists, all images in this folder will be uploaded to the existing Album.
        
    Mode 2:
        ~/pictures/2008 & ~/pictures/2009 are the input directories, "2008" and "2009" will be the Album Names, respectively
        Since "2008" & "2009" is the root directory, images in sub folders will be uploaded into these two albums.
        1) Album 2008 is created, images will be captioned with "Road Trips - Road Trip #"
        2) Album 2008 is used, images will be captioned with "Road Trips - Vegas - Vegas #"
        3) Album 2008 is used, images will be captioned with "Road Trips - Grand Canyon - GC #"
        4) Album 2009 is created, images will be captioned with "New Years Eve - Down Town - Fireworks - FireWorks #"
        5) Album 2009 is used, images will be captioned with "Road Trips - Road Trip#
        
    Caveat:
        The captions are used to determine unique pictures. In the above example, in mode 1, 
        if you had a 2008/Road Trips/1.jpg & 2009/Roadtrips/1.jpg, the second image will be skipped 
        because the script thinks it's the same picture.
        
    Beware of how your shell script interpets inputs for mode 2
        For example, in bash,  php_batch_uploader ~/pictures/ & php_batch_uploader ~/pictures/* are not the same.
        In the first,  1 argument (~/pictures/) is passed to the script, so the Album Name in Mode 2 will be "pictures"
        In the second, 2 arguments (~/pictures/2008,~/pictures/2009) are passed and albums in the example will be created.
        
        In Mode 1, there won't be any difference (unless you have pictures in the root folder ~/pictures, then an Album "pictures" will be created)
        
    When the facebook limit of 200 photos is reached. The album name is suffixed with a number sign and a number starting at 2.
        "Spring Break" becomes "Spring Break #2" then "Spring Break #3", so on and so forth.


EOF;
	echo $help;
}
function uploadImages($images) {
}
# Upload the photo
function uploadImage($aids, $image) {
	global $fbo, $temp_file;
	$errors = 1;
	while (1) {
		try {
			# Make the thumbnail.
			# Get the album caption
			$caption = getCaption($image);
			# Upload the photo
			#$fbReturn = $fbo->api_client->photos_upload($temp_file, end($aids), $caption);
			# If the image was uploaded successfully
			disp("Uploaded: $image", 2);
			# Break the while loop
			break;
			# Catch exception
			
		}
		catch(Exception $e) {
			if ($e->getMessage() == "Album is full") {
				# If the album is full, get a new album name & return album ids
				$aids = getAlbumId(getAlbumBase($image));
				# Give the uploader 2 chances to generate thumbnail and upload picture
				
			} elseif ($errors >= 2) {
				# Display error and continue on
				disp("Unexpected Error #$errors: " . $e->getMessage() . ", skipping $image", 1);
			} else {
				disp("Unexpected Error #$errors: " . $e->getMessage(), 2);
				$errors++;
				# Occasionally happens when there are too many API requests, slow it down.
				sleep(2);
			}
		}
	}
	# Return album IDs
	return $aids;
}
# Get the album ID if the album exists, else create the album and return the ID.
function getAlbumId($albumName, $description = "") {
	global $fbo, $uid;
	// Get a list of user albums
	$albums = $fbo->api_client->photos_getAlbums($uid, NULL);
	$i = 0;
	# Create Album IDs array
	$aids = array();
	# For each of the albums
	while ($i < count($albums)) {
		# If the album name is the same as the current increment album
		if ($albums[$i]['name'] == $albumName) {
			# Check if album is full.
			if ($albums[$i]['size'] >= 200) { # Limit of 200 photos per album.
				// If the album is full, generate a new name.
				disp("$albumName is full", 2);
				// Build $aid array of all aids associated with current Album Name.
				$aids[] = $albums[$i]['aid'];
				// Generate a new album name based on the current name
				$albumName = genAlbumName($albumName);
				// Reset search index, start searching from the beginning of album list with the new album
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
	$album = $fbo->api_client->photos_createAlbum($albumName, $description, "", "SELF");
	disp("Create Album: $albumName ($description)", 5);
	$aids[] = $album['aid'];
	return $aids;
}
# getAlbumBase - Get the base name of an album based on the mode.
# Input: $image - Image to get the album base for.
function getAlbumBase($image) {
	global $root_dir, $mode;
	if ($mode == 1) {
		# Mode 1: Album name = folder image is in
		$album_name = basename(dirname($image));
	} elseif ($mode == 2) {
		# Moded 2: Album name = root folder
		$album_name = basename($root_dir);
	} else {
		disp("Invalid Mode: $mode", 1);
	}
	return $album_name;
}
# genAlbumName - Generate a new album name.
# Input: $baseAlbumName - base name of album.
# Output: return the newName.
function genAlbumName($baseAlbumName) {
	// Determine if the album name 'My Album #2' etc is in use.
	if (preg_match('/([^#]+) #([\\d]+)/', $baseAlbumName, $regs)) {
		// If so, increment the number by 1.
		$newName = $regs[1] . " #" . (intval($regs[2]) + 1);
	} else {
		// Else, album name is #2.
		$newName = $baseAlbumName . " #2";
	}
	disp("Generated new album name $newName from $baseAlbumName", 6);
	// Return the new name
	return $newName;
}
# getCaption - Get the caption for the image based on the mode.
# Input: $image - Image file to generate caption for.
# Output: Caption of image file.
function getCaption($image) {
	global $root_dir, $mode;
	$root_dir = substr($root_dir, -1) == "/" ? $root_dir : $root_dir . "/";
	if ($mode == 1) {
		# In Mode 1 (where each (sub)directory gets its own album, just use the file name
		$caption = pathinfo($image, PATHINFO_FILENAME);
	} elseif ($mode == 2) {
		# Define the glue for the caption.
		$glue = " - ";
		# Replace the root directory with nothing.
		$dir_structure = explode(DIRECTORY_SEPARATOR, str_replace($root_dir, "", $image));
		# Generate a caption based on the folder's relative
		$caption = pathinfo(implode($glue, $dir_structure), PATHINFO_FILENAME);
	} else {
		disp("Invalid Mode", 1);
	}
	# Trim off excess white spaces.
	$caption = trim($caption);
	disp("Got Caption: $caption for $image", 6);
	return $caption;
}
# imageExists - Check if a picture already exists in a list of pictures
# Input: $pictures_captions    - Picture captions array from FaceBook's photos.get method
#        $new_picture - Absolute path to the new photo to be checked.
# Output: bool - True if picture exists. False if picture does not exist.
function imageExists($pictures_captions, $new_picture) {
	# Make sure the picture array is actually one
	if (!is_array($pictures)) {
		return false;
	}
	$caption = getCaption($new_picture);
	return in_array($caption, $album_captions);
	/*
	Old method. foreach should only be run once, not for each photo.
	foreach($pictures as $album_pictures) {
	# Make sure the album is an array (will not be for a new album)
	if (!is_array($album_pictures) || empty($pictures)) {
	continue;
	}
	foreach($album_pictures as $picture) {
	# If the caption matches, which the uploader assigns to the filename minus extension.
	if (@$picture['caption'] == getCaption($new_picture)) return true;
	}
	}
	in_array
	return false;
	*/
}
# makeThumbBatch - Create a thumbnail of a photo in batch mode. Will create a new process with proc_open
# Input: $file - Absolute path to the new photo to have a thumb created
# Output: Array[0] proc_open resource.
#		  Array[1] Associative array with the [original] file and [thumb]nail being generated.
function makeThumbBatch($file) {
	disp("Make Thumbnail: $file", 6);
	# global variable for converter.
	global $converter;
	# Generate a temp file where the thumbnails will be put before uploading.
	$temp_file = tempnam("/tmp", "fbi_");
	# image quality
	$quality = 80;
	# Resize to max facebook photo size.
	$resize = "720x720";
	# Input File
	$input = escapeshellarg($file);
	# Output file.
	$output = escapeshellarg($temp_file);
	# create command to create thumbnail
	$command = "$converter -format JPG -quality $quality -size $resize -resize $resize +profile '*' $input $output";
	disp($command, 6);
	$descriptorspec = array(0 => array("file", "/dev/null", "r"), 1 => array("file", "/dev/null", "w"), 2 => array("file", "/dev/null", "a"));
	# Fork process
	$ret[0] = proc_open($command, $descriptorspec, $pipes);
	$ret[1] = array("original" => $file, "thumb" => $output);
	# Return output.
	return $ret;
}
# folderScan - Scan folder for images and directories
# Input: $dir - Directory to scan
# Output: Associative array with all the [images] and [directories] that the input directory contains.
function folderScan($dir) {
	disp("Scanning Folder: $dir", 6);
	# Define image extensions in lower case.
	$imgExt = array('jpg', 'jpeg', 'png', 'gif', 'bmp', 'tif', 'tiff');
	# Add trailing slash to directory if it doesn't exist already.
	$dir = substr($dir, -1) == "/" ? $dir : $dir . "/";
	# Create arrays
	$result['directories'] = Array();
	$result['images'] = Array();
	# If the scan fails.
	if (!($files = @scandir($dir))) disp("Failed scanning $dir", 2);
	# If there were files found.
	if (count($files) > 0) {
		foreach($files as $file) {
			# Skip all Unix hidden images & directories
			if (substr($file, 0, 1) == ".") {
				continue;
			}
			# If the 'file' is a directory.
			if (is_dir($dir . $file)) {
				$result['directories'][] = $dir . $file;
			}
			# If the 'file' is an image file.
			if (is_file($dir . $file) && hasExt($file, $imgExt)) {
				$result['images'][] = $dir . $file;
			}
		}
	}
	return $result;
}
# hasExt - Determine if file has an extension.
# Input: $file - file with extension
#		 $findExt - string or array of strings of extensions to compare to.
# Output: Boolean if the file's extension is in the string/array of #findExt
function hasExt($file, $findExt) {
	# If the extension to search for isn't an array, make it one
	if (!is_array($findExt)) {
		$findExt[] = $findExt;
	}
	# Find the extension of the file
	$ext = end(explode(".", $file));
	# Return if the extension exists in the list of extensions to search.
	return in_array(strtolower($ext), strtolower($findExt));
}
# getConverter - Find the conversion utility. (Image Magick or Graphics Magick)
# Input: $path - specified path to converter.
# Output: path to converter is assigned to $converter global.
function getConverter($path = NULL) {
	disp("Finding image converter.", 6);
	global $converter;
	# If a path isn't specified.
	if (is_null($path)) {
		# Attempt to find graphics magic and image magick
		$gm = exec("which gm");
		$im = exec("which convert");
		# Specify converter, prefer graphics magick.
		if (!empty($gm)) {
			$converter = "$gm convert";
			disp("Found GraphicsMagic, using $gm", 3);
		} elseif (!empty($im)) {
			$converter = $im;
			disp("Found ImageMagick, using $im", 3);
		} else {
			disp("No suitable image converter found. Specify one with -c on the command line or\ninstall Image or GraphicsMagick and make sure that the executable location is added to your PATH.", 1);
		}
	} else {
		# If path isn't executable
		if (!is_executable($path)) {
			# Return an error.
			disp("$path is not executable. Please specify one with -c", 1);
		}
		$ex = pathinfo($path, PATHINFO_FILENAME);
		if ($ex == "gm" {
			$converter = "$path convert";
		} else {
			$converter = $path;
		}
	}
	# In case it has spaces in the name.
	$converter = escapeshellcmd($converter);
}
# parseParameters - Parse input parameters. Taken from the comments on the getopts page.
# Input: nothing
# Output: array of input parameters
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
# disp - Display messages according to verbosity level. Any message with a level <=1 will cause the program to exit
# Input: $message message to display
#		 $level display level of message. If verbrosity is >= to the display level, the message will be displayed
function disp($message, $level) {
	global $verbosity; # Get verbrosity level.
	# If the level of the message is less than the vebrosity level, display the message.
	# If verbrosity level >=4, display the duration
	$message = (($verbosity >= 4 && $level <= $verbosity) ? " (" . getDuration($verbosity) . " s) " : "") . (($level <= $verbosity) ? $message : "");
	echo empty($message) ? "" : $message . "\n";
	if ($level <= 1) die("\n");
}
# getDuration - Calculate diration between events.
# Input: verbrosity
function getDuration($verbosity) {
	global $start_time;
	$elapsed = round(microtime(true) - ($start_time), 3);
	# For verbrosity <5, just display time since the beginning. For vebrosity >=5, show elapsed time between events.
	if ($verbosity >= 5) $start_time = microtime(true);
	return $elapsed;
}
