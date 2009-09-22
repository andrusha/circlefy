/*
    Mixpanel, Inc. (http://mixpanel.com/)
*/

var mpmetrics = {};

mpmetrics.super_properties = {"all": {}, "events": {}, "funnels": {}};

mpmetrics.init = function(token) {
    var mp_protocol = (("https:" == document.location.protocol) ? "https://" : "http://");
    this.token = token;
    this.api_host = mp_protocol + 'api.mixpanel.com';
    try {
        mpmetrics.get_super();
    } catch(err) {}
};

mpmetrics.send_request = function(url, data) {
    var callback = 'mpmetrics.jsonp_callback';
    
    if (url.indexOf("?") > -1) {
        url += "&callback=";
    } else {
        url += "?callback=";
    }
    url += callback + "&";
    
    if (data) { url += this.http_build_query(data); }
    url += '&_=' + new Date().getTime().toString();
    var script = document.createElement("script");
    script.setAttribute("src", url);
    script.setAttribute("type", "text/javascript");
    document.body.appendChild(script);
};

// This is DEPRECATED, do not use.
mpmetrics.log = function(data, callback) {
    if (! data.project) { data.project = this.token; }
    if (data.project && data.category) {
        this.callback = callback;
        data.ip = 1;
        this.send_request(this.api_host + "/log/", data);
    }
};

mpmetrics.track_funnel = function(funnel, step, goal, properties, callback) {
    if (! properties) { properties = {}; }

    properties.funnel = funnel;
    properties.step = parseInt(step, 10);
    properties.goal = goal;
    
    // If step 1 of the funnel, super property track the search keyword throughout the funnel automatically
    if (properties.step == 1) {
        // Only google for now
        if (document.referrer.search('http://(.*)google.com') === 0) {
            var keyword = mpmetrics.get_query_param(document.referrer, 'q');
            if (keyword.length) {
                mpmetrics.register({'mp_keyword' : keyword}, 'funnels');
            }
        }
    }
    
    mpmetrics.track('mp_funnel', properties, callback, "funnels");
};

mpmetrics.get_query_param = function(url, param) {
    // Expects a raw URL
    
    param = param.replace(/[\[]/,"\\\[").replace(/[\]]/,"\\\]");
    var regexS = "[\\?&]" + param + "=([^&#]*)";
    var regex = new RegExp( regexS );
    var results = regex.exec(url);
    if (results === null || (results && typeof(results[1]) != 'string' && results[1].length)) {
        return '';
    } else {
        return unescape(results[1]).replace(/\+/g, ' ');
    }
};

mpmetrics.track = function(event, properties, callback, type) {
    if (!type) { type = "events"; }
    if (!properties) { properties = {}; }
    if (!properties.token) { properties.token = this.token; }
    if (callback) { this.callback = callback; }
    properties.time = this.get_unixtime();
    
    // First add specific super props
    if (type != "all") {
        for (var p in mpmetrics.super_properties[type]) {
            if (!properties[p]) {                
                properties[p] = mpmetrics.super_properties[type][p];
            }
        }
    }
    
    // Then add any general supers that were not in specific 
    if (mpmetrics.super_properties.all) {
        for (p in mpmetrics.super_properties.all) {
            if (!properties[p]) {
                properties[p] = mpmetrics.super_properties.all[p];
            }
        }
    }
    
    var data = {
        'event' : event,
        'properties' : properties
    };
    
    // console.dir(data);
    
    var encoded_data = this.base64_encode(this.json_encode(data)); // Security by obscurity
    
    this.send_request(
        this.api_host + '/track/', 
        {
            'data' : encoded_data, 
            'ip' : 1
        }
    );
};

mpmetrics.register = function(props, type, days) {
    // register a set of super properties to be included in all events and funnels
    if (!type) { type = "all"; }
    if (!days) { days = 7; }
    
    if (props) {
        for (var p in props) {
            if (p) {
                mpmetrics.super_properties[type][p] = props[p];
            }
        }    
    }

    mpmetrics.set_cookie("mp_super_properties", mpmetrics.json_encode(mpmetrics.super_properties), days);
};

