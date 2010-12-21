#/bin/sh
JS_PATH=static/js
CSS_PATH=static/css

function compress_js {
    echo "Combining files $@"
    cat  $@ > $JS_PATH/_compressed_temp.js

    echo "YUI-compressing..."
    #java -jar garbage/yuicompressor-2.4.2.jar --type js $JS_PATH/_compressed_temp.js > $JS_PATH/_yui_temp.js
    java -jar garbage/compiler.jar --js $JS_PATH/_compressed_temp.js --js_output_file $JS_PATH/_yui_temp.js
    rm $JS_PATH/_compressed_temp.js

    echo "GZip-compressing..."
    gzip -9 -f $JS_PATH/_yui_temp.js
    echo ""
}

function compress_js_closure {
    echo "Combining files $@"
    cat  $@ > $JS_PATH/_compressed_temp.js

    echo "Closure-compilig..."
    java -jar garbage/compiler.jar --compilation_level SIMPLE_OPTIMIZATIONS --js $JS_PATH/_compressed_temp.js --js_output_file $JS_PATH/_yui_temp.js
    rm $JS_PATH/_compressed_temp.js

    echo "GZip-compressing..."
    gzip -9 -f $JS_PATH/_yui_temp.js
    echo ""
}

function compress_css {
    echo "Combining files $@"
    cat  $@ > $CSS_PATH/_compressed_temp.css

    echo "YUI-compressing..."
    java -jar garbage/yuicompressor-2.4.2.jar --type css $CSS_PATH/_compressed_temp.css > $CSS_PATH/_yui_temp.css
    rm $CSS_PATH/_compressed_temp.css

    echo "GZip-compressing..."
    gzip -9 -f $CSS_PATH/_yui_temp.css
    echo ""
}

echo "Compressing libs"
compress_js_closure static/js/mootools-core.js static/js/mootools-more.js static/js/libs.js static/js/validators.js
mv -f $JS_PATH/_yui_temp.js.gz $JS_PATH/_libs.js.gz

echo "Compressing main code"
compress_js_closure static/js/main.js static/js/push.js static/js/search.js static/js/modal.js static/js/Swiff.Uploader.js static/js/user_edit.js static/js/group_create.js static/js/follow.js static/js/notification.js static/js/post.js
mv -f $JS_PATH/_yui_temp.js.gz $JS_PATH/_main.js.gz

echo "Compressing optional code"
compress_js_closure static/js/feed.js static/js/sidebar.js static/js/group_edit.js
mv -f $JS_PATH/_yui_temp.js.gz $JS_PATH/_optional.js.gz

echo "Compressing css"
compress_css static/css/main.css static/css/roar.css static/css/modal.css
mv -f $CSS_PATH/_yui_temp.css.gz $CSS_PATH/_main.css.gz
