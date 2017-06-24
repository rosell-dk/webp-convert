<?php


class WebPConvertPathHelper {

  public static $do_logging = TRUE; 

  // Little helper
  public static function logmsg($msg = '') {
    if (WebPConvertPathHelper::$do_logging) {
      echo $msg . '<br>';
    }
  }

  // Canonicalize a path by resolving '../' and './'
  // Got it from a comment here: http://php.net/manual/en/function.realpath.php
  // But fixed it (it could not handle './../')
  public static function canonicalize($path) {
    $parts = explode('/', $path);

    // Remove parts containing just '.' (and the empty holes afterwards)
    $parts = array_values(array_filter($parts, function($var) {
      return ($var != '.');
    }));

    // Remove parts containing '..' and the preceding
    $keys = array_keys($parts, '..');
    foreach($keys as $keypos => $key) {
      array_splice($parts, $key - ($keypos * 2 + 1), 2);
    }
    return implode('/', $parts);
  }



  // We do not operate with backslashes here. Windows is a big boy now, 
  // it can handle forward slashes
  public static function replace_backslashes($str) {
    return str_replace('\\', '/', $str);
  }

  public static function remove_double_slash($str) {
    return preg_replace('/\/\//', '/', $str);
  }

  /* Get relative path between one dir and the other.
     ie
        from:   /var/www/wordpress/wp-content/plugins/webp-express
        to:     /var/www/wordpress/wp-content/uploads
        result: ../../uploads/     
     */
  public static function get_rel_dir($from_dir, $to_dir) {
    $from_dir_parts = explode('/', str_replace( '\\', '/', $from_dir ));
    $to_dir_parts = explode('/', str_replace( '\\', '/', $to_dir ));
    $i = 0;
    while (($i < count($from_dir_parts)) && ($i < count($to_dir_parts)) && ($from_dir_parts[$i] == $to_dir_parts[$i])) {
      $i++;
    }
    $rel = "";
    for ($j = $i; $j < count($from_dir_parts); $j++) {
      $rel .= "../";
    } 

    for ($j = $i; $j < count($to_dir_parts); $j++) {
      $rel .= $to_dir_parts[$j];
      if ($j < count($to_dir_parts)-1) {
        $rel .= '/';
      }
    }
    return $rel;
  }

  // Strip filename from path
  // Similar to dirname, but does not localize
  public static function get_folder($path_with_filename) {
    $parts = explode('/', $path_with_filename);
    array_pop($parts);
    return implode('/', $parts);
  }


  /**
   * Calculates absolute path from relative path, but handles absolute path too
   * Relative path is per default taken to be relative to DOCUMENT_ROOT.
   * This can however be altered by providing another root
   *
   * If path starts with "/", it is considered an absolute path, and it is just
   * passed through
   * 
   * @param   path    Relative or absolute path (relative to document root).
   */
  public static function abspath($relative_or_absolute_path, $root = NULL) {
    if (!isset($root)) {
      $root = $_SERVER['DOCUMENT_ROOT'];
    }
    $relative_or_absolute_path = self::replace_backslashes($relative_or_absolute_path);
    if ((substr($relative_or_absolute_path, 0, 1) == '/')) {
      return $relative_or_absolute_path;
    }
    else {
      return self::canonicalize(self::remove_double_slash($root . '/' . $relative_or_absolute_path));
    }
  }

  /**
   * Calculates relative path from absolute path, but handles absolute path too
   * Relative path is per default taken to be relative to DOCUMENT_ROOT.
   * This can however be altered by providing another root
   *
   * If path starts with "/", it is considered an absolute path, and it is just
   * passed through
   * 
   * @param   path    Relative or absolute path (relative to document root).
   */
  public static function relpath($abs_path, $root = NULL) {
    $abs_path = self::abspath($abs_path);
//    self::logmsg('Source path: "' . $abs_path . '"');
    if (!isset($root)) {
      $root = $_SERVER['DOCUMENT_ROOT'];
    }
//    self::logmsg('root: "' . $root . '"');
    $source_rel = self::get_rel_dir($root, $abs_path);
  //  self::logmsg('Relative path of source (calculated): "' . $source_rel . '"');
    if (preg_match('/\.\.\//', $source_rel)) {
    //  self::logmsg('<b>Note</b>: path is outside document root. You are allowed to, but its unusual setup. When used to calculate destination folder, the "../"s will be removed');
    }
    return $source_rel;
  }

  /**
   * Calculates absolute destination path 
   * Calculated like this : [desired destination root] + [relative path of source file] + ".webp"
   *
   * If you want converted files to be put in the same folder as the originals, just leave out
   * the destination_root parameter.
   *
   * If you want all converted files to reside in their own folder, set the destination_root
   * parameter to point to that folder. The converted files will be stored in a hierarchy that
   * matches the source files. With destination_root set to "webp-cache", the source file
   * "images/2017/cool.jpg" will be stored at "webp-cache/images/2017/cool.jpg.webp"
   * You can provide absolute path or relative path for destination_root.
   * If the path starts with "/", it is considered an absolute path (also true for source argument)
   * Examples of valid paths "webp-cache", "/var/www/webp-cache", "..", "../images", "."
   * 
   * @param   source             path to source file. 
   *                             May be absolute or relative (relative to document root or provided $root).
   * @param   destination_root   path to destination root
   *                             
   * @return  destination_path   absolute path for destination file
   */
  public static function get_destination_path($source, $destination_root = '', $root = NULL) {

    if (!isset($root)) {
      $root = $_SERVER['DOCUMENT_ROOT'];
    }

    // Step 1: Get relative path of source
    $source_rel = self::relpath($source, $root);


    // Step 2: Get absolute destination root
    if ((substr($destination_root, 0, 1) == '/')) {
      // Its already an absolute path. Do nothing
    }
    else {
      $destination_root = self::replace_backslashes($destination_root);
      if ($destination_root == '') {
        $destination_root = '.';
      }
      $destination_root = self::canonicalize(self::remove_double_slash($root . '/' . $destination_root));
    }

    // Step 3: Put the two together, and append ".wepb"
    return self::canonicalize(self::remove_double_slash($destination_root . '/' . preg_replace('/\.\.\//', '', $source_rel) . '.webp'));

  }

}


?>
