
# Got the script from here:
# https://stackoverflow.com/questions/41138404/how-to-install-newer-imagemagick-with-webp-support-in-travis-ci-container


convert -list delegate | grep 'webp =>' && {
    echo "Imagick is already compiled with webp. Nothing to do :)" &&
    echo ":)"
}

#convert -version | grep 'webp' || {

convert -list delegate | grep 'webp =>' || {
    export CORES=$(nproc) &&
    export LIBWEBP_VERSION=1.0.2 &&
    export IMAGEMAGICK_VERSION=7.0.8-43 &&
    echo "Using $CORES cores for compiling..." &&
    cd /tmp &&
    curl -O https://storage.googleapis.com/downloads.webmproject.org/releases/webp/libwebp-$LIBWEBP_VERSION.tar.gz &&
    tar xzf libwebp-$LIBWEBP_VERSION.tar.gz &&
    cd libwebp-* &&
    ./configure --prefix=$HOME/opt &&
    make -j$CORES &&
    make install -j$CORES &&
    cd /tmp &&
    curl -O https://www.imagemagick.org/download/ImageMagick-$IMAGEMAGICK_VERSION.tar.gz &&
    tar xzf ImageMagick-$IMAGEMAGICK_VERSION.tar.gz &&
    cd ImageMagick-* &&
    ./configure --prefix=$HOME/opt --with-webp=yes &&
    make -j$CORES &&
    make install -j$CORES &&
    $HOME/opt/bin/magick -version | grep $IMAGEMAGICK_VERSION
}
