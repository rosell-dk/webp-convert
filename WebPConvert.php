<?php

class WebPConvert
{
    private static $preferred_converters = array();

    public static $serve_converted_image = true;
    public static $serve_original_image_on_fail = true;

    public static $current_conversion_vars;

    // Little helper
    public static function logMessage($msg = '')
    {
        // http://php.net/manual/en/filter.filters.sanitize.php

        if (!self::$serve_converted_image) {
            // First fully encode (safety first)
            $html_encoded = filter_var($msg, FILTER_SANITIZE_FULL_SPECIAL_CHARS);

            // Then decode the following safe tags: <b>, </b>, <i>, </i>, <br>
            $html_decoded = preg_replace('/&lt;(\/?)(b|i|br)&gt;/', '<$1$2>', $html_encoded);
            echo $html_decoded . '<br>';
        }
    }

    /*
    We distinguish between "critical errors" and "normal errors".
    Both types of fails means that the conversion fails.

    The distinction has to do with whether it will be possible to serve the source as fallback
    A "critical" fail is when that aren't possible.

    We have two error functions:
    - criticalError() will output an image with the error message, when $serve_converted_image is set. Otherwise, it will output plain text
      so criticalError() never tries to serve the source as fallback
    - normalError() will output the file supplied as source, when $serve_original_image_on_fail is set.
      (regardless of whether that exists or not).
      If not set to serve original image, it will output the error either as an image or plain text (it calls criticalError())

    So, to sum up, criticalError() must be called when we know we cannot serve the source as fallback
    Otherwise, call normalError().
    */

    // Critical errors
    private static function criticalError($msg)
    {
        if (!self::$serve_converted_image) {
            echo $msg;
        } else {
            header('Content-type: image/gif');

            // Prevent caching error message
            header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
            header("Cache-Control: post-check=0, pre-check=0", false);
            header("Pragma: no-cache");

            $image = imagecreatetruecolor(620, 200);
            imagestring($image, 1, 5, 5, $msg, imagecolorallocate($image, 233, 214, 291));
            // echo imagewebp($image);
            echo imagegif($image);
            imagedestroy($image);
        }
    }

    // Normal errors
    private static function normalError($msg)
    {
        if (self::$serve_converted_image) {
            if (self::$serve_original_image_on_fail) {
                $ext = array_pop(explode('.', self::$current_conversion_vars['source']));
                switch (strtolower($ext)) {
                    case 'jpg':
                    case 'jpeg':
                        header('Content-type: image/jpeg');
                        break;
                    case 'png':
                        header('Content-type: image/png');
                        break;
                }
                readfile(self::$current_conversion_vars['source']);
            } else {
                self::criticalError($msg);
            }
        } else {
            self::logMessage($msg);
        }
    }

    public static function setPreferredConverters($preferred_converters)
    {
        self::$preferred_converters = $preferred_converters;
    }

    /*
      @param (string) $source: Absolute path to image to be converted (no backslashes). Image must be jpeg or png
      @param (string) $destination: Absolute path (no backslashes)
      @param (int) $quality (optional): Quality of converted file (0-100)
      @param (bool) $strip_metadata (optional): Whether or not to strip metadata. Default is to strip. Not all converters supports this
    */

    // "dirname", but which doesn't localization
    private static function stripFilenameFromPath($path_with_filename)
    {
        $parts = explode('/', $path_with_filename);
        array_pop($parts);
        return implode('/', $parts);
    }

