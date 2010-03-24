<?php
# getAlbums - Get all current facebook albums
function getAlbums() {
	global $fbo;
	disp("Getting albums",6);
	# Create the album.
	$albums = $fbo->api_client->photos_getAlbums("","");
}

# getAlbums - Get all current facebook albums
function createAlbums($name) {
	global $fbo;
	disp("Creating album: $name",6);
	try {
		$album = $fbo->api_client->photos_createAlbum($name);
	} catch(Exception $e) {
		disp("Failed to create album $album",1);
	}
	# Created albums do not have the following parameters.
	$albums[0]["can_upload"]=1;
	$albums[0]["size"]=0;
	$albums[0]["type"]="normal";
	return $albums;
	return $albums;
}

# getImages - Get all current images album(s)
function getImages($aids) {
	if (!is_array($aids)) {
		$aids=array($aids);
	}
	
	foreach ($aids as $aid) {
		
	}
}

# facebookBatch - Performs batch facebook functions adhering to the 20 cmd limit on batch processes
function facebookBatch() {

}