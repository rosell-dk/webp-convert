# Installing Imagick extension with WebP support

## MX-19.4
I succeeded by simply doing the following after installing imagemagick, libwebp and libwebp-dev:
```
sudo apt install php-imagick
sudo service apache2 restart
```

## Ubuntu 16.04
In order to get imagick with WebP on Ubuntu 16.04, you (currently) need to:
1. [Compile libwebp from source](https://developers.google.com/speed/webp/docs/compiling)
2. [Compile imagemagick from source](https://www.imagemagick.org/script/install-source.php) (```./configure --with-webp=yes```)
3. Compile php-imagick from source, phpize it and add ```extension=/path/to/imagick.so``` to php.ini

## Ubuntu 18.04 (from source)
A simple `sudo apt-get install php-imagick` unfortunately does not give you webp support.
Again, you must:

### 1. Compile libwebp from source
Instructions are [here](https://developers.google.com/speed/webp/docs/compiling).
In short, you need to:
```
sudo apt-get install libjpeg-dev libpng-dev
wget https://storage.googleapis.com/downloads.webmproject.org/releases/webp/libwebp-1.1.0.tar.gz
tar xvzf libwebp-1.1.0.tar.gz
cd into the dir
./configure
make
sudo make install
```

### 2. Compile *imagemagick* from source, configured with *webp*
See tutorial [here](https://linuxconfig.org/how-to-install-imagemagick-7-on-ubuntu-18-04-linux), but configure with *webp* (`./configure --with-webp=yes`)

```
sudo apt-get update
sudo apt build-dep imagemagick
wget https://imagemagick.org/download/ImageMagick.tar.gz
tar xvzf ImageMagick.tar.gz
cd into the dir
./configure --with-webp=yes
sudo make
sudo make install
sudo ldconfig /usr/local/lib
sudo identify -version   # to check if installed ok
make check  # optional run in-depth check
```
Check it this way: `identify -list format |  grep WEBP`
- It should print a line

### 3a. Install extension with pecl
First find out which version of PHP you are using and the location of the relevant *php.ini* file. Both of these can be obtained with `phpinfo();`. Next do the following (but alter to use the info you just collected):

```
sudo apt-get update
sudo apt-get install imagemagick gcc libmagickwand-dev php-pear php7.2-dev
sudo pecl install imagick
sudo echo "extension=imagick.so" >> /etc/php/7.2/apache2/php.ini
sudo service apache2 restart
```
Related:
https://askubuntu.com/questions/769396/how-to-install-imagemagick-for-php7-on-ubuntu-16-04


### 3b. Alternively to using pecl, compile php-imagick from source
https://github.com/mkoppanen/imagick
First find out which version of PHP you are using and the location of the relevant *php.ini* file. Both of these can be obtained with `phpinfo();`. Next do the following (but alter to use the info you just collected):

```
wget https://pecl.php.net/get/imagick-3.4.3.tgz
tar xvzf imagick-3.4.3.tgz
cd into the dir
sudo /usr/bin/phpize7.2      # note: find you version of phpize with locate phpize
./configure
make
make install
sudo echo "extension=imagick.so" >> /etc/php/7.2/apache2/php.ini
sudo service apache2 restart
```
