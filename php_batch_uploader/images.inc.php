<?php
# makeThumbBatch - Create a thumbnail of a photo in batch mode. Will create a new process with proc_open
# Input: $file - Absolute path to the new photo to have a thumb created
# Output: Array[0] proc_open resource.
#		  Array[1] Associative array with the [original] file and [thumb]nail being generated.
function makeThumbBatch($file) {
	global $photoSize, $photoQuality, $converter;
	disp("Make Thumbnail: $file", 6);
	# global variable for converter.
	$temp_file = tempnam("/tmp", "fbi_"); # Generate a temp file where the thumbnails will be put before uploading.
	$input = escapeshellarg($file);			  # Input File
	$output = escapeshellarg($temp_file); # Output file.
	# create command to create thumbnail
	$command = "$converter -format JPG -quality $photoQuality -size $photoSize -resize $photoSize +profile '*' $input $output";
	disp($command, 6);
	$descriptorspec = array(0 => array("file", "/dev/null", "r"), 1 => array("file", "/dev/null", "w"), 2 => array("file", "/dev/null", "a"));
	# Fork process
	$ret[0] = proc_open($command, $descriptorspec, $pipes);
	$ret[1] = array("original" => $file, "thumb" => $output);
	# Return output.
	return $ret;
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
		if ($ex == "gm") {
			$converter = "$path convert";
		} else {
			$converter = $path;
		}
	}
	# In case it has spaces in the name.
	$converter = escapeshellcmd($converter);
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
	return in_array(strtolower($ext), $findExt);
}