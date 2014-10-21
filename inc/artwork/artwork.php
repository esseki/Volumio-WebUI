<?php

/* Dont' change anything before this line */
define('REWRITE_RULE_IDENTIFIER', 'artwork');
define('PATH_TO_MPD_LIBRARY', '/var/lib/mpd');
define('ARTWORK_APC_TTL', 3600);
define('PLACEHOLDER_PATH', '/var/www/images/artwork/placeholder.png');
/* Dont' change anything after this line */


class debug {
  private $isDebug; 
  private $debugTrace;
  private $startTime;
  private $endTime;
  private $lastTime;
  private $indent;

  function __construct() {
    $this->isDebug = false;
    $this->log = '';
    $this->startTime = microtime(true);
    $this->indent = '';
  }

  public function turnOn($bool) {
    $this->isDebug = ($bool === true)? true : false;
  }

  public function isDebug() {
    return $this->isDebug;
  }

  public function indent($bool) {
    if ($bool === true) {
      $this->indent .= '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
    } else {
      $this->indent = substr($this->indent, 0, -30);
    }
  }

  public function addDebugTrace($trace, $start, $finish) {
    if ($this->isDebug === true) {
      if ($start === false and $finish === false) {
        $this->debugTrace .= $this->indent.$trace.'</br>';
      } else {
        $begin = 0;
        $end = 0;
        if ($start === 'beginning') {
          $begin = $this->startTime;
        }
        if ($start === 'last') {
          $begin = $this->lastTime;
        }
        if ($finish === 'end') {
          $this->endTime = microtime(true);
          $end = $this->endTime;
        }
        if ($finish === 'now') {
          $end = microtime(true);
          $this->lastTime = $end;
        }
        $duration = round($end - $begin, 3);
        $this->debugTrace .= $this->indent.$trace.' : '.$duration.'s</br>';
      }    
    }
  }

  public function displayDebugTrace() {
    if ($this->isDebug === true) {
      echo $this->debugTrace;
    }
  }
}

class cacheManager {
  private $isCacheActivated;
  
  function __construct() {
    $this->isCacheActivated = (extension_loaded('apc') && ini_get('apc.enabled'))? true : false;
  }

  static function httpCachingHeaders ($id, $timestamp) {
    $gmt_mtime = gmdate('r', $timestamp);
    header('ETag: "'.md5($timestamp.$id).'"');
    header('Last-Modified: '.$gmt_mtime);
    header('Cache-Control: public');

    if(isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) || isset($_SERVER['HTTP_IF_NONE_MATCH'])) {
      if ($_SERVER['HTTP_IF_MODIFIED_SINCE'] == $gmt_mtime || str_replace('"', '', stripslashes($_SERVER['HTTP_IF_NONE_MATCH'])) == md5($timestamp.$id)) {
        header('HTTP/1.1 304 Not Modified');
        exit();
      }
    }
  }

  public function disable() {
    $this->isCacheActivated = false;
  }

  public function storeInCache($key, $data, $duration) {
    if($this->isCacheActivated === true) {
      apc_add(base64_encode($key), base64_encode($data), $duration);
    } else {
      throw new Exception('Cache is disabled');
    }
  }

  public function getFromCache($key) {
    $data = false;
    if($this->isCacheActivated === true) {
      $data = apc_fetch(base64_encode($key));
      if ($data !== false) { 
        return base64_decode($data);
      } else {
        throw new Exception('No data found in cache');
      } 
    } else {
      throw new Exception('Cache is disabled');
    }
  } 
}

class imageManager {
  private $image;
  private $imageType;
  private $imageBlob;

  function __construct() {
    $this->image = new Imagick();
    $this->imageType = null;
    $this->imageBlob = null;
  }

  public function getImageBlob() {
    try {
      if (is_null($this->imageBlob)) {
        $this->imageBlob = $this->image->getImageBlob();
      }   
      return $this->imageBlob;
    } catch (Exception $e) {}
  }

