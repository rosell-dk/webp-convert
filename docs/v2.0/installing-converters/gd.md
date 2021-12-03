# Installing Gd extension with WebP support

## Ubuntu 18.04

On Ubuntu 18.04, I did not have to do anything special to configure Gd for WebP support. The following worked right away:
```
sudo apt-get install php7.2-gd
```

## Ubuntu 16.04
The official page with installation instructions is [available here](http://il1.php.net/manual/en/image.installation.php)

In summary:

PHP 5.5.0:
To get WebP support for `gd` in PHP 5.5.0, PHP must be configured with the `--with-vpx-dir` flag.

PHP >7.0.0:
PHP has to be configured with the `--with-webp-dir` flag
