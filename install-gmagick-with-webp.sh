# https://duntuk.com/how-install-graphicsmagick-gmagick-php-extension
# https://gist.github.com/basimhennawi/21c39f9758b0b1cb5e0bd5ee08b5be58
# https://github.com/rosell-dk/webp-convert/wiki/Installing-gmagick-extension

#if [ -d "$HOME/vips/bin" ]; then
#fi;


$HOME/opt/bin/gm -version | grep -i 'WebP.*yes' && {
    gmagick_installed_with_webp=1
}

if [[ $gmagick_installed_with_webp == 1 ]]; then
    echo "Gmagick is already compiled with webp. Nothing to do :)"
    echo ":)"
else
    echo "Gmagick is is not installed or not compiled with webp."
    compile_libwebp=1
    compile_gmagick=1
fi;
#ls $HOME/opt/bin


cores=$(nproc)
LIBWEBP_VERSION=1.0.2

if [[ $compile_libwebp == 2 ]]; then
    echo "We are going to be compiling libwebp..."
    echo "Using $cores cores."
    echo "Downloading libwebp version $LIBWEBP_VERSION"
    cd /tmp
    curl -O https://storage.googleapis.com/downloads.webmproject.org/releases/webp/libwebp-$LIBWEBP_VERSION.tar.gz
    tar xzf libwebp-$LIBWEBP_VERSION.tar.gz
    cd libwebp-*

    echo "./configure --prefix=$HOME/opt"
    ./configure --prefix=$HOME/opt

    echo "make -j$CORES"
    make -j$CORES

    echo "make install -j$CORES"
    make install -j$CORES
fi;

if [[ $compile_gmagick == 2 ]]; then
    echo "Compiling Gmagick"
    echo "Using $cores cores."
    cd /tmp
    echo "Downloading GraphicsMagick-LATEST.tar.gz"
    wget http://78.108.103.11/MIRROR/ftp/GraphicsMagick/GraphicsMagick-LATEST.tar.gz
    tar xfz GraphicsMagick-LATEST.tar.gz
    cd GraphicsMagick-*

    echo "Configuring"
    ./configure --prefix=$HOME/opt --enable-shared --with-webp=yes

    echo "make -j$CORES"
    make -j$CORES

    echo "make install -j$CORES"
    make install -j$CORES
fi;


#./configure --prefix=$HOME/opt --with-webp=yes &&

#$HOME/opt/bin/gm -version

#convert -version | grep 'webp' || {

#convert -list delegate | grep 'webp =>' || {
#}
##libgraphicsmagick1-dev
