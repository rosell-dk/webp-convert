# https://duntuk.com/how-install-graphicsmagick-gmagick-php-extension
# https://gist.github.com/basimhennawi/21c39f9758b0b1cb5e0bd5ee08b5be58
# https://github.com/rosell-dk/webp-convert/wiki/Installing-gmagick-extension

gm -version | grep -i 'WebP.*yes' && {
    echo "Gmagick is already compiled with webp. Nothing to do :)" &&
    echo ":)"

}

#ls $HOME/opt/bin

gm -version | grep -i 'WebP.*yes' || {
    echo "Gmagick is not compiled with webp... Doing that!" &&
    cd /tmp &&
    #wget ftp://ftp.graphicsmagick.org/pub/GraphicsMagick/GraphicsMagick-LATEST.tar.gz &&
    #tar xvfz GraphicsMagick-LATEST.tar.gz &&
    #curl -O https://sourceforge.net/projects/graphicsmagick/files/graphicsmagick/1.3.31/GraphicsMagick-1.3.31.tar.gz &&
    #tar zxvf GraphicsMagick-1.3.31.tar.gz &&
    wget http://78.108.103.11/MIRROR/ftp/GraphicsMagick/GraphicsMagick-LATEST.tar.gz &&
    tar xvfz GraphicsMagick-LATEST.tar.gz &&
    cd GraphicsMagick-* &&
    ./configure --prefix=$HOME/opt --with-webp=yes &&
    make &&
    make install &&
    #$HOME/opt/bin/gm -version
}

#convert -version | grep 'webp' || {

#convert -list delegate | grep 'webp =>' || {
#}
