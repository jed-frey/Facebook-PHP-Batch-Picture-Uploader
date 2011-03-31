<?php
# Wait for all thumbnail processing threads to finish.
function waitToProcess($procs) {
	if (!is_array($procs)) $procs = array($procs);
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
// Upload Images into Image Albums.
function uploadImages($images, $imageAlbums) {
	global $fbo, $key;
	$albumImages = getAlbumImages($imageAlbums);
	$c = count($images);
	$a = 0;
	$b = 0;
	$batchSize = 10;
	$md5s=array();
	for ($a = 0;$a < $c;$a+=$batchSize) {
		$imagesToUpload = array();
		for ($b = 0;$b < $batchSize && ($a + $b) < $c;$b++) {
			$z = $a + $b;
			$image = $images[$z];
			$caption = getCaption($image);
			$md5 = md5_file($image);
			#
			if (array_key_exists("md5", $albumImages) && array_search($md5, $albumImages["md5"])!==false) {
				disp("Skipping: $image already uploaded (MD5 Check)", 4);
				continue;
			}
			if (array_search($md5, $md5s)!==false) {
				disp("Skipping: Identical image to $image already queued (MD5 Check)", 4);
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
				waitToProcess($imagesToUpload[$i]["process"]);
				try {
					$fbo->api_client->photos_upload($imagesToUpload[$i]["thumb"], getUploadAID($imageAlbums, $uploadAlbumIdx), $imagesToUpload[$i]["caption"]);
					$imageAlbums["size"][$uploadAlbumIdx]++;
					$imagesToUpload[$i]["uploaded"] = true;
					disp("Uploaded: " . $imagesToUpload[$i]["image"], 3);
					$j++;
				}
				catch(Exception $e) {
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
							disp("Fatal error: " . $e->getMessage() . "\n Please submit a bug report: http://github.com/jedediahfrey/Facebook-PHP-Batch-Picture-Uploader", 1);
						break;
						case 102:
							disp("Could not login. Try creating a new auth code.", 1);
						case 321:
							disp($e->getMessage() . ". Should have been caught earlier.", 3);
							$imageAlbums["size"][$uploadAlbumIdx] = 200;
							$imageAlbums["can_upload"][$uploadAlbumIdx] = 0;
						break;
						case 324:
							disp($e->getMessage() . ". Bad Graphics/ImageMagick output? Skipping " . $imagesToUpload[$i]["image"], 3);
							$imagesToUpload[$i]["uploaded"] = true;
						break;
						case 325:
							disp($e->getMessage() . ". Allow php_batch_uploader to upload files directly: http://www.facebook.com/authorize.php?v=1.0&api_key={$key}&ext_perm=photo_upload\n\n", 1);
						break;
					}
					$imagesToUpload[$i]["errors"]++;
				}
			}
			if ($j == 0) break;
		}
	}
}
function getUploadAID(&$imageAlbums, &$uploadAlbumIdx) {
	static $uploadAlbumIndex;
	$idx = array_search(1, $imageAlbums["can_upload"]);
	# If you can't upload or the image album has more than 200 photos.
	if (is_array($imageAlbums)) {
		$c = count($imageAlbums["aid"]);
		for ($uploadAlbumIdx = 0;$uploadAlbumIdx < $c;$uploadAlbumIdx++) {
			if ($imageAlbums["can_upload"][$uploadAlbumIdx] && $imageAlbums["size"][$uploadAlbumIdx] < 200) {
				return $imageAlbums["aid"][$uploadAlbumIdx];
			}
		}
	}
	$newAlbumName = genAlbumName(end($imageAlbums["name"]));
	$newAlbum = createAlbum($newAlbumName);
	foreach($newAlbum as $key => $value) {
		$imageAlbums[$key][] = $value;
	}
	$uploadAlbumIdx = count($imageAlbums["name"]) - 1;
	return $imageAlbums["aid"][$uploadAlbumIdx];
}
function getAlbumImages($albums) {
	global $fbo, $batchLimit;
	$i = 0;
	$fbo->api_client->begin_batch();
	foreach($albums["aid"] as $aid) {
		$allAlbumPictures[$i] = & $fbo->api_client->photos_get("", $aid, "");
		$i++;
		if (($i % $batchLimit) == 0) {
			disp("Batch execution function limit reached. Executing and beginning new batch.", 5);
			$fbo->api_client->end_batch();
			$fbo->api_client->begin_batch();
		}
	}
	$fbo->api_client->end_batch();
	# Merge all of the album pictures into one picture array.
	$albumImages = array();
	foreach($allAlbumPictures as $albumPictures) {
		if (is_array($albumPictures)) {
			foreach($albumPictures as $picture) {
				if (preg_match("/[0-9a-f]{32}/i",$picture["caption"],$md5)) {
					$picture["md5"]=$md5[0];
				} else {
					$picture["md5"]=null;
				}
				$albumImages[] = $picture;
			}
		}
	}
	return arrayMutate($albumImages);
}
# Get the album ID if the album exists, else create the album and return the ID.
function getImageAlbums($album_name) {
	global $albums, $fbo, $uid;
	# Get a list of user albums
	$albums = getAlbums();
	$albums2 = arrayMutate($albums);
	if ($idx[] = array_search($album_name, $albums2["name"])) {
		disp("Found $album_name", 6);
		for ($i = 2;$idx_tmp = array_search("$album_name #$i", $albums2["name"]);$i++) {
			$idx[] = $idx_tmp;
			disp("Found $album_name #$i", 6);
		}
		foreach($idx as $i) {
			$imageAlbums[] = $albums[$i];
		}
	} else {
		disp("$album_name not found. Creating.", 2);
		$imageAlbums[0] = createAlbum($album_name);
	}
	$imageAlbums = arrayMutate($imageAlbums);
	return $imageAlbums;
}
# getAlbumBase - Get the base name of an album based on the mode.
# Input: $image - Image to get the album base for.
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