  public function loadFromBinary($binary) {
    try {
      $this->imageBlob = $binary;
      $this->image->readImageBlob($this->imageBlob);
      // set the artwork image format to jpg
      $this->image->setImageFormat('jpg');
    } catch (Exception $e) {}
  }

  public function loadFromFile($fileName) {
    try {
      $this->loadFromBinary(file_get_contents($fileName));
    } catch (Exception $e) {}
  }

  public function setImageFormat($format) {
    try {
      $this->image->setImageFormat($format);
      $this->imageType = 'image/'.$format;
    } catch (Exception $e) {}
  }

  public function displayImage() {
    try {
      header('Content-Type: '.$this->imageType);
      echo $this->imageBlob;
    } catch (Exception $e) {}
  }
}

class artworkManager {  
  public $debug;
  private $mode;
  private $pathToSong;
  private $songPlayed;
  private $id3TagManager;
  private $artwork;
  private $rewriteRuleIdentifier;
  private $pathToMpdLibrary;
  
  function __construct() {
    $this->debug = new debug();
    $this->mode = null;
    $this->songPlayed = null;
    $this->pathToSong = null;
    $this->id3TagManager = null;
    $this->artwork = new imageManager();
    $this->cache = new cacheManager();

    $this->rewriteRuleIdentifier = REWRITE_RULE_IDENTIFIER;
    $this->pathToMpdLibrary = PATH_TO_MPD_LIBRARY;
    $this->artworkCacheTTL = ARTWORK_APC_TTL;
  }
  
  // Define the way the script will look for artworks
  public function setMode($mode) {
    switch ($mode) {
      // Search for a file "Folder.jpg" in the folder containing the song php
      case 'cover':
        $this->debug->addDebugTrace('Mode set to : cover', false, false);
        $this->mode = 'cover';
        break;
      // Search for an artwork in the metadata of the song passed to the script  
      case 'song':
        $this->debug->addDebugTrace('Mode set to : song', false, false);
        $this->mode = 'song';
        break;
      // Search for an artwork in all the songs contained in the folder containing the song passed to the script
      case 'folder':
        $this->debug->addDebugTrace('Mode set to : folder', false, false);
        $this->mode = 'folder';
      default:
        $this->mode = 'all';
    }
  }
  
  // Disable cache
  public function disableCache() {
    $this->cache->disable();
    $this->debug->addDebugTrace('Cache disabled', false, false);
  }

  private function isASong($filename) {
    $pathA = pathinfo($filename);
    if (isset($pathA['extension'])) {
      $pathA['extension'] = strtolower($pathA['extension']);
      if ($pathA['extension'] === 'mp3' or $pathA['extension'] === 'm4a' or $pathA['extension'] === 'wav') {
        return true;
      } else {
        return false;
      }
    }
  }

  // Retrieve the linux path to the file passed to the script
  private function retrievePathToSong() {
    if (is_null($this->pathToSong)) {
      // remove the query string if any
      $url = parse_url(rawurldecode($_SERVER["REQUEST_URI"]), PHP_URL_PATH);
      // remove the rewrite rule idenitifer to retrieve the path
      $this->pathToSong = str_ireplace('/'.$this->rewriteRuleIdentifier, '', $url);
      $pathA = pathinfo($this->pathToSong);
      $this->pathToSong = $pathA["dirname"].'/';
      // if a song is actually being played we store it
      if ($this->isASong($pathA['basename'])) {
        $this->songPlayed = $pathA['basename'];
        $this->debug->addDebugTrace('songPlayed set to : '.$this->songPlayed, false, false);
      }
      if (is_dir($this->pathToMpdLibrary.$this->pathToSong)) {
        $this->pathToSong = $this->pathToMpdLibrary.$this->pathToSong;
        $this->debug->addDebugTrace('pathToSong set to : '.$this->pathToSong, false, false);
      } else {
        throw new Exception('No folder found');
      }
    } 
  }
  
