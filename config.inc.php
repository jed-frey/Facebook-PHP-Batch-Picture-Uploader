<?php
$defaultSD     = false; # Set the default image quality. If $defaultSD=true, use "-hd" to upload in high quality.
                        # If $defaultSD=false, use "-sd" to upload in standard quality.
$photoSizeSD   = 720;   # Resize to max facebook photo size. Currently 720x720 in facebook.
$photoSizeHD   = 2000;  # Resize to max facebook photo size. Currently 720x720 in facebook.
$albumLimit    = 200;   # Limit the number of photos per album to this. Currently 200 in facebook.
$photoQuality  = 80;    # JPEG Quality to resize with.
$batchLimit    = 15;    # Facebook batch limit. Currently 20, set to 15 to be safe.
$converterPath = NULL;  # To permanently change the image converter, set it here, otherwise use -c on the command line to set it.

## These shouldn't need edited and changed unless you want to use them with your own app.
# Key and Secret for php_batch_uploader.
$app_id="180748208632384";
$key = "7c984a9708b1a9f0eb0880017560e840";
$sec = "cfcec008079a87aace666875c0fcf3d9";
$urlAccess = "https://www.facebook.com/dialog/oauth?client_id={$app_id}&redirect_uri=http://jedediahfrey.github.com/Facebook-PHP-Batch-Picture-Uploader/success.html&scope=user_photos";
$urlAuth = "http://www.facebook.com/code_gen.php?v=1.0&api_key={$key}";
$urlUpload = "http://www.facebook.com/authorize.php?v=1.0&api_key={$key}&ext_perm=photo_upload";