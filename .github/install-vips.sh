#!/bin/bash

if ! [[ $VIPS_VERSION ]]; then
    export VIPS_VERSION="8.10.6"
fi;

export PATH=$HOME/vips/bin:$PATH
export LD_LIBRARY_PATH=$HOME/vips/lib:$LD_LIBRARY_PATH
export PKG_CONFIG_PATH=$HOME/vips/lib/pkgconfig:$PKG_CONFIG_PATH
export PYTHONPATH=$HOME/vips/lib/python2.7/site-packages:$PYTHONPATH
export GI_TYPELIB_PATH=$HOME/vips/lib/girepository-1.0:$GI_TYPELIB_PATH

vips_site=https://github.com/libvips/libvips/releases/download

set -e

# do we already have the correct vips built? early exit if yes
# we could check the configure params as well I guess
if [ -d "$HOME/vips/bin" ]; then
	installed_version=$($HOME/vips/bin/vips --version)
	escaped_version="${VIPS_VERSION//\./\\.}"
	echo "Need vips-$version"
	echo "Found $installed_version"
	if [[ "$installed_version" =~ ^vips-$escaped_version ]]; then
		echo "Using cached directory"
		exit 0
	fi
fi

rm -rf $HOME/vips
echo "wget: $vips_site/v$VIPS_VERSION/vips-$VIPS_VERSION.tar.gz"
wget $vips_site/v$VIPS_VERSION/vips-$VIPS_VERSION.tar.gz
tar xf vips-$VIPS_VERSION.tar.gz
cd vips-$VIPS_VERSION
CXXFLAGS=-D_GLIBCXX_USE_CXX11_ABI=0 ./configure --prefix=$HOME/vips --disable-debug --disable-dependency-tracking --disable-introspection --disable-static --enable-gtk-doc-html=no --enable-gtk-doc=no --enable-pyvips8=no --without-orc --without-python
make && make install

# Install PHP extension
# ----------------------
yes '' | pecl install vips
