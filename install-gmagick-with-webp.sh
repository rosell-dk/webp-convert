# https://duntuk.com/how-install-graphicsmagick-gmagick-php-extension
gm -version | grep -i 'WebP.*yes' && {
    echo "Gmagick is already compiled with webp. Nothing to do :)" &&
    echo ":)"

}

gm -version | grep -i 'WebP.*yes' || {
    echo "Gmagick is not compiled with webp... Doing that!" &&
    cd /tmp &&
    wget ftp://ftp.graphicsmagick.org/pub/GraphicsMagick/GraphicsMagick-LATEST.tar.gz &&
    tar xvfz GraphicsMagick-LATEST.tar.gz &&
    cd GraphicsMagick-* &&
    ./configure --enable-shared --with-webp=yes &&
    make &&
    make install &&
    gm -version
}

#convert -version | grep 'webp' || {

#convert -list delegate | grep 'webp =>' || {
#}
