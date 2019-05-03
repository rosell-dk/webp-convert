# https://duntuk.com/how-install-graphicsmagick-gmagick-php-extension
gm -version | grep -i 'WebP.*yes' && {
    echo "Gmagick is already compiled with webp. Nothing to do :)" &&
    echo ":)"

}


gm -version | grep -i 'WebP.*yes' || {
    echo "Gmagick is not compiled with webp... Doing that!" &&
    cd /tmp &&
    #wget ftp://ftp.graphicsmagick.org/pub/GraphicsMagick/GraphicsMagick-LATEST.tar.gz &&
    #tar xvfz GraphicsMagick-LATEST.tar.gz &&
    curl -O https://sourceforge.net/projects/graphicsmagick/files/graphicsmagick/1.3.31/GraphicsMagick-1.3.31.tar.gz &&
    tar zxvf GraphicsMagick-1.3.31.tar.gz &&
    cd GraphicsMagick-* &&
    ./configure --prefix=$HOME/opt --with-webp=yes &&
    make &&
    make install &&
    gm -version
}

#convert -version | grep 'webp' || {

#convert -list delegate | grep 'webp =>' || {
#}
