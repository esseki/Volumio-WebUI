<?php

/* Dont' change anything before this line */
define('REWRITE_RULE_IDENTIFIER', 'artwork');
define('PATH_TO_MPD_LIBRARY', '/var/lib/mpd');
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
    }
    else {
      $this->indent = substr($this->indent, 0, -30);
    }
  }

  public function addDebugTrace($trace, $start, $finish) {
    if ($this->isDebug === true) {
      if ($start === false and $finish === false) {
        $this->debugTrace .= $this->indent.$trace.'</br>';
      }
      else {
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

  public function storeInCache($key, $data, $duration) {
    if($this->isCacheActivated === true) {
      apc_add(base64_encode($key), base64_encode($data), $duration);
    }
  }

  public function getFromCache($key) {
    $data = false;
    if($this->isCacheActivated === true) {
      $data = apc_fetch(base64_encode($key));
    } 
    return ($data !== false)? base64_decode($data) : false; 
  } 
}

class imageManager {
  private $image;
  private $imageType;

  function __construct() {
    $this->image = new Imagick();
    $this->imageType = null;
  }

  public function getImageBlob() {
    try {
      return $this->image->getImageBlob();
    } catch (Exception $e) {}
  }

  public function loadFromFile($fileName) {
    try {
      $this->image->readImage($fileName);
    } catch (Exception $e) {}
  }

  public function loadFromBinary($binary) {
    $this->image->readImageBlob($binary);
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
      echo $this->image;
    } catch (Exception $e) {}
  }
}

class artworkManager {  
  public $debug;
  private $pathToSong;
  private $songPlayed;
  private $id3TagManager;
  private $artwork;
  private $rewriteRuleIdentifier;
  private $pathToMpdLibrary;
  
  function __construct() {
    $this->debug = new debug();
    $this->songPlayed = null;
    $this->pathToSong = null;
    $this->id3TagManager = null;
    $this->artwork = new imageManager();
    $this->cache = new cacheManager();

    $this->rewriteRuleIdentifier = REWRITE_RULE_IDENTIFIER;
    $this->pathToMpdLibrary = PATH_TO_MPD_LIBRARY;
  }
  
  // Retrieve the linux path to the file played
  private function retrievePathToSong() {
    if (is_null($this->pathToSong)) {
      $this->pathToSong = rawurldecode($_SERVER["REQUEST_URI"]);
      // remove the rewrite rule idenitifer to retrieve the path
      $this->pathToSong = str_ireplace('/'.$this->rewriteRuleIdentifier, '', $this->pathToSong);
      $pathA = pathinfo($this->pathToSong);
      $this->pathToSong = $pathA["dirname"].'/';
      // if a song is actually being played we store it
      if (strtolower($pathA['extension']) !== 'jpg' and strtolower($pathA['extension']) !== 'jpeg' and strtolower($pathA['extension']) !== 'png') {
        $this->songPlayed = $pathA["basename"];
      }
      if (is_dir($this->pathToMpdLibrary.$this->pathToSong)) {
        $this->pathToSong = $this->pathToMpdLibrary.$this->pathToSong;
      } else {
        throw new Exception('No folder found');
      }
    }
  }
  
  // Set the linux path to the file played (for debug purpose)
  public function setPathToSong($path) {
    $this->pathToSong = $path;
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
    $this->debug->addDebugTrace('Browse "'.$pathToFolder.'" with scandir for "Folder.jpg"', false, false);
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
            }
            elseif ($artworkFileName === '') {
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
    }
    else {
      $this->debug->addDebugTrace('No file found', 'last', 'now');
      $this->debug->indent(false);
    }
  }

  // find the artwork in a music file ID3 tag 
  private function searchArtworkInAFile($pathToFile) {
    $this->debug->addDebugTrace('Search for artwork in "'.$pathToFile.'"', false, false);
    $this->debug->indent(true);
    $getID3 = $this->getId3TagManager();
    $ThisFileInfo = $this->getId3TagManager()->analyze($pathToFile);
    $this->debug->addDebugTrace('Analyse "'.$pathToFile.'"', 'last', 'now');
    getid3_lib::CopyTagsToComments($ThisFileInfo);
    if (isset($ThisFileInfo['comments']['picture'][0])) {
      $this->debug->addDebugTrace('Retrieve artwork "'.$pathToFile.'"', 'last', 'now');
      $this->debug->indent(false);
      $this->artwork->loadFromBinary($ThisFileInfo['comments']['picture'][0]['data']);
      throw new Exception('Artwork found in song');
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
    $this->cache->storeInCache($this->pathToSong, $this->artwork->getImageBlob(), 3600);
    $this->debug->addDebugTrace('Stored artwork into cache', 'last', 'now');
  }

  private function getArtworkFromCache() {
    $this->debug->addDebugTrace('Search for artwork in cache', false, false);
    $this->debug->indent(true);
    $artworkBinary = $this->cache->getFromCache($this->pathToSong);
    if ($artworkBinary !== false) {
      $this->artwork->loadFromBinary($artworkBinary);
      $this->debug->addDebugTrace('Retrieve artwork from cache', 'last', 'now');
      $this->debug->indent(false);
      throw new Exception('Artwork found in cache');
    } else {
      $this->debug->addDebugTrace('No artwork found in cache', 'last', 'now');
      $this->debug->indent(false);
    }
  } 

  public function process() {
    try {
      $this->retrievePathToSong();
      $this->debug->addDebugTrace('Script bootstrap', 'beginning', 'now');

      // serach for an artwork in cache
      $this->getArtworkFromCache();
      // search for a file Folder.jpg in the folder containing the song being played
      $this->searchArtworkInAFolder($this->pathToSong);
      // else search for an artwork in the song played
      if (!is_null($this->songPlayed)) {
        $this->searchArtworkInAFile($this->pathToSong.$this->songPlayed);
      }
      else {
        $this->debug->addDebugTrace('No song played : skipped', false, false);
      }
      // else browse all the songs contained in the folder containing the song played 
      $this->browseSongsInFolder($this->pathToSong);
    } catch (Exception $e) {}

    // set the artwork image format to jpg
    $this->artwork->setImageFormat('jpg');
    $this->storeArtworkInCache();
    if ($this->debug->isDebug() === false) {
      $this->artwork->displayImage();
    }
    
    $this->debug->addDebugTrace('Total time', 'beginning', 'end');    
    // Display the debug trace is debug mode is On
    $this->debug->displayDebugTrace();
  }
}

$am = new artworkManager();
//$am->debug->turnOn(true);
//$am->setPathToSong('/home/volumio/PHP/');
$am->process();

