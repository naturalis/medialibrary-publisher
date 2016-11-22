#!/bin/bash

# Initializes the file system for/after test runs of the
# harvester, master publisher, www publisher, offloader
# and cleaner.
#
########################################################
##      DO NOT RUN IN PRODUCTION ENVIRONMENTS !!!!!   ##
########################################################

top='/opt/medialib_data/'
bak='/opt/medialib_data/media-bak'
resetDb='true'

php reset.php "$top" "$bak" "$resetDb"

