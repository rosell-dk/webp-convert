<?php


class WebPConvert {
  private static $tools_order = array();

  public static $serve_converted_image = TRUE;
  public static $serve_original_image_on_fail = TRUE;
  private static $preferred_tools_order = TRUE;

  public static $current_conversion_vars;

  // Little helper
  public static function logmsg($msg = '') {
    if (!WebPConvert::$serve_converted_image) {
      echo $msg . '<br>';
    }
  }

  // Critical fail - when we can't even serve the source as fallback
  private static function cfail($msg) {
    if (!WebPConvert::$serve_converted_image) {
      echo $msg;
    }
    else {
      header('Content-type: image/gif');

      // Prevent caching error message
      header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
      header("Cache-Control: post-check=0, pre-check=0", FALSE);
      header("Pragma: no-cache");

      $image = imagecreatetruecolor(620, 200);
      imagestring($image, 1, 5, 5,  $msg, imagecolorallocate($image, 233, 214, 291));
  //      echo imagewebp($image);
      echo imagegif($image);
      imagedestroy($image);
    }
  }

  private static function fail($msg) {
    self::logmsg($msg);
    if (WebPConvert::$serve_converted_image && WebPConvert::$serve_original_image_on_fail) {
      $ext = array_pop(explode('.', WebPConvert::$current_conversion_vars['source']));
      switch ($ext) {
        case 'jpg':
        case 'jpeg':
          header('Content-type: image/jpeg');
          break;
        case 'png':
          header('Content-type: image/png');
          break;
      }
      readfile(WebPConvert::$current_conversion_vars['source']);
    }
  }

  public static function set_preferred_tools($preferred_tools) {
    // Remove preferred tools from order (we will add them again right away!)
    self::$tools_order = array_diff(self::$tools_order, $preferred_tools);

    // Add preferred tools to order
    foreach (array_reverse($preferred_tools) as $pref_tool) {
      if (function_exists('webpconvert_' . $pref_tool)) {
        array_unshift(self::$tools_order, $pref_tool);
      }
    }
  }

  /**
    @param (string) $source: Absolute path to image to be converted (no backslashes). Image must be jpeg or png
    @param (string) $destination: Absolute path (no backslashes)
    @param (int) $quality (optional):  Quality of converted file (0-100)
    @param (bool) $strip_metadata (optional):  Whether or not to strip metadata. Default is to strip. Not all tools supports this
  */
  public static function convert($source, $destination, $quality = 85, $strip_metadata = TRUE) {

    self::logmsg('WebPConvert::convert() called');
    self::logmsg('- source: ' . $source);
    self::logmsg('- destination: ' . $destination);
    self::logmsg('- quality: ' . $quality);
    self::logmsg('- strip_metadata: ' . ($strip_metadata ? 'true' : 'false'));
    self::logmsg();

    WebPConvert::$current_conversion_vars = array();
    WebPConvert::$current_conversion_vars['source'] =  $source;
    WebPConvert::$current_conversion_vars['destination'] =  $destination;

    if (self::$serve_converted_image) {
      // If set to serve image, textual content will corrupt the image
      // Therefore, we prevent PHP from outputting error messages
      ini_set('display_errors','Off');
    }

    // "dirname", but which doesn't localization
    function strip_filename_from_path($path_with_filename) {
      $parts = explode('/', $path_with_filename);
      array_pop($parts);
      return implode('/', $parts);
    }

    // Test if file extension is valid
    $parts = explode('.', $source);
    $ext = array_pop($parts);

    if (!in_array($ext, array('jpg', 'jpeg', 'png'))) {
      self::cfail("Unsupported file extension: " . $ext);
      return;      
    }

    // Test if source file exists
    if (!file_exists($source)) {
      self::cfail("File not found: " . $source);
      return;
    }

    // Prepare destination folder
    $destination_folder = strip_filename_from_path($destination);

    if (!file_exists($destination_folder)) {

      self::logmsg('We need to create destination folder');

      // Find out which permissions to set.
      // We want same permissions as parent folder
      // But which parent? - the parent to the first missing folder
      // (TODO: what to do if this is outside open basedir? http://php.net/manual/en/ini.core.php#ini.open-basedir)
      $parent_folders = explode('/', $destination_folder);
      $popped_folders = array();
      while (!(file_exists(implode('/', $parent_folders)))) {
        array_unshift($popped_folders, array_pop($parent_folders));
      }
      $closest_existing_folder = implode('/', $parent_folders);

      self::logmsg('Using permissions of closest existing folder (' . $closest_existing_folder . ')');
      $perms = fileperms($closest_existing_folder) & 000777;
      self::logmsg('Permissions are: 0' . decoct($perms));

      if (!mkdir($destination_folder, $perms, TRUE)) {
        self::fail('Failed creating folder:' . $folder);
        return;
      };
      self::logmsg('Folder created successfully');

      // alas, mkdir does not respect $perms. We have to chmod each created subfolder
      $path = $closest_existing_folder;
      foreach ($popped_folders as $subfolder) {
        $path .= '/' . $subfolder;
        self::logmsg('chmod 0' . decoct($perms) . ' ' . $path);
        chmod( $path, $perms );
      }

    }


    // Test if it will be possible to write file
    if (file_exists($destination)) {
      if (!is_writable($destination)) {
        self::fail('Cannot overwrite file: ' . $destination  . '. Check the file permissions.');
        return;
      }
    }
    else {
      if (!is_writable($destination_folder)) {
        self::fail('Cannot write file: ' . $destination  . '. Check the folder permissions.');
        return;
      }
    }

    // If there is already a converted file at destination, remove it
    // (actually it seems the current tools can handle that, but maybe not future tools)
    if (file_exists($destination)) {
      if (unlink($destination)) {
        self::logmsg('Destination file already exists... - removed');
      }
      else {
        self::logmsg('Destination file already exists. Could not remove it');
      }
    }

    self::logmsg('Order of tools to be tried: ' . implode(', ', self::$tools_order));

    $success = FALSE;
    foreach (self::$tools_order as $tool_name) {
      self::logmsg('<br>trying <b>' . $tool_name . '</b>');

      $time_start = microtime(true);
      $result = call_user_func('webpconvert_' . $tool_name, $source, $destination, $quality, $strip_metadata);
      $time_end = microtime(true);
      self::logmsg('execution time: ' . round(($time_end - $time_start) * 1000) . ' ms');
      
      if ($result === TRUE) {
        self::logmsg('success!');
        $success = TRUE;
        break;
      }
      else {
        self::logmsg($result);
      }
    }

    if (!$success) {
      self::fail('No tools could convert file: ' . $source);
      return;
    }


    if (!file_exists($destination)) {
      self::fail('Failed saving image to path: ' . $destination);
      return;
    }


    if (self::$serve_converted_image) {
      // Serve the saved file
      header('Content-type: image/webp');
//    Should we add Content-Length header?  header('Content-Length: ' . filesize($file));
      readfile($destination);
    }

  }

  public static function addTool($name) {
    self::$tools_order[] = $name;
  }
}

/* Add plugins */
foreach (scandir(__DIR__ . '/plugins') as $file) {
  if (is_dir('plugins/' . $file)) {
    if ($file == '.') continue;
    if ($file == '..') continue;

    // echo 'Added plugin: ' . $file . '<br>';
    include_once('plugins/' . $file . '/' . $file . '.php');

    WebPConvert::addTool($file);
  }
}


?>
