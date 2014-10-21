<?php

/***********************************

   GET PARAMETERS ACCEPTED :
    - mode :
       * 'cover' : look for a 'Folder.jpg' file in the folder of the song passed to the script
       * 'song' : look for an artwork in the metadata of the song passed to the script
       * 'folder' : look for an artwork in the metadata of all the songs stored in the folder of the song passed to the script
       * novalue : try 'cover', if fail then try 'song', if fail then try 'folder'
    - cache :
       * 'off' : don't store and restore in APC the artworks of the last songs passed to the script
       * novalue : store and restore in APC the artworks of the last songs passed to the script
    - debug :
       * 'on' : display the artwork and logs
       * novalue : display the artwork only

  ie : http://volumio.local/artwork/music/NAS%2FMusic%20Tib%2F2080%2FThe%20Backup%20-%20EP%2FFolder.jpg?mode=folder&cache=off
  
***********************************/

// artwork manager include
include('inc/Artwork/artwork.php');

// Handle browser caching for artwork
if (!(isset($_GET['cache']) and $_GET['cache']==='off')) {
  cacheManager::httpCachingHeaders(__FILE__, filemtime(__FILE__));
}

$am = new artworkManager();
// activate debug if requested in the URL
if (isset($_GET['debug']) and $_GET['debug']==='on') {
  $am->debug->turnOn(true);
}
// disable cache if requested in the URL
if (isset($_GET['cache']) and $_GET['cache']==='off') {
  $am->disableCache();
}
$mode = (isset($_GET['mode']))? $_GET['mode'] : '';
$am->setMode($mode);
$am->process();

// Display the artwork
$am->display();