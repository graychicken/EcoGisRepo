#!/bin/bash

pushd `dirname $0` > /dev/null
BASE_DIR=`pwd`
popd > /dev/null
BASE_DIR=`/usr/bin/realpath $BASE_DIR/..`

mkdir -p $BASE_DIR/tmp

lang="de_DE it_IT"
php $BASE_DIR/script/tsmarty2c.php $BASE_DIR/templates/admin/*.tpl $BASE_DIR/templates/admin/users/*.tpl > $BASE_DIR/tmp/tpl-gettext-application.c

for ln in $lang; do
    echo "Generating text for $ln"
    xgettext -L C --from-code=utf-8 --join-existing --add-comments --output-dir=$BASE_DIR/lang/$ln/LC_MESSAGES -n $BASE_DIR/tmp/tpl-gettext-application.c
    xgettext -L PHP --from-code=utf-8 --join-existing --add-comments --output-dir=$BASE_DIR/lang/$ln/LC_MESSAGES -n $BASE_DIR/web/admin/*.php $BASE_DIR/lib/*.php $BASE_DIR/lang/*.php
done
