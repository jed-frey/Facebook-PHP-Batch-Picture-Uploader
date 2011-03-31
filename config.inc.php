<?php
$converterPath = NULL; # To permanently change the image converter, set it here, otherwise use -c on the command line to set it.
$albumLimit = 200; # Limit the number of photos per album to this. Currently 200 in facebook.
$photoSizeSD = 720;  # Resize to max facebook photo size. Currently 720x720 in facebook.
$photoSizeHD = 2000; # Resize to max facebook photo size. Currently 720x720 in facebook.
$defaultSD=false; # Set the default image quality. If $defaultSD=true, use "-hd" to upload in high quality.
				  # If $defaultSD=false, use "-sd" to upload in standard quality.

$photoQuality = 80; # JPEG Quality to resize with.
$batchLimit = 15; # Facebook batch limit. Currently 20, set to 15 to be safe.