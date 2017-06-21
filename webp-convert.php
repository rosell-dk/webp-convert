<?php
// http://test2/webp-convert.php?source=/var/www/test/images/subfolder/logo.jpg&quality=80&preferred_tools=imagewebp,cwebp&debug&destination-root=cold

/*
URL parameters:

source:
Path to source file. Can be absolute or relative (relative to document root). If it starts with "/", it is considered an absolute path.

destination (optional): (TODO)
Path to destination file. Can be absolute or relative (relative to document root). You can choose not to specify destination. In that case, the path will be created based upon source, destination-root and absrel settings. If all these are blank, the destination will be same folder as source, and the filename will have ".webp" appended to it (ie image.jpeg.webp)

destination-root (optional):
Path of destination (relative to document root) or an absolute path. If not supplied, then the converted file will be placed in same folder as the target. Double-dots are allowed, ie "../../webp-cache/images/2017"

quality:
The quality of the generated WebP image, 0-100.

strip_metadata:
0 or 1. If 0, metadata will be stripped. If 1, metadata will be copied (if tool supports it)


preferred_tools (optional):
Set the priority of the tools, that is, the order to try the tools in. You do not have to specify all tools. The tools you specify will move to the top of the list. The script will always try all tools before giving up.
Comma-separated list.
Allowed values: "cwebp", "imagewebp"
Default order is: cwebp, imagewebp

absrel (optional):
Might be relevant in rare occasions where you cannot pass all of the relative path. 

debug (optional):
If set (if "&debug" is appended to the URL), the script will produce text output instead of an image.
*/

error_reporting(E_ALL);
ini_set('display_errors','On');

include( __DIR__ . '/WebPConvertClass.php');
include( __DIR__ . '/WebPConvertPathHelperClass.php');

$source = WebPConvertPathHelper::abspath($_GET['source']);
$destination = WebPConvertPathHelper::get_destination_path($source, $_GET['destination-root']);
//echo "$source<br>$destination<br>";
$quality = (isset($_GET['quality']) ? intval($_GET['quality']) : 85);
//$preferred_tools = (isset($_GET['preferred_tools'])) ? explode(',', $_GET['preferred_tools']) : NULL); 
$preferred_tools = array('imagewebp', 'cwebp');

WebPConvert::$serve_converted_image = !(TRUE);
WebPConvert::set_preferred_tools($preferred_tools);
WebPConvert::convert($source, $destination, $quality);


//echo WebPConvertPathHelper::abspath($_GET['source']) . '<br>';
//echo WebPConvertPathHelper::abspath($_GET['source'], $_SERVER['DOCUMENT_ROOT'] . '/wp');
die();
//$_GET['source'], $_GET['absrel']
//echo WebPConvert;
//die();

/*
$source = '/var/www/test/images/subfolder/logo.jpg';
$destination = '/var/www/test2/cold/test/images/subfolder/logo.jpg.webp';

WebPConvert::$serve_converted_image = !(TRUE);
WebPConvert::set_preferred_tools(array('cwebp','imagewebp'));
WebPConvert::convert($source, $destination, 90);
*/



//strip_metadata



//die();

//logmsg($source);
//logmsg(realpath('.'));
//logmsg(realpath('..'));
//logmsg(canonicalize('images/..////crazyfolder/logo2.jpg'));
//logmsg(canonicalize('images/../crazyfolder/logo2.jpg'));
//logmsg(realpath('images/../images/crazyfolder/logo2.jpg'));

/*logmsg($_GET['source']);
logmsg(realpath($_GET['source']));
logmsg(realpath('/tmp//'));*/


// actually comma is "unsafe" in URLs according to RFC.
// - See speedplanes comment here: https://stackoverflow.com/questions/198606/can-i-use-commas-in-a-url


/*  if (file_exists($dest)) {
    die_with_msg('The webp file already exists. I refuse to overwrite');

