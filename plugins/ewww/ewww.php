<?php


WebPConvert::addTool(
  'ewww',
  function($source, $destination, $quality, $strip_metadata = TRUE) {
    if(!function_exists('imagewebp')) {
      return 'imagewebp() is not available';
    }
  }
);
