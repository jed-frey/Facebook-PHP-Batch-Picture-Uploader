#!/usr/bin/env bash
C=$(which curl);
W=$(which wget);

if [ ! -n "$C" ]; then
	if [ ! -n "$W" ]; then
		echo "cURL or wget are not installed. Please install them or download http://github.com/facebook/platform/raw/master/clients/packages/facebook-platform.tar.gz and extract into this directory"
		exit 1
	else
		wget --no-check-certificate -O facebook-platform.tar.gz "http://github.com/facebook/platform/raw/master/clients/packages/facebook-platform.tar.gz"
	fi
else
	curl -OL "http://github.com/facebook/platform/raw/master/clients/packages/facebook-platform.tar.gz"
fi
tar -xvf facebook-platform.tar.gz
rm facebook-platform.tar.gz