    public static function convert($source, $destination, $quality = 85, $strip_metadata = true)
    {
        // $newstr = filter_var($source, FILTER_SANITIZE_STRING);
        // $source = filter_var($source, FILTER_SANITIZE_MAGIC_QUOTES);

        self::logMessage('self::convert() called');
        self::logMessage('- source: ' . $source);
        self::logMessage('- destination: ' . $destination);
        self::logMessage('- quality: ' . $quality);
        self::logMessage('- strip_metadata: ' . ($strip_metadata ? 'true' : 'false'));
        self::logMessage();

        self::$current_conversion_vars = array();
        self::$current_conversion_vars['source'] =  $source;
        self::$current_conversion_vars['destination'] =  $destination;

        if (self::$serve_converted_image) {
            // If set to serve image, textual content will corrupt the image
            // Therefore, we prevent PHP from outputting error messages
            ini_set('display_errors', 'Off');
        }

        // Test if file extension is valid
        $parts = explode('.', $source);
        $ext = array_pop($parts);

        if (!in_array(strtolower($ext), array('jpg', 'jpeg', 'png'))) {
            self::criticalError("Unsupported file extension: " . $ext);
            return;
        }

        // Test if source file exists
        if (!file_exists($source)) {
            self::criticalError("Source file not found: " . $source);
            return;
        }

        // Prepare destination folder
        $destination_folder = self::stripFilenameFromPath($destination);

        if (!file_exists($destination_folder)) {
            self::logMessage('We need to create destination folder');

            // Find out which permissions to set.
            // We want same permissions as parent folder
            // But which parent? - the parent to the first missing folder
            // (TODO: what to do if this is outside open basedir?
            // see http://php.net/manual/en/ini.core.php#ini.open-basedir)
            $parent_folders = explode('/', $destination_folder);
            $popped_folders = array();
            while (!(file_exists(implode('/', $parent_folders)))) {
                array_unshift($popped_folders, array_pop($parent_folders));
            }
            $closest_existing_folder = implode('/', $parent_folders);

            self::logMessage('Using permissions of closest existing folder (' . $closest_existing_folder . ')');
            $perms = fileperms($closest_existing_folder) & 000777;
            self::logMessage('Permissions are: 0' . decoct($perms));

            if (!mkdir($destination_folder, $perms, true)) {
                self::normalError('Failed creating folder:' . $folder);
                return;
            };
            self::logMessage('Folder created successfully');

            // alas, mkdir does not respect $perms. We have to chmod each created subfolder
            $path = $closest_existing_folder;
            foreach ($popped_folders as $subfolder) {
                $path .= '/' . $subfolder;
                self::logMessage('chmod 0' . decoct($perms) . ' ' . $path);
                chmod($path, $perms);
            }
        }

        // Test if it will be possible to write file
        if (file_exists($destination)) {
            if (!is_writable($destination)) {
                self::normalError('Cannot overwrite file: ' . $destination . '. Check the file permissions.');
                return;
            }
        } else {
            if (!is_writable($destination_folder)) {
                self::normalError('Cannot write file: ' . $destination . '. Check the folder permissions.');
                return;
            }
        }

        // If there is already a converted file at destination, remove it
        // (actually it seems the current converters can handle that, but maybe not future converters)
        if (file_exists($destination)) {
            if (unlink($destination)) {
                self::logMessage('Destination file already exists... - removed');
            } else {
                self::logMessage('Destination file already exists. Could not remove it');
            }
        }

        // Prepare building up an array of converters
        $converters = array();

        // Add preferred converters
        if (count(self::$preferred_converters) > 0) {
            self::logMessage('Preferred converters was set to: ' . implode(', ', self::$preferred_converters));
        } else {
            self::logMessage('No preferred converters was set. Converters will be tried in default order');
        }

        foreach (self::$preferred_converters as $converter) {
            $filename = __DIR__ . '/converters/' . $converter . '/' . $converter . '.php';
            if (file_exists($filename)) {
                $converters[] = $converter;
            } else {
                self::logMessage('<b>the converter "' . $converter . '" that was set as a preferred converter was not found at: "' . $filename . '".</b>');
            }
        }

        // Add converters in the converters dir.
        // - Convention is that the name of the converter equals the dir name
        foreach (scandir(__DIR__ . '/converters') as $file) {
            if (is_dir('converters/' . $file)) {
                if ($file == '.') {
                    continue;
                }
                if ($file == '..') {
                    continue;
                }
                if (in_array($file, $converters)) {
                    continue;
                }

                $converters[] = $file;
            }
        }

        self::logMessage('Order of converters to be tried: <i>' . implode('</i>, <i>', $converters) . '</i>');

        $success = false;
        foreach ($converters as $converter) {
            self::logMessage('<br>trying <b>' . $converter . '</b>');

            $filename = __DIR__ . '/converters/' . $converter . '/' . $converter . '.php';
            self::logMessage('including converter at: "' . $filename . '"');

            include_once($filename);

            if (!function_exists('webpconvert_' . $converter)) {
                self::logMessage('converter not useable - it does not define a function " . $converter . "');
                continue;
            }

            $time_start = microtime(true);
            $result = call_user_func('webpconvert_' . $converter, $source, $destination, $quality, $strip_metadata);
            $time_end = microtime(true);
            self::logMessage('execution time: ' . round(($time_end - $time_start) * 1000) . ' ms');

            if ($result === true) {
                self::logMessage('success!');
                $success = true;
                break;
            } else {
                self::logMessage($result);
            }
        }

        if (!$success) {
            self::normalError('No converters could convert file: ' . $source);
            return;
        }

        if (!file_exists($destination)) {
            self::normalError('Failed saving image to path: ' . $destination);
            return;
        }

        if (self::$serve_converted_image) {
            // Serve the saved file
            header('Content-type: image/webp');
            // Should we add Content-Length header?
            // header('Content-Length: ' . filesize($file));
            readfile($destination);
        }
    }
}
