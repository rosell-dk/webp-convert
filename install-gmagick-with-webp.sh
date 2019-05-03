
gm -version | grep -i 'WebP.*yes' && {
    echo "Gmagick is already compiled with webp. Nothing to do :)" &&
    echo ":)"
}

gm -version | grep -i 'WebP.*yes' || {
    echo "Gmagick is not compiled with webp... " &&
    echo ":("
}

#convert -version | grep 'webp' || {

#convert -list delegate | grep 'webp =>' || {
#}
