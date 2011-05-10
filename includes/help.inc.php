<?php
# printHelp - Display Help Function
function printHelp($help = "") {
	global $key;
	$help.= <<<EOF
Usage:  php_batch_uploader [-m mode] [-v verbosity] [-nr] [-n album name] [-nr] [-sd/-hd] directories
        php_batch_uploader -a auth

  -a    Facebook Authentication Code. Must be used before the script can upload anything.
            Visit http://www.facebook.com/code_gen.php?v=1.0&api_key=$key
            to authorize php_batch_uploader and generate code.

            To authorize direct uploading of pictures, you have to authorize php_batch_uploader direct upload access. This can be granted here:
            http://www.facebook.com/authorize.php?v=1.0&api_key=$key&ext_perm=photo_upload 

  -m    Upload Mode.
            1: Upload each directory & subdirectory as album name. Caption based on image name. [Default]
            2: Use the top level directory input as album name. Create caption based on subdirectories & image name. [Default with Album Name set, -n]
            h: Display detailed information about how each of the modes works, with examples.

  -v    Script verbosity.
            0: Display nothing, not even warnings or fatal errors. Script will run, then exit.
            1: Display only errors which cause the script to exit.
            2: Display errors, warnings and minimal script progress [Default]
            3: Display everything w/time stamp when event occurred.
            4: Display everything w/time stamp since last message.
            5: Debug w/time stamp when event occurred.
            6: Debug w/time stamp since last message.

  -nr    Disable recursion. Only upload images in the specified folders.

Album Options
  -n    Album name. Sets mode to 2 and uploads all images to specified album.

  -d    Album Description.

  -l    Album Location.

  -p    Privacy settings. Options: 'friends', 'friends-of-friends', 'networks', or 'everyone'. 
            Default: 'friends' (Can be changed in config.inc.php)

  -u    UID of Fan Page. Upload photos as fan page you manage. php_batch_uploaded must be authorized to manage pages:
            http://www.facebook.com/authorize.php?v=1.0&api_key=$key&ext_perm=manage_pages

Image Quality. To set HD as default, edit config.inc.php and change \$defaultSD=false.
  -hd    Upload photos in high quality (2000x2000). This enables "Download in High Resolution" link when viewing photos.
  -sd    Upload photos in standard quality (720x720). If \$defaultSD=false, force uploading of images in standard quality.

EOF;
	
	echo $help;
}
# printModeHelp - Print help for the differences between modes.
function printModeHelp() {
	$help = <<<EOF
Modes Explained:
    Each of the modes will recursively upload all images and folders in a given directory. 
    The only way in which they differ is how the files are captioned and the album names that they are put into.

    Mode 1 (-m 1):
        Uses the directory that the file is in as the Album Name. 
        The image is then captioned as the image name minus extension or EXIF Caption.

    Mode 2 (-m 2):
        Uses the directory(s) passed to the script as the as Album Name. 
        The image is then captioned with the relative path to the image and the image minus extension or EXIF Caption.

    Example, for the sample folder structure below:
        1) ~/pictures/2008/Road Trips/Road Trip #.jpg, etc
        2) ~/pictures/2008/Road Trips/Vegas/Vegas #.jpg, etc
        3) ~/pictures/2008/Road Trips/Grand Canyon/GC # .jpg,etc 
        4) ~/pictures/2009/New Years Eve/Down Town/Fireworks/FireWorks #.jpg, etc
        5) ~/pictures/2009/Road Trips/Road Trip #.jpg, etc

    Called with "[php] php_batch_uploader ~/pictures/2008 ~/pictures/2009"

    Mode 1:
        1) Album 'Road Trips' is created and all images are uploaded with caption "Road Trip #"
        2) Album 'Vegas' is created and and all images is uploaded with caption "Vegas #"
        3) Album 'Grand Canyon' is created and and all images is uploaded with caption "GC #"
        4) Album 'Fireworks' is created and and all images is uploaded with caption "FireWorks #"
        5) Because album 'Road Trips' already exists, all images in this folder will be uploaded to the existing Album.

    Mode 2:
        ~/pictures/2008 & ~/pictures/2009 are the input directories, '2008' and '2009' will be the Album Names, respectively
        Since '2008' & '2009' is the root directory, images in sub folders will be uploaded into these two albums.
        1) Album '2008' is created, images will be captioned with "Road Trips - Road Trip #"
        2) Album '2008' is used, images will be captioned with "Road Trips - Vegas - Vegas #"
        3) Album '2008' is used, images will be captioned with "Road Trips - Grand Canyon - GC #"
        4) Album '2009' is created, images will be captioned with "New Years Eve - Down Town - Fireworks - FireWorks #"
        5) Album '2009' is used, images will be captioned with "Road Trips - Road Trip#

	Mode 2 with album specified.
		Called with "[php] php_batch_uploader -n "My Life" ~/pictures/2008 ~/pictures/2009") 
		1) Album 'My Life' is created, images will be captioned with "Road Trips - Road Trip #"
        2) Album 'My Life' is used, images will be captioned with "Road Trips - Vegas - Vegas #"
        3) Album 'My Life' is used, images will be captioned with "Road Trips - Grand Canyon - GC #"
        4) Album 'My Life' is used, images will be captioned with "New Years Eve - Down Town - Fireworks - FireWorks #"
        5) Album 'My Life' is used, images will be captioned with "Road Trips - Road Trip#

    Caveat:
        The captions are used to determine unique pictures. In the above example, in mode 1, 
        if you had a 2008/Road Trips/1.jpg & 2009/Roadtrips/1.jpg, the second image will be skipped 
        because the script will think it's the same picture.

    Beware of how your shell script interprets inputs for mode 2
        For example, in bash,  php_batch_uploader ~/pictures/ & php_batch_uploader ~/pictures/* are not the same.
        In the first,  1 argument (~/pictures/) is passed to the script, so the Album Name in Mode 2 will be "pictures"
        In the second, 2 arguments (~/pictures/2008,~/pictures/2009) are passed and albums in the example will be created.

        In Mode 1, there won't be any difference (unless you have pictures in the root folder ~/pictures, then an Album "pictures" will be created)

    When the Facebook limit of 200 photos is reached. The album name is suffixed with a number sign and a number starting at 2.
        "Spring Break" becomes "Spring Break #2" for photos 201-400 then "Spring Break #3" for 401-600 so on and so forth.

EOF;
	echo $help;
}
