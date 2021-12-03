# Installing GMagick PHP extension with WebP support

See:
https://github.com/rosell-dk/webp-convert/issues/37

## MX-19.4
I succeeded by simply doing the following after installing graphicsmagick, libwebp and libwebp-dev:
```
sudo apt install php-gmagick
sudo service apache2 restart
```
Note: For some reason this disables the imagick extension. It seems they cannot both be installed at the same time.


## Ubuntu 18.04, using *PECL*
In Ubuntu 18.04, you will not have to do any special steps in order to compile with webp :)

1. Find out which version of PHP you are using and the location of the relevant php.ini file. Both of these can be obtained with `phpinfo();`
2. Find out which is the latest version of *gmagick* on pecl. https://pecl.php.net/package/gmagick
3. Do the following - but alter to use the info you just collected

```
sudo apt-get update
sudo apt-get install graphicsmagick gcc libgraphicsmagick1-dev php-pear php7.2-dev
sudo pecl install gmagick-2.0.5RC1
sudo echo "extension=gmagick.so" >> /etc/php/7.2/apache2/php.ini
sudo service apache2 restart
```

Notes:
- The php-pear contains *pecl*.
- *php7.2-dev* provides *phpize*, which is needed by pecl. Use *php7.1-dev*, if you are on PHP 7.1
- We do not simply do a `pecl install gmagick` because the latest package is in beta, and pecl would not allow. You should however be able to do *pecl install gmagick-beta*, which should install the latest beta.
- If you are on *fpm*, remember to restart that as well (ie `sudo service php7.2-fpm restart`)

## Plesk
https://support.plesk.com/hc/en-us/articles/115003511013-How-to-install-Gmagick-PHP-extension-on-Ubuntu-Debian-

## From source
https://duntuk.com/how-install-graphicsmagick-gmagick-php-extension