  // Build a URL to retrieve an artwork from a song
  static function buildArtworkUrl($pathTosong, $mode = null) {
    // Add URL to the song played to retrieve artwork
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') {
      $protocol = 'https';
    } else {
      $protocol = 'http';
    }
    
    // will generate a URL that will search for an artwork only in the metadata of the song
    if (!isset($mode) or $mode !== 'song') {
      $url = rawurlencode($pathTosong).'?mode=song';
    } 
    // or will generate a URL that will search for an aartwork either in a "Folder.jpg" file or browsing the folder containing the song
    else {
      $url = rawurlencode(substr($pathTosong, 0, strripos($pathTosong, '/')).'/Folder.jpg');
    }

    return $protocol.'://'.$_SERVER['HTTP_HOST'].'/artwork/music/'.$url;
  }

  // Instanciate the ID3 tag manager
  private function getId3TagManager() {
    if (is_null($this->id3TagManager)) {
      // include getID3() library (can be in a different directory if full path is specified)
      require_once('getID3/getid3/getid3.php');
      // Initialize getID3 engine
      $this->id3TagManager = new getID3;  
    }
    return $this->id3TagManager; 
  }
  
  // find the artwork if present as an image file in the folder containing the album
  private function searchArtworkInAFolder($pathToFolder) {
    $this->debug->addDebugTrace('Browse "'.$pathToFolder.'" for "Folder.jpg"', false, false);
    $this->debug->indent(true);
    $artworkFileName = null;
    if ($filesA = scandir($pathToFolder)) {
      foreach ($filesA as $key => $file) {
        $pathA = pathinfo(strtolower($file));
        if (isset($pathA['extension']) and isset($pathA['filename']))  {
          // search for an image in the folder
          if (strtolower($pathA['extension']) == 'jpg' or strtolower($pathA['extension']) == 'jpeg' or strtolower($pathA['extension']) == 'png') {
            // if an image filename is "cover" or "folder" we use this image
            if (strtolower($pathA['filename']) == 'cover' or strtolower($pathA['filename']) == 'folder') {
              $artworkFileName = $file;
            } elseif ($artworkFileName === '') {
              $artworkFileName = $file;
            }
          }
        }
      }
    }
    if (!is_null($artworkFileName)) {
      $this->debug->addDebugTrace('Found "'.$artworkFileName.'"', 'last', 'now');
      $this->artwork->loadFromFile($this->pathToSong.$artworkFileName);
      $this->debug->addDebugTrace('Retrieve "'.$artworkFileName.'"', 'last', 'now');
      $this->debug->indent(false);
      throw new Exception('Artwork found in folder');
    } else {
      $this->debug->addDebugTrace('No file found', 'last', 'now');
      $this->debug->indent(false);
    }
  }

  // find the artwork in a music file ID3 tag 
  private function searchArtworkInAFile($pathToFile) {
    $this->debug->addDebugTrace('Browse "'.$pathToFile.'" for artwork', 'last', 'now');
    $this->debug->indent(true);
    if ($this->isASong($pathToFile)) {
      $this->debug->addDebugTrace('Search for artwork in "'.$pathToFile.'"', 'last', 'now');
      $this->debug->indent(true);
      $getID3 = $this->getId3TagManager();
      $ThisFileInfo = $this->getId3TagManager()->analyze($pathToFile);
      $this->debug->addDebugTrace('Analyse "'.$pathToFile.'"', 'last', 'now');
      getid3_lib::CopyTagsToComments($ThisFileInfo);
      if (isset($ThisFileInfo['comments']['picture'][0])) {
        $this->debug->addDebugTrace('Retrieve artwork "'.$pathToFile.'"', 'last', 'now');
        $this->debug->indent(false);
        $this->debug->indent(false);
        $this->artwork->loadFromBinary($ThisFileInfo['comments']['picture'][0]['data']);
        throw new Exception('Artwork found in song');
      }
      $this->debug->indent(false);
    } else {
      $this->debug->addDebugTrace('Not a song file : skipped', 'last', 'now');
    }
    $this->debug->indent(false);
  }

  // find the artwork in every music files (ID3 tag) present in the folder containing the album 
  private function browseSongsInFolder($pathToFolder) {
    $this->debug->addDebugTrace('Browse "'.$pathToFolder.'" for songs', false, false);
    $this->debug->indent(true);
    $getID3 = $this->getId3TagManager();
    try {
      if ($filesA = scandir($pathToFolder)) {
        foreach ($filesA as $key => $file) {
          $this->searchArtworkInAFile($this->pathToSong.$file);
        }
      }
    }
    catch(Exception $e) {
      $this->debug->indent(false);
      throw $e;
    }
  }

  private function storeArtworkInCache() {
    try {
      $this->cache->storeInCache($this->pathToSong, $this->artwork->getImageBlob(), $this->artworkCacheTTL);
      $this->debug->addDebugTrace('Store artwork into cache', 'last', 'now');
    } catch (Exception $e) {
      $this->debug->addDebugTrace('Skip store artwork into cache', 'last', 'now');
    }
  }

  private function getArtworkFromCache() {
    try {
      $artworkBinary = $this->cache->getFromCache($this->pathToSong);
      $this->debug->addDebugTrace('Search for artwork in cache', false, false);
      $this->debug->indent(true);
      $this->artwork->loadFromBinary($artworkBinary);
      $this->debug->addDebugTrace('Retrieve artwork from cache', 'last', 'now');
      $this->debug->indent(false);
      throw new Exception('Artwork found in cache');
    } 
    catch (Exception $e) {
      if ($e->getMessage() === 'Artwork found in cache') {
        throw $e;
      } elseif ($e->getMessage() === 'No data found in cache') {
        $this->debug->addDebugTrace('No artwork found in cache', 'last', 'now');
        $this->debug->indent(false);
      } else {
        $this->debug->addDebugTrace('Skip cache search', 'last', 'now');
        $this->debug->indent(false);
      }
    }
  } 

  private function getPlaceholder() {
    if (is_file(PLACEHOLDER_PATH)) {
      $this->artwork->loadFromFile(PLACEHOLDER_PATH);
      $this->debug->addDebugTrace('Placeholder found at : '.PLACEHOLDER_PATH, 'last', 'now');
      throw new Exception('Paceholder found');
    }
    else {
      $this->debug->addDebugTrace('No placeholder found', false, false);
    }
  }

  public function process() {
    try {
      $this->retrievePathToSong();
      $this->debug->addDebugTrace('Script bootstrap', 'beginning', 'now');

      // search for an artwork in cache
      //$this->getArtworkFromCache();
      // search for a file Folder.jpg in the folder containing the song being passed to the script
      if ($this->mode === 'cover' or $this->mode === 'all') {
        $this->searchArtworkInAFolder($this->pathToSong);
      }
      // else search for an artwork in the song passed to the script
      if ($this->mode === 'song' or $this->mode === 'all') {
        $this->searchArtworkInAFile($this->pathToSong.$this->songPlayed);
      }  
      // else browse all the songs contained in the folder containing the song passed to the script
      if ($this->mode === 'folder' or $this->mode === 'all') {
        $this->browseSongsInFolder($this->pathToSong);
      }
      // if no artwork has been found, display a placeholder
      $this->getPlaceholder(); 
    } catch (Exception $e) {
      // If artwork has been found in cache we don't write it into the cache again
      if ($e->getMessage() !== 'Artwork found in cache') {
        $this->storeArtworkInCache(); 
      }
    }
  }

  public function display() {
    // render the artwok
    if ($this->debug->isDebug() === false) {
      $this->artwork->displayImage();
    } else {
      echo "<img src='data:image/jpg;base64,".base64_encode($this->artwork->getImageBlob())."' /><br />";
    }
    $this->debug->addDebugTrace('Render the artwork', 'last', 'now');
    
    // Display the debug trace is debug mode is On
    $this->debug->addDebugTrace('Total time', 'beginning', 'end'); 
    $this->debug->displayDebugTrace();
  }
}