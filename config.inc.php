<?php
$defaultSD     = false; // Set the default image quality. If $defaultSD=true, use "-hd" to upload in high quality.
                        // If $defaultSD=false, use "-sd" to upload in standard quality.
$photoSizeSD   = 720;   // Resize to max facebook photo size. Currently 720x720 in facebook.
$photoSizeHD   = 2000;  // Resize to max facebook photo size. Currently 720x720 in facebook.
$albumLimit    = 200;   // Limit the number of photos per album to this. Currently 200 in facebook.
$photoQuality  = 80;    // JPEG Quality to resize with.
$converterPath = NULL;  // To permanently change the image converter, set it here, otherwise use -c on the command line to set it.
$batchSize     = 5;     // Number of images to convert at once. Too few and your CPU is idle while it is uploading. Too many and your CPU is overloaded.
$privacy       = "friends"; // Default privacy settings.
// These shouldn't need edited and changed unless you want to use them with your own app.
// Key and Secret for php_batch_uploader.
$app_id="221615537869856";
$key = "c6e96655073cb303448fcb5144d810c1";
$sec = "89f60d87f2b73a762070399533630d3c";
$urlAccess = "https://www.facebook.com/dialog/oauth?client_id={$app_id}&redirect_uri=http://jedediahfrey.github.com/Facebook-PHP-Batch-Picture-Uploader/success.html&scope=user_photos,offline_access,manage_pages";
$urlAuth = "http://www.facebook.com/code_gen.php?v=1.0&api_key={$key}";
$urlUpload = "http://www.facebook.com/authorize.php?v=1.0&api_key={$key}&ext_perm=photo_upload";