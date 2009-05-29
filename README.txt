Requirements: 
	php5 (http://php.net)
	ImageMagick (http://www.imagemagick.org/script/index.php)
		Graphics Magick also works, you will have to edit 
	*NIX like OS (Linux, OS X, etc). Windows might work, but has not been tested.
	Some command line knowledge.
	
Installation:
	Move the php_batch_uploader folder to anywhere you wish.
	1) Run the script directly: ./php_batch_uploader ~/Pictures/
	2) Run the script through php: php php_batch_uploader ~/Pictures/
	3) Add the php_batch_uploader directory to your PATH and run: cd ~/Pictures;php_batch_uploader ./
	
Usage:  php_batch_uploader [-m MODE] [-v VERBOSITY] dirs
        php_batch_uploader -a AUTH
	   
  -a    Facebook Authentication Code. Must be used the first time the script is run.
            Visit http://www.facebook.com/code_gen.php?v=1.0&api_key=187d16837396c6d5ecb4b48b7b8fa038 to authorize php_batch_uploader and get code
  -m    Upload Mode.
            1: Upload each directory & subdirectory as album name. Caption based on image name.[Default]
            2: Use the top level directory input as album name. Create caption based on subdirectories & image name
            h: Display detailed information about how each of the modes works, with examples
  -v    Script verbosity.
            0: Display nothing, not even warnings or errors
            1: Display only errors which cause the script to exit.
            2: Display errors and warnings. [Default]
            3: Display everything. (When file is uploaded, etc)
  
  dirs  Directories passed to script. These are the folders that are uploaded to facebook.
  
