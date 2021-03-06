/*global Events, window, typeOf, Options, Chain, Template, document, Elements, Fx, Element, Browser, Hash, Request, console, Type*/

if (!window.console) {
    window.console = {
        log: function () {}, 
        debug: function () {}, 
        info: function () {}, 
        warn: function () {}, 
        error: function () {},
        assert: function () {}, 
        dir: function () {}, 
        dirxml: function () {}, 
        trace: function () {}, 
        time: function () {},
        timeEnd: function () {}, 
        profile: function () {}, 
        profileEnd: function () {}
    };
}

/* There would be self-coded/customized mootools-based libraries
 * so we can use mootools builder easily */

String.implement({
    plural: function (num) {
        return num > 1 ? this + 's' : this;
    }
});

/*
Script: Template.jx
    Basic templating system.

Copyright and License:
    Copyright, 2010, Mark Obcena. MIT-style license.

Modified:
    Andrew Korzhuev

Acknowledgements:
    Original inspired by Charlie Savages' simple templating engine.

*/

(function () {
    this.Template = new Class({
        pattern:  /<#[:|=]?(.*?)#>/g,
        outkey:   ":",
        loopExp:  /(?:for|each|foreach)\s+\((?:var\s*)?(.*?)\s+from\s+(.*?)\s*\)\s*(?:\{|:)\s*(.*?)/g,
        loopEnds: /end(each|for|foreach);/g,
        condExp:  /(if|else|elseif)(.*):/g,
        condEnds: /endif;/g,
               
        parseConds: function (template) {
            return template.replace(
                    this.condExp,
                    function (whole, tag, rest) {
                        return (['else', 'elseif'].contains(tag) ? '} ' : '') + tag + rest + '{\n';
                    }
                ).replace(this.condEnds, '}\n');
        },
               
        parseLoops: function (template) {
            return template.replace(
                    this.loopExp,
                    function (whole, var_name, source_name, inner) {
                        return 'Object.each(' + source_name + ', function (' + var_name + ') {\n' +
                               'if (!["object", "array"].contains(typeOf(' + var_name + '))) return; \n' + inner;
                    }
                ).replace(this.loopEnds, '}, this);\n');
        },
        
        'escape': function (template) {
            return template.replace(/"/g, '\\"').replace(/\r|\n\s*/g, '\\n');
        },

        clean: function (template) {
            return template.replace(/(^[\s\n\r\t]*<!--|-->[\s\n\r\t]*$)/g, '');
        },
        
        build: function (template) {
            template = this.escape(this.clean(template)).replace(
                this.pattern,
                function (whole, line) {
                    if (whole.charAt(2) == this.outkey) {
                        line = 'buffer += ' + line + ';\n';
                    } else {
                        line = this.parseLoops(this.parseConds(line)).trim();
                    }
                    
                    return '";\n' + line + 'buffer += "';
                }.bind(this)
            );
            
            return ["var $ = this, buffer = '', print = function(data){ buffer += data; };\n",
                    '\nbuffer += "', template, '";\n', 
                    'return buffer;\n'].join("");
        },
        
        compile: function (template) {
            var code = this.build(template);
            return new Function(code);
        }
    });
})();

/*
Script: Observer.js
    A Registry Class

License:
    Copyright 2010, Mark Obcena <markobcena@gmail.com>
    MIT-style license.

Modified:
    Andrew Korzhuev <wolfon@gmail.com>
*/

var Observer = new Class({

    Extends: Events,

    $mixins: {},
    $done: {},

    mixin: function (obj) {
        if (!obj.name) {
            return;
        }

        this.$mixins[obj.name] = obj;
        if (obj.init) {
            window.addEvent('domready', obj.init.bind(obj));
        }

        delete obj.name;
        return this;
    },

    register: function (obj) {
        var self = this;
        if (obj.init)  {
            window.addEvent('domready', obj.init.bind(obj));
        }

        if (obj.mixins) {
            var mixins = obj.mixins.split(';'),
                len = mixins.reverse().length;
            while (len--) {
                var mixin = this.$mixins[mixins[len].trim()];
                if (mixin) {
                    Object.append(obj, mixin);
                }
            }
            delete obj.mixins;
        }
        obj.obs = this;
        obj.publish = this.publish.bind(this);
        obj.subscribe = this.subscribe.bind(this);
        return obj;
    },

    publish: function (type, args, once) {
        if (!this.$events || !this.$events[type]) {
            return this;
        }

        if (once) {
            this.$done[type] = args || true;
        }

        return this.fireEvent(type, args);
    },

    subscribe: function (obj, value) {
        var keys, len, curr, 
            _tmp = {},
            results = [];
        if (typeOf(obj) == 'string') {
            _tmp[obj] = value; 
            obj = _tmp;
        }

        for (var key in obj) {
            if (key !== 'prototype') {
                keys = key.split(';');
                len = keys.reverse().length;
                while (len--) {
                    curr = keys[len].trim();
                    if (this.$done[curr]) {
                        results.push(obj[key].apply(this, this.$done[curr]));
                    } else {
                        this.addEvent.call(this, curr, obj[key]);
                    }
                }
            }
        }
        return results;
    }
});

/**
 * Roar - Notifications
 *
 * Inspired by Growl
 *
 * @version     1.0.2
 *
 * @license     MIT-style license
 * @author      Harald Kirschner <mail [at] digitarald.de>
 * @modified    Andrew Korzhuev <wolfon@gmail.com>
 * @modified    Ignacio Freiberg <hookdump@gmail.com>
 *
 * @copyright   Author
 */

var Roar = new Class({

    Implements: [Options, Events, Chain],

    options: {
        duration: 10000,
        position: 'upperLeft',
        container: null,
        bodyFx: null,
        itemFx: null,
        margin: {x: 10, y: 10},
        offset: 10,
        className: 'roar',
        onShow: function () {},
        onHide: function () {},
        onRender: function () {}
    },

    initialize: function (options) {
        this.setOptions(options);
        this.items = [];
        this.templater = new Template();
        this.container = $(this.options.container) || document;
    },

    'alert': function (title, message, options) {
        var params = Array.link(arguments, {title: Type.isString, message: Type.isString, options: Type.isObject});
        options = params.options || {};

        if (!this.template) {
            this.template = this.templater.compile($('template-notify').innerHTML.cleanup());
        }

        var data = {title: params.title, text: params.message};
        if (options.user) {
            data.user_id = options.user;
        }

        if (options.group) {
            data.group_id = options.group;
        }

        var elements = Elements.from(this.template.apply(data));
        if (options.color) {
            elements[0].getElement('div.roar-bg').setStyle('background-color', options.color);
        }

        return this.inject(elements[0], options);
    },

    inject: function (item, options) {
        if (options.once && $('roar-' + options.once)) {
            return;
        }

        if (!this.body) {
            this.render();
        }

        var offset = [-this.options.offset, 0];
        var last = this.items.getLast();
        if (last) {
            offset[0] = last.retrieve('roar:offset');
            offset[1] = offset[0] + last.offsetHeight + this.options.offset;
        }

        if (options.once) {
            item.id = 'roar-' + options.once;
        }

        var to = {'opacity': 1};

        // I added this check to support both Absolute Position and Left/Right/Top/Bottom Position. [Ignacio]
        var isArray = options.position instanceof Array;
        if (isArray) {
            // Andrew's absolute position code ---
            if (options.position) {
                item.setStyle('left', options.position[0] - this.body.offsetLeft);
                item.setStyle('top', options.position[1] - this.body.offsetTop);
                to.opacity = [0.7, 1];
            }
        } else {
            // Original position code ---
            to[this.align.y] = offset;
            item.setStyle(this.align.x, 0);
        }

        item.store('roar:offset', offset[1]).set('morph', Object.merge({
            unit: 'px',
            link: 'cancel',
            onStart: Chain.prototype.clearChain,
            transition: Fx.Transitions.Back.easeOut
        }, this.options.itemFx));

        var remove = function () {
                this.remove.delay(10, this, [item]);
            }.bind(this);
        this.items.push(item.addEvent('click', remove));

        item.set('over', false);
        
        if (this.options.duration) {
            var duration = this.options.duration;
            if (options.duration) {
                duration = options.duration;
            }

            var over = false;
            var trigger = function () {
                trigger = null;
                if (!item.over) {
                    remove();
                }
            }.delay(duration);
            item.addEvents({
                mouseover: function () {
                    item.over = true;
                },
                mouseout: function (e) {
                    var fromOutside = !e.relatedTarget.getParents('div.roar').length;
                    if (!fromOutside) {
                        return;
                    }

                    item.over = false;
                    if (!trigger) {
                        remove();
                    }
                }
            });
        }

        item.inject(this.body).morph(to);
        return this.fireEvent('onShow', [item, this.items.length]);
    },

    remove: function (item) {
        var index = this.items.indexOf(item);
        if (index == -1) {
            return this;
        }

        this.items.splice(index, 1);
        item.removeEvents();
        var to = {opacity: 0};
        to[this.align.y] = item.getStyle(this.align.y).toInt() - item.offsetHeight - this.options.offset;
        item.morph(to).get('morph').chain(item.destroy.bind(item));
        return this.fireEvent('onHide', [item, this.items.length]).callChain(item);
    },

    empty: function () {
        while (this.items.length) {
            this.remove(this.items[0]);
        }
        return this;
    },

    render: function () {
        this.position = this.options.position;
        if (typeOf(this.position) == 'string') {
            var position = {x: 'center', y: 'center'};
            this.align = {x: 'left', y: 'top'};

            if ((/left|west/i).test(this.position)) {
                position.x = 'left';
            } else if ((/right|east/i).test(this.position)) {
                this.align.x = position.x = 'right';
            }

            if ((/upper|top|north/i).test(this.position)) { 
                position.y = 'top';
            } else if ((/bottom|lower|south/i).test(this.position)) {
                this.align.y = position.y = 'bottom';
            }
            this.position = position;
        }
        this.body = new Element('div', {'class': 'roar-body'}).inject(document.body);

        this.moveTo = this.body.setStyles.bind(this.body);
        this.reposition();
        if (this.options.bodyFx) {
            var morph = new Fx.Morph(this.body, Object.merge({
                unit: 'px',
                chain: 'cancel',
                transition: Fx.Transitions.Circ.easeOut
            }, this.options.bodyFx));
            this.moveTo = morph.start.bind(morph);
        }
        var repos = this.reposition.bind(this);
        window.addEvents({
            scroll: repos,
            resize: repos
        });
        this.fireEvent('onRender', this.body);
    },

    reposition: function () {
        var max = document.getCoordinates(), scroll = document.getScroll(), margin = this.options.margin;
        max.left += scroll.x;
        max.right += scroll.x;
        max.top += scroll.y;
        max.bottom += scroll.y;
        var rel = (typeOf(this.container) == 'element') ? this.container.getCoordinates() : max;

        //dirty chrome hack (cuz it returns document width without scrollbars)
        if (Browser.chrome || Browser.safari || Browser.opera) {
            margin.x = 20;
            margin.y = 20;
        }
        this.moveTo({
            left: (this.position.x == 'right') ? (Math.min(rel.right, max.right) - margin.x)
                                               : (Math.max(rel.left, max.left) + margin.x),
            top: (this.position.y == 'bottom') ? (Math.min(rel.bottom, max.bottom) - margin.y)
                                               : (Math.max(rel.top, max.top) + margin.y)
        });
    }

});

/*
Script: Tap.Extensions.js
    Additional native methods used in Tap
*/

Element.Events.outerClick = {

    base : 'click',

    condition : function (event) {
        event.stopPropagation();
        return false;
    },

    onAdd : function (fn) {
        this.getDocument().addEvent('click', fn);
    },

    onRemove : function (fn) {
        this.getDocument().removeEvent('click', fn);
    }

};

Element.Events.showTip = {};
Element.Events.hideTip = {};
Element.Events.showCustomTip = {};

Element.implement({

    does: function (fn) {
        if (fn instanceof Function) {
            fn.apply(this);
        }

        return this;
    },

    setData: function (attrib, value) {
        return this.set('data-' + attrib, value);
    },

    getData: function (attrib) {
        return this.get('data-' + attrib);
    }

});

Elements.from = function (text, excludeScripts) {
    if ((excludeScripts !== undefined) ? excludeScripts : true) {
        text = text.stripScripts();
    }

    var container, 
        match = text.match(/^\s*<(t[dhr]|tbody|tfoot|thead)/i);

    if (match) {
        container = new Element('table');
        var tag = match[1].toLowerCase();
        if (({'td': 1, 'th': 1, 'tr': 1})[tag]) {
            container = new Element('tbody').inject(container);
            if (tag != 'tr') {
                container = new Element('tr').inject(container);
            }
        }
    }
    return (container || new Element('div')).set('html', text).getChildren();
};

Function.implement({

    toHandler: function (bound) {
        var func = this;
        return function (e) {
            e = e || {};
            if (e.preventDefault) {
                e.preventDefault();
            }
            return func.apply(bound, [this, e]);
        };
    },

    toListener: function (bound) {
        var func = this;
        return function (e) {
            e = e || {};
            return func.apply(bound, [this, e]);
        };
    }

});

String.implement({
    isEmpty : function () {
        return (!this || !this.replace(/\s/g, ''));
    },

    cleanup: function () {
        return this.replace(/&gt;|%3E|&lt;|%3C|%20|&amp;/g, function (match) {
            var x;
            switch (match) {
            case '&gt;':
            case '%3E':
                x = '>'; 
                break;
            case '&lt;':
            case '%3C':
                x = '<'; 
                break;
            case '%20':
                x = ' '; 
                break;
            case '&amp;':
                x = '&'; 
                break;
            }
            return x;
        });
    },

    rtrim: function (str) {
        if (this.lastIndexOf(str) == this.length - 1) {
            return this.substring(0, this.lastIndexOf(str));
        }

        return this;
    },

    remove: function (exp) {
        return this.replace(exp, '');
    },

    linkify: function () {
        var regexp = new RegExp(
            "(?:(?:ht|f)tp(?:s?)\\:\\/\\/|~\\/|\\/){1}" +
            "(?:\\w+:\\w+@)?" + 
            "(?:(?:[-\\w]+\\.)" +
            "(?:com|org|net|gov|mil|biz|info|mobi|name|aero|jobs|museum|travel|[a-z]{2}))" +
            "(?::[\\d]{1,5})?(?:(?:(?:\\/(?:[-\\w~!$+|.,=]|%[a-f\\d]{2})+)+|\\/)+|\\?|#)?" +
            "(?:(?:\\?(?:[-\\w~!$+|.,*:]|%[a-f\\d{2}])+=(?:[-\\w~!$+|.,*:=]|%[a-f\\d]{2})*)" +
            "(?:&(?:[-\\w~!$+|.,*:]|%[a-f\\d{2}])+=(?:[-\\w~!$+|.,*:=]|%[a-f\\d]{2})*)*)*" + 
            "(?:#(?:[-\\w~!$+|.,*:=]|%[a-f\\d]{2})*)?".replace(/\(\?x\)|\s+#.*$|\s+/gim, ''), 'g');

        return this.replace(regexp, function (match) {
            return ['<a href="', match, '" target="_blank">', match, '</a>'].join('');
        });
    },

    lpad: function (chara, count) {
        var ch = chara || "0";
        var cnt = count || 2;
        var s = "";
        while (s.length < (cnt - this.length)) { 
            s += ch; 
        }

        s = s.substring(0, cnt - this.length);

        return s + this;
    },

    rpad: function (chara, count) {
        var ch = chara || "0";
        var cnt = count || 2;
        var s = "";
        while (s.length < (cnt - this.length)) { 
            s += ch; 
        }
        s = s.substring(0, cnt - this.length);
        return this + s;
    },

    /*
     * Make line shorter till specified limit
     */
    limit: function (size) {
        size = size || 30;
        if (this.length <= size) {
            return this;
        }

        var len = 0,
            words = this.clean().trim().split(' ').filter(function (word) {
            len += word.length + 1;
            return len <= size;
        });

        if (words.length) {
            return words.join(' ') + ' ...';
        } else {
            return this.substr(0, size) + ' ...';
        }
    },

    /*
     * Make short url-complient text for use as `symbol`
     */
    makeSymbol: function (size) {
        size = size || 30;
        var str = this.trim();

        //UpperCase for words if there were one
        if (str.contains(' ')) {
            str = str.capitalize().clean().replace(/ /g, '-');
        }

        //clean garbage
        str = str.replace(/[^a-z0-9\-)(]*/ig, '');

        //if there are words and string > limit
        //try to make abbreviations
        if (str.contains('-')) {
            while (str.length > size && str.test(/[a-z]/)) {
                str = str.replace(/([A-Z])[a-z0-9]+([^a-z0-9]*)$/, '$1$2');
            }
        }

        return str.substring(0, size);
    }
    
});


/**
 * CirToolTip
 *
 * Tooltips for Circlefy
 * To show tooltips just add class 'circletip' and the content in REL attribute
 * as JSON: {title:"Tooltip Title",description:"text here"}
 * 
 * based on this MooTooltips: http://www.php-help.ro/examples/mootooltips-javascript-tooltips/
 *
 * position / align possible combos:
 * top-bottom / left-center-right
 * left-right / top-middle-bottom
 *
 * @version     1.0.0
 *
 * @author      Leandro Ardissone <leandro [at] ardissone.com>
 *
 */
var CirTooltip = new Class({
    Implements: [Options],
    
    options: {
        hovered: null,      // hovered element
        duration: 100,      // time after mouse leaves hovered element
        template: null,     // template to use
        position: 'bottom', // any position from Element.Position
        align: 'left',      // tooltip alignment: left, center, right, top, middle, bottom
        sticky: false       // tooltip will remain open until close event is throw
    },
    
    initialize: function (options) {
        this.setOptions(options || null);
        
        if (!this.options.hovered) {
            return;
        }
        
        this.elements = this.options.hovered;
        
        if (this.elements && this.elements.length > 0) {
            this.currentElement = null;

            if (!CirTooltip.tooltip_template) {
                CirTooltip.tooltip_template = new Template().compile($(this.options.template).innerHTML.cleanup());
            }
            this.tooltip_template = CirTooltip.tooltip_template;
        
            this.attach();
        }
    },

    attach: function () {
        this.elements.each(function (elem, key) {
            var data = {id: elem.getData('tipid'), title: elem.getData('tiptitle'), content: elem.getData('tipcontent'), image: elem.getData('tipimage')};
            var properties = new Hash();
            properties.include('visible', 0);
            properties.include('id', data.id);
            
            if (elem.getData('tipposition')) {
                properties.include('position', elem.getData('tipposition'));
            }

            if (elem.getData('tipalign')) {
                properties.include('align', elem.getData('tipalign'));
            }
            
            
            this.tooltip = Elements.from(this.tooltip_template.apply(data))[0];

            this.tooltip.setStyles({
                'display':  'block',
                'position': 'absolute',
                'z-index':  '110000'
            });

            this.tooltip.set('tween', {link: 'cancel', duration: 'short',
                onComplete: function (elem) { 
                    if (!elem.getStyle('opacity'))  {
                        elem.dispose(); 
                    }
                }
            });
            
            elem.store('tip', this.tooltip);
            elem.store('properties', properties);
            
            var self = this;
            var over = function (e) {
                    self.enter(e, elem);
                };

            var out = function (e) {
                    self.leave(e, elem);
                };
            
            if (!this.options.sticky) {
                elem.addEvent('mouseenter', over);
                elem.addEvent('mouseleave', out.pass(this.tooltip));
            } else {
                elem.addEvent('showTip', over);
                elem.addEvent('hideTip', out.pass(this.tooltip));

                elem.addEvent('showCustomTip', function (content) {
                    self.enter(this, elem, content);
                });
            }
        }, this);
    },

    enter: function (event, element, new_content) {
        var tip = element.retrieve('tip');
        var elProperties = element.retrieve('properties');
        $(document.body).adopt(tip);
        
        // are we setting new title?
        if (new_content) {
            if (new_content.title) {
                tip.getElement('.tooltip-title').innerHTML = new_content.title;
            }

            if (new_content.content) {
                tip.getElement('.tooltip-content').innerHTML = new_content.content;
            }
        }
        
        var opposites = {
            'upperLeft': 'bottomRight',
            'centerTop': 'centerBottom',
            'upperRight': 'BottomLeft',
            'bottomLeft': 'upperRight',
            'centerBottom': 'centerTop',
            'bottomRight': 'upperLeft',
            'centerLeft': 'centerRight',
            'centerRight': 'centerLeft'
        };

        tip.position({
            relativeTo: element, 
            position: this.options.position, 
            edge: opposites[this.options.position]
        });

        tip.addClass('position-' + this.options.position);
        
        //elProperties.set('leave', top_dist);
        this.currentElement = elProperties.get('id');
        this.show(tip);
    },
    
    leave: function (event, element) {
        var tip = $('tooltip-' + this.currentElement);
        if (tip) {
            this.hide(tip);
        }
    },

    hide: function (element) {
        element.dispose();
    },

    show: function () {
        //$('tooltip-'+this.currentElement).tween('opacity', 1);
        $('tooltip-' + this.currentElement).show();
    }
});

var MediaEmbed = new Class({
    Implements: [Options],
    
    options: {
        element: null,      // watched element
        services: /http:\/\/(\S*youtube\.com\/watch\S*|\S*\.youtube\.com\/v\/\S*|youtu\.be\/\S*|\S*\.youtube\.com\/user\/\S*#\S*|\S*\.youtube\.com\/\S*#\S*\/\S*|m\.youtube\.com\/watch\S*|m\.youtube\.com\/index\S*|www\.livestream\.com\/\S*|www\.flickr\.com\/photos\/\S*|flic\.kr\/\S*|\S*imgur\.com\/\S*|\S*dribbble\.com\/shots\/\S*|drbl\.in\/\S*|www\.vimeo\.com\/groups\/\S*\/videos\/\S*|www\.vimeo\.com\/\S*|vimeo\.com\/m\/#\/featured\/\S*|vimeo\.com\/groups\/\S*\/videos\/\S*|vimeo\.com\/\S*|vimeo\.com\/m\/#\/featured\/\S*|www\.ted\.com\/talks\/\S*\.html\S*|www\.ted\.com\/talks\/lang\/\S*\/\S*\.html\S*|www\.ted\.com\/index\.php\/talks\/\S*\.html\S*|www\.ted\.com\/index\.php\/talks\/lang\/\S*\/\S*\.html\S*|www\\.last\\.fm\/music\/\S*|www\\.last\\.fm\/music\/+videos\/\S*|www\\.last\\.fm\/music\/+images\/\S*|www\\.last\\.fm\/music\/\S*\/_\/\S*|www\\.last\\.fm\/music\/\S*\/\S*|www\.facebook\.com\/photo\.php\S*|www\.facebook\.com\/video\/video\.php\S*|gist\.github\.com\/\S*|\S*\.scribd\.com\/doc\/\S*|tumblr\.com\/\S*|\S*\.tumblr\.com\/post\/\S*)/i
    },
    
    initialize: function (options) {
        this.setOptions(options || {});
        
        if (!this.options.element) {
            return;
        }

        this.preview = this.options.element.getParent('form').getElement('div.media-preview');
        this.preview_loading = this.options.element.getParent('form').getElement('div.media-preview-loading');

        if (!this.preview) {
            return;
        }

        this.url_found = false;
        this.addChecker();
    },

    addChecker: function () {
        var self = this;
        this.options.element.addEvent('keyup', function (event) {
            var content = event.target.value;
            
            var urls = content.match(self.options.services);
            if (!urls || !urls.length || urls[1] == this.url_found) {
                return; 
            }
            
            this.url_found = urls[1];
            this.preview_loading[0].removeClass('hidden');
            this.getEmbed(urls[0]);
        }.bind(this));
    },

    getEmbed: function (url) {
        new Request.JSONP({
            url: 'http://api.embed.ly/v1/api/oembed',
            data: {format: 'json', maxwidth: 500, url: url},
            headers: {'User-Agent': 'Mozilla/5.0 (compatible; Circlefy/0.1; +http://circlefy.com/)'},
            onComplete: function (response) {
                if (['video', 'photo'].contains(response.type)) {
                    this.inject(response, url);
                } else {
                    this.preview_loading.addClass('hidden');
                }
            }.bind(this)
        }).send();
    },

    inject: function (data, url) {
        var p = this.preview[0],
            d = p.getElement('div.data'),
            title = d.getElement('h3.title > a'),
            link  = d.getElement('small > a'),
            descr = d.getElement('em'),
            description = data.description ? data.description.stripTags().limit(200).replace('\n', '<br>') : '&nbsp;';

        p.getElement('img.thumbnail').src = data.thumbnail_url;
        title.innerHTML = data.title ? data.title : '&nbsp;';
        descr.innerHTML = description;
        title.href = link.innerHTML = link.href = url;
        
        this.options.element.setData('mediatype', data.type);
        this.options.element.setData('link', url);
        this.options.element.setData('embed', data.html);
        this.options.element.setData('title', data.title ? data.title : '&nbsp;');
        this.options.element.setData('description', description);
        this.options.element.setData('thumbnail', data.thumbnail_url);
        if (data.type == 'photo') {
            this.options.element.setData('fullimage', data.url);
            this.options.element.setData('embed', data.width + ',' + data.height);
        }
        
        this.preview_loading[0].addClass('hidden');
        p.removeClass('hidden');
    }
});
