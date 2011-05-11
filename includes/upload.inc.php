<?php
// Wait for all thumbnail processing threads to finish.
// Restart any threads that may have been killed. (Used to work around Dreamhost shared server processes).
function waitToProcess($proc) {
	$allowedFailures=5;
	$r=proc_get_status($proc);
	for ($failures=0;$failures<$allowedFailures;$failures++) {
		while ($r["running"]) $r=proc_get_status($proc);
		// Break if the process died normally.
		if ($r["termsig"]==0) break;
		// If the process was killed. Fire it up again.
		disp("Processed Killed", 7); // Throw a high level warning.
		$descriptorspec = array(0 => array("file", "/dev/null", "r"), 1 => array("file", "/dev/null", "w"), 2 => array("file", "/dev/null", "a"));
		// Reopen the process
		$proc=proc_open($r["command"], $descriptorspec, $pipes);
		// Get the process status.
		$r=proc_get_status($proc);
	}
	// If we've failed the maximum number of times, give up. 
	// There is probably something wrong with the image or it's too large to be converted and keeps getting killed.
	if ($failures==$allowedFailures) {
		disp("Process killed too many times. Try executing it via command line and see what the issue is.", 5);
		return 1;
	}
	return 0;
}
// Upload Images into Image Albums.
function uploadImages($images, $imageAlbums) {
	global $fbo, $key, $uid, $batchSize;
	$albumImages = getAlbumImages($imageAlbums);
	$c = count($images);
	$a = 0; $b = 0;
	$md5s=array();
	// Loop through all the images. Skip by batch size.
	for ($a = 0;$a < $c;$a+=$batchSize) {
		$imagesToUpload = array(); // empty the array for images to upload.
		// For for the batch size while $b is less than the batch size OR a + b is less than the total number of images.
		for ($b = 0;$b < $batchSize && ($a + $b) < $c;$b++) {
			$z = $a + $b;         // Get the "real" image index number.
			$image = $images[$z]; // Pull out the image to manipulate
			$caption = getCaption($image); // Get the caption of the image
			$md5 = md5_file($image); // Get the MD5 of the image.
			// Check for duplicate images.
			if (array_key_exists("md5", $albumImages) && array_search($md5, $albumImages["md5"])!==false) {
				disp("Skipping: $image already uploaded (MD5 Check)", 3);
				continue;
			}
			// Check for duplicate images in the queue.
			if (array_search($md5, $md5s)!==false) {
				disp("Skipping: Identical image to $image already queued (MD5 Check)", 3);
				continue;
			}
			$md5s[]=$md5;
			list($process, $thumb) = makeThumbBatch($image);
			$temp["image"] = $image;
			$temp["caption"] = $caption;
			$temp["process"] = $process;
			$temp["thumb"] = $thumb;
			$temp["caption"] = $caption."\n\n\n".$md5;
			$temp["uploaded"] = false;
			$temp["errors"] = 0;
			$imagesToUpload[] = $temp;
		}
		while (1) {
			$j = 0;
			for ($i = 0;$i < count($imagesToUpload);$i++) {
				if ($imagesToUpload[$i]["uploaded"]) continue;
				disp("Waiting for processing to finish on: " . $imagesToUpload[$i]["image"], 5);
				// If the process continues to fail, just mark as uploaded and move on.
				if (waitToProcess($imagesToUpload[$i]["process"])) {
					disp("Image Conversion Failed, skipping: " . $imagesToUpload[$i]["image"], 2);
					$imagesToUpload[$i]["uploaded"]=true;
					continue;
				}
				disp("Finished Processing.", 5);
				try {
					$fbo->api_client->photos_upload($imagesToUpload[$i]["thumb"],getUploadAID($imageAlbums, $uploadAlbumIdx),$imagesToUpload[$i]["caption"], $uid);
					$imageAlbums["size"][$uploadAlbumIdx]++;
					$imagesToUpload[$i]["uploaded"] = true;
					disp("Uploaded: " . $imagesToUpload[$i]["image"], 3);
					$j++;
				} catch(Exception $e) {
					disp($e->getCode() . " " . $e->getMessage(), 5);
					switch ($e->getCode()) {
						case 1:
						case 2:
						case 5:
							disp("Non-fatal error: " . $e->getMessage(), 3);
						break;
						case 100:
						case 101:
						case 103:
						case 104:
						case 120:
						case 200:
							disp("Fatal error: " . $e->getMessage() . "\n Please submit a bug report: 
http://github.com/jedediahfrey/Facebook-PHP-Batch-Picture-Uploader", 1);
						break;
						case 102:
							disp("Could not login. Try creating a new auth code.", 1);
						case 321:
							disp($e->getMessage() . ". Should have been caught earlier.", 3);
							$imageAlbums["size"][$uploadAlbumIdx] = 200;
						break;
						case 324:
							disp($e->getMessage() . ". Bad Graphics/ImageMagick output? Skipping " . $imagesToUpload[$i]["image"], 3);
							$imagesToUpload[$i]["uploaded"] = true;
						break;
						case 325:
							disp($e->getMessage() . ". Allow php_batch_uploader to upload files directly: 
http://www.facebook.com/authorize.php?v=1.0&api_key={$key}&ext_perm=photo_upload\n\n", 1);
						break;
					}
					$imagesToUpload[$i]["errors"]++;
				}
			}
			// If all images have been uploaded, break out of the while loop.
			if ($j == 0) break;
		}
	}
}
// Get the Album ID to upload images to.
function getUploadAID(&$imageAlbums, &$uploadAlbumIdx) {
	static $uploadAlbumIndex;
	if (is_array($imageAlbums)) {
		$c = count($imageAlbums["aid"]);
		// Loop through all of the albums and see if they have less than 200 pictures.
		for ($uploadAlbumIdx = 0;$uploadAlbumIdx < $c;$uploadAlbumIdx++) {	
			// If you can't upload or the image album has more than 200 photos.		
			if ($imageAlbums["size"][$uploadAlbumIdx] < 200) {
				return $imageAlbums["aid"][$uploadAlbumIdx];
			}
		}
	}
	// Generate a new album name. "Album #n+1"
	$newAlbumName = genAlbumName(end($imageAlbums["name"]));
	// Create the album name.
	$newAlbum = createAlbum($newAlbumName);
	// Add the new album to the array of current image albums.
	foreach($newAlbum as $key => $value) {
		$imageAlbums[$key][] = $value;
	}
	// Return the AID.
	return $imageAlbums["aid"][count($imageAlbums["aid"]) - 1];
}
// Get all of the images for an array of albums.
// Groups images that are in "Album", "Album #2", "Album #3", etc.
function getAlbumImages($albums) {
	global $fbo;
	$i = 0;
	$allAlbumPictures=array();
	foreach($albums["aid"] as $aid) {
		$allAlbumPictures[] = &$fbo->api_client->photos_get("", $aid, "");
	}
	// Merge all of the album pictures into one picture array.
	$albumImages = array();
	foreach($allAlbumPictures as $albumPictures) {
		if (is_array($albumPictures)) {
			// For each of the album pictures found
			foreach($albumPictures as $picture) {
				// Grab out the md5 so we don't do duplicate uploads.
				if (preg_match("/[0-9a-f]{32}/i",$picture["caption"],$md5)) {
					$picture["md5"]=$md5[0];
				} else {
					$picture["md5"]=null;
				}
				// Add the picture to the stockpile.
				$albumImages[] = $picture;
			}
		}
	}
	return arrayMutate($albumImages);
}
// Get all of the albums associated with a base album image name.
function getImageAlbums($album_name) {
	global $albums, $fbo, $uid;
	// Get a list of user albums
	$albums = getAlbums();
	$albums2 = arrayMutate($albums);
	if ($idx = array_search($album_name, $albums2["name"])) {
		$imageAlbums[]=$albums[$idx];
		disp("Found $album_name", 5);
		// If "Album Name #X" is found in the full list of albums, add that to the list.
		for ($i = 2;$idx = array_search("$album_name #$i", $albums2["name"]);$i++) {
			$imageAlbums[]=$albums[$idx];
			disp("Found $album_name #$i", 5);
		}
	} else {
		disp("$album_name not found. Creating.", 2);
		$imageAlbums[0] = createAlbum($album_name);
	}
	$imageAlbums = arrayMutate($imageAlbums);
	return $imageAlbums;
}
// getAlbumBase - Get the base name of an album based on the mode.
// Input: $image - Image to get the album base for.
function getAlbumBase($image) {
	global $root_dir, $mode, $albumName;
	if ($albumName===NULL) {
		$album_name = ($mode == 1) ? basename(dirname($image)) : basename($root_dir);
		disp("Generating Album Base Name: $album_name", 5);
	} else {
		$album_name=$albumName;
		disp("Using given name: $album_name", 5);
	}
	return $album_name;
}
// genAlbumName - Generate a new album name.
// Input: $baseAlbumName - base name of album.
// Output: return the newName.
function genAlbumName($baseAlbumName) {
	// Determine if the album name 'My Album #2' etc is in use.
	if (preg_match('/([^#]+) #([\\d]+)/', $baseAlbumName, $regs)) {
		// If so, increment the number by 1.
		$newName = $regs[1] . " #" . (intval($regs[2]) + 1);
	} else {
		// Else, album name is #2.
		$newName = $baseAlbumName . " #2";
	}
	disp("Generated new album name $newName from $baseAlbumName", 5);
	// Return the new name
	return $newName;
}
// getCaption - Get the caption for the image based on the mode.
// Input: $image - Image file to generate caption for.
// Output: Caption of image file.
function getCaption($image) {
	global $root_dir, $mode, $glue;
	$root_dir = substr($root_dir, -1) == "/" ? $root_dir : $root_dir . "/";
	if ($mode == 1) {
		// In Mode 1 (where each (sub)directory gets its own album, just use the file name
		$caption = pathinfo($image, PATHINFO_FILENAME);
	} elseif ($mode == 2) {
		// Replace the root directory with nothing.
		$dir_structure = explode(DIRECTORY_SEPARATOR, str_replace($root_dir, "", $image));
		// Generate a caption based on the folder's relative
		$caption = pathinfo(implode($glue, $dir_structure), PATHINFO_FILENAME);
	} else {
		disp("Invalid Mode", 1);
	}
	// Trim off excess white spaces.
	$caption = trim($caption);
	disp("Got Caption: $caption for $image", 5);
	return $caption;
}