mpmetrics.http_build_query = function(formdata, arg_separator) {
    var key, use_val, use_key, i = 0, tmp_arr = [];

    if (!arg_separator) {
        arg_separator = '&';
    }

    for (key in formdata) {
        if (key) {
            use_val = encodeURIComponent(formdata[key].toString());
            use_key = encodeURIComponent(key);
            tmp_arr[i++] = use_key + '=' + use_val;
        }
    }

    return tmp_arr.join(arg_separator);
};

mpmetrics.get_unixtime = function() {
    return parseInt(new Date().getTime().toString().substring(0,10), 10) - 60;
};

mpmetrics.jsonp_callback = function(response) {
    if (this.callback) { 
        this.callback(response); 
        this.callback = false; 
    }
};

mpmetrics.json_encode = function(mixed_val) {    
    var indent;
    var value = mixed_val;
    var i;

    var quote = function (string) {
        var escapable = /[\\\"\x00-\x1f\x7f-\x9f\u00ad\u0600-\u0604\u070f\u17b4\u17b5\u200c-\u200f\u2028-\u202f\u2060-\u206f\ufeff\ufff0-\uffff]/g;
        var meta = {    // table of character substitutions
            '\b': '\\b',
            '\t': '\\t',
            '\n': '\\n',
            '\f': '\\f',
            '\r': '\\r',
            '"' : '\\"',
            '\\': '\\\\'
        };

        escapable.lastIndex = 0;
        return escapable.test(string) ?
        '"' + string.replace(escapable, function (a) {
            var c = meta[a];
            return typeof c === 'string' ? c :
            '\\u' + ('0000' + a.charCodeAt(0).toString(16)).slice(-4);
        }) + '"' :
        '"' + string + '"';
    };

    var str = function(key, holder) {
        var gap = '';
        var indent = '    ';
        var i = 0;          // The loop counter.
        var k = '';          // The member key.
        var v = '';          // The member value.
        var length = 0;
        var mind = gap;
        var partial = [];
        var value = holder[key];

        // If the value has a toJSON method, call it to obtain a replacement value.
        if (value && typeof value === 'object' &&
            typeof value.toJSON === 'function') {
            value = value.toJSON(key);
        }
        
        // What happens next depends on the value's type.
        switch (typeof value) {
            case 'string':
                return quote(value);

            case 'number':
                // JSON numbers must be finite. Encode non-finite numbers as null.
                return isFinite(value) ? String(value) : 'null';

            case 'boolean':
            case 'null':
                // If the value is a boolean or null, convert it to a string. Note:
                // typeof null does not produce 'null'. The case is included here in
                // the remote chance that this gets fixed someday.

                return String(value);

            case 'object':
                // If the type is 'object', we might be dealing with an object or an array or
                // null.
                // Due to a specification blunder in ECMAScript, typeof null is 'object',
                // so watch out for that case.
                if (!value) {
                    return 'null';
                }

                // Make an array to hold the partial results of stringifying this object value.
                gap += indent;
                partial = [];

                // Is the value an array?
                if (Object.prototype.toString.apply(value) === '[object Array]') {
                    // The value is an array. Stringify every element. Use null as a placeholder
                    // for non-JSON values.

                    length = value.length;
                    for (i = 0; i < length; i += 1) {
                        partial[i] = str(i, value) || 'null';
                    }

                    // Join all of the elements together, separated with commas, and wrap them in
                    // brackets.
                    v = partial.length === 0 ? '[]' :
                    gap ? '[\n' + gap +
                    partial.join(',\n' + gap) + '\n' +
                    mind + ']' :
                    '[' + partial.join(',') + ']';
                    gap = mind;
                    return v;
                }

                // Iterate through all of the keys in the object.
                for (k in value) {
                    if (Object.hasOwnProperty.call(value, k)) {
                        v = str(k, value);
                        if (v) {
                            partial.push(quote(k) + (gap ? ': ' : ':') + v);
                        }
                    }
                }

                // Join all of the member texts together, separated with commas,
                // and wrap them in braces.
                v = partial.length === 0 ? '{}' :
                gap ? '{' + partial.join(',') + '' +
                mind + '}' : '{' + partial.join(',') + '}';
                gap = mind;
                return v;
        }
    };

    // Make a fake root object containing our value under the key of ''.
    // Return the result of stringifying the value.
    return str('', {
        '': value
    });
};

mpmetrics.base64_encode = function(data) {        
    var b64 = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=";
    var o1, o2, o3, h1, h2, h3, h4, bits, i = 0, ac = 0, enc="", tmp_arr = [];

    if (!data) {
        return data;
    }

    data = this.utf8_encode(data+'');
    
    do { // pack three octets into four hexets
        o1 = data.charCodeAt(i++);
        o2 = data.charCodeAt(i++);
        o3 = data.charCodeAt(i++);

        bits = o1<<16 | o2<<8 | o3;

        h1 = bits>>18 & 0x3f;
        h2 = bits>>12 & 0x3f;
        h3 = bits>>6 & 0x3f;
        h4 = bits & 0x3f;

        // use hexets to index into b64, and append result to encoded string
        tmp_arr[ac++] = b64.charAt(h1) + b64.charAt(h2) + b64.charAt(h3) + b64.charAt(h4);
    } while (i < data.length);
    
    enc = tmp_arr.join('');
    
    switch( data.length % 3 ){
        case 1:
            enc = enc.slice(0, -2) + '==';
        break;
        case 2:
            enc = enc.slice(0, -1) + '=';
        break;
    }

    return enc;
};

mpmetrics.utf8_encode = function (string) {
    string = (string+'').replace(/\r\n/g, "\n").replace(/\r/g, "\n");

    var utftext = "";
    var start, end;
    var stringl = 0;

    start = end = 0;
    stringl = string.length;
    for (var n = 0; n < stringl; n++) {
        var c1 = string.charCodeAt(n);
        var enc = null;

        if (c1 < 128) {
            end++;
        } else if((c1 > 127) && (c1 < 2048)) {
            enc = String.fromCharCode((c1 >> 6) | 192) + String.fromCharCode((c1 & 63) | 128);
        } else {
            enc = String.fromCharCode((c1 >> 12) | 224) + String.fromCharCode(((c1 >> 6) & 63) | 128) + String.fromCharCode((c1 & 63) | 128);
        }
        if (enc !== null) {
            if (end > start) {
                utftext += string.substring(start, end);
            }
            utftext += enc;
            start = end = n+1;
        }
    }

    if (end > start) {
        utftext += string.substring(start, string.length);
    }

    return utftext;
};

mpmetrics.set_cookie = function(c_name,value,expiredays) {
    var exdate=new Date();
    exdate.setDate(exdate.getDate()+expiredays);
    document.cookie=c_name+ "=" +escape(value)+
    ((expiredays===null) ? "" : ";expires="+exdate.toGMTString()) + 
    "; path=/";
};

mpmetrics.get_cookie = function (c_name) {
    if (document.cookie.length>0) {
        var c_start=document.cookie.indexOf(c_name + "=");
        if (c_start!=-1) {
            c_start=c_start + c_name.length+1;
            var c_end=document.cookie.indexOf(";",c_start);
            if (c_end==-1) { c_end=document.cookie.length; }
            return unescape(document.cookie.substring(c_start,c_end));
        }
    }
    return "";
};

mpmetrics.get_super = function() {
    var cookie_props = eval('(' + mpmetrics.get_cookie("mp_super_properties") + ')');
    
    if (cookie_props) {
        for (var i in cookie_props) {
            if (i) { mpmetrics.super_properties[i] = cookie_props[i]; }
        }
    }
};
