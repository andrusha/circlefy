#/bin/sh
JS_PATH=static/js
CSS_PATH=static/css

#append string to every array element
function append {
    str=$1
    eval array=("\${$2[@]}")

    for x in `seq 0 $[ ${#array[@]} - 1 ]`; do
        array[$x]=$str${array[$x]}
    done

    eval $2="(${array[@]})"
}

function compress_js {
    OUTPUT=$1
    TEMP="$OUTPUT"_temp
    shift 1

    args=("$@")
    append $JS_PATH/ args
    
    echo "Combining files ${args[@]}"
    cat  ${args[@]} > $TEMP 

    echo "Closure-compilig..."
    java -jar garbage/compiler.jar --compilation_level SIMPLE_OPTIMIZATIONS --js $TEMP --js_output_file $OUTPUT 
    rm $TEMP 

    echo "GZip-compressing..."
    gzip -9 -f $OUTPUT 
    echo ""
}

function compress_css {
    OUTPUT=$1
    TEMP="$OUTPUT"_temp
    shift 1

    args=("$@")
    append $CSS_PATH/ args

    echo "Combining files ${args[@]}"
    cat  ${args[@]} > $TEMP 

    echo "YUI-compressing..."
    java -jar garbage/yuicompressor-2.4.2.jar --type css $TEMP > $OUTPUT 
    rm $TEMP 

    echo "GZip-compressing..."
    gzip -9 -f $OUTPUT 
    echo ""
}

echo "Compressing libs"
compress_js $JS_PATH/_libs.js mootools-core.js mootools-more.js libs.js validators.js

echo "Compressing main code"
compress_js $JS_PATH/_main.js main.js push.js search.js modal.js Swiff.Uploader.js user_edit.js group_create.js follow.js notification.js post.js

echo "Compressing optional code"
compress_js $JS_PATH/_optional.js feed.js sidebar.js group_edit.js

echo "Compressing css"
compress_css $CSS_PATH/_main.css main.css roar.css modal.css
