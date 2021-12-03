# Installing vips extension

### Step 1: Install the vips library
Follow the instructions on the [vips library github page](https://github.com/libvips/libvips/)

Don't forget to install required packages before running `./configure`:
```
sudo apt-get install libglib2.0-dev pkg-config build-essential libexpat1-dev libjpeg-dev libpng-dev libwebp-dev gobject-introspection libgs-dev
```

### Step 2: Install the vips extension

```
sudo pecl install vips
```
&ndash; And add the following to the relevant php.ini:
```
extension=vips
```

(or `extension=vips.so` if you are in older PHP)

The vips extension is btw [also on github](https://github.com/libvips/php-vips-ext):
