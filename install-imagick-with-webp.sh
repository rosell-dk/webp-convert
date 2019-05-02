
convert -version | grep 'webp' && {
    echo "Imagick is not already compiled with webp. Nothing to do :)" &&
    echo ":)"
}

convert -version | grep 'webp' || {
    export CORES=$(nproc) &&
    export LIBWEBP_VERSION=1.0.2 &&
    export IMAGEMAGICK_VERSION=7.0.8-43 &&
    echo "Using $CORES cores for compiling..." &&
    cd /tmp &&
    curl -O https://storage.googleapis.com/downloads.webmproject.org/releases/webp/libwebp-$LIBWEBP_VERSION.tar.gz &&
    tar xvzf libwebp-$LIBWEBP_VERSION.tar.gz &&
    cd libwebp-* &&
    ./configure --prefix=$HOME/opt &&
    make -j$CORES &&
    make install -j$CORES &&
    cd /tmp &&
    curl -O https://www.imagemagick.org/download/ImageMagick-$IMAGEMAGICK_VERSION.tar.gz &&
    tar xvzf ImageMagick-$IMAGEMAGICK_VERSION.tar.gz &&
    cd ImageMagick-* &&
    ./configure --prefix=$HOME/opt &&
    make -j$CORES &&
    make install -j$CORES &&
    $HOME/opt/bin/magick -version | grep $IMAGEMAGICK_VERSION
}
