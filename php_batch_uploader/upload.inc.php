<?php
$batchLimit = 15;
function batchPrep($images) {
}
# Wait for all thumbnail processing threads to finish.
function waitToProcess($procs) {
	if (!is_array($procs)) $procs=array($procs);
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
function uploadImages($images, $imageAlbums) {
	global $fbo;
	$albumImages = getAlbumImages($imageAlbums);
	$imagesToUpload = array();
	foreach($images as $image) {
		$caption = getCaption($image);
		if (array_search($caption, $albumImages["caption"])) {
			disp("Skipping: $image as  '$caption' already uploaded.", 4);
			continue;
		}
		list($process, $thumb) = makeThumbBatch($image);
		$temp["image"] = $image;
		$temp["caption"] = $caption;
		$temp["process"] = $process;
		$temp["thumb"] = $thumb;
		$temp["caption"] = $caption;
		$temp["uploaded"] = false;
		$imagesToUpload[]=$temp;
	}
	while (1) {
		$j=0;
		for ($i=0;$i<count($imagesToUpload);$i++) {
			if ($imagesToUpload[$i]["uploaded"]) continue;
			waitToProcess($imagesToUpload[$i]["process"]);		
			$fbReturn[$j] = $fbo->api_client->photos_upload($imagesToUpload[$i]["thumb"], getUploadAID($imageAlbums,$uploadAlbumIdx), $imagesToUpload[$i]["caption"]);
			$imageAlbums["size"][$uploadAlbumIdx]++;
			die(array($fbReturn,$imageAlbums,$imagesToUpload));
		}
		if ($j=0) break;
	}
	
	/*
	for ($i=0;($i+$j)<$c;$i+=10) {
		for ($j=0;($i+$j)<$c&&$j<10;$j++) {
			$k=$j+$i;
			
		}
	}*/
}

function getUploadAID(&$imageAlbums,&$uploadAlbumIdx) {
	static $uploadAlbumIndex;
	$uploadAlbumIdx=array_search(1,$imageAlbums["can_upload"]);
	if ($uploadAlbumIndex===false||$imageAlbums["size"][$uploadAlbumIdx]>=200) {
		$newAlbumName=genAlbumName(end($imageAlbums["name"]));
		$newAlbum=createAlbum($newAlbumName);
		foreach ($newAlbum as $key => $value) {
			$imageAlbums[$key][]=$value;
		}
		$uploadAlbumIdx=count($imageAlbums["name"])-1;
	}
	$aid=$imageAlbums["aid"][$uploadAlbumIdx];
	return $aid;
}
# Upload the photo
/*
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
*/

function getAlbumImages($albums) {
	global $fbo, $batchLimit;
	$i = 0;
	$fbo->api_client->begin_batch();
	foreach($albums["aid"] as $aid) {
		$allAlbumPictures[$i] = & $fbo->api_client->photos_get("", $aid, "");
		$i++;
		if (($i % $batchLimit) == 0) {
			disp("Batch execution function limit reached. Executing and beginning new.", 6);
			$fbo->api_client->end_batch();
			$fbo->api_client->begin_batch();
		}
	}
	$fbo->api_client->end_batch();
	# Merge all of the album pictures into one picture array.
	$pictures = array();
	foreach($allAlbumPictures as $albumPictures) {
		foreach($albumPictures as $picture) {
			$pictures[] = $picture;
		}
	}
	return arrayMutate($pictures);
}
# Get the album ID if the album exists, else create the album and return the ID.
function getImageAlbums($album_name) {
	global $albums, $fbo, $uid;
	# Get a list of user albums
	$albums = getAlbums();
	$albums2 = arrayMutate($albums);
	//$album_name="Road Trip - May 2006";
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
		$imageAlbums[0]=createAlbum($album_name);
	}
	$imageAlbums = arrayMutate($imageAlbums);
	return $imageAlbums;
}
# getAlbumBase - Get the base name of an album based on the mode.
# Input: $image - Image to get the album base for.
function getAlbumBase($image) {
	global $root_dir, $mode;
	$album_name = ($mode == 1) ? basename(dirname($image)) : basename($root_dir);
	disp("Generating Album Base Name: $album_name", 6);
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
