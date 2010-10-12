/* There would be self-coded/customized mootools-based libraries
 * so we can use mootools builder easily */

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

(function() {
    this.Template = new Class({
        Implements:Options,
        
        options: {
            pattern:"raccoon",
            path:"",
            suffix:""
        },

        regexps: {
            raccoon : {
                pattern:/<#[:|=]?(.*?)#>/g,
                outkey:":",
                include:"="
            },
            asp: {
                pattern:/<%[=|@]?(.*?)%>/g,
                outkey:"=",
                include:"@"
            },
            php: {
                pattern:/<\?[=|@]?(.*?)\?>/g,
                outkey:"=",
                include:"@"
            }
        },
        forEachExp: /for\s+\((?:var\s*)?(.*?)\s+from\s+(.*?)\s*\)\s*\{\s*([^¬]*?)/g,
        eachExp: /each\s+\((?:var\s*)?(.*?)\s+from\s+(.*?)\s*\)\s*\{\s*([^¬]*?)/g,
        shortTags: /(each|for|if|else|while)+(.*):/g,
        shortEnds:/end(for|if|while|each)?;/g,
        
        initialize: function(b) {
            this.setOptions(b);
            var d=this.options.pattern,
                a=this.regexps,
                c=a.raccoon;
                
            if($type(d)=="object") {
                this.pattern=d.pattern||c.pattern;
                this.outkey=d.outkey||c.outkey;
                this.includes=d.include||c.include;
            } else {
                this.pattern=a[d].pattern||c.pattern;
                this.outkey=a[d].outkey||c.raccoon.outkey;
                this.includes=a[d].include||c.include;
            }
        },
        
        parseShortTags: function(a) {
            return a.replace(
                  this.shortTags,
                    function(c,b,d) {
                        return[b=="else"?"} ":"",b,d,"{"].join("");
                    }
                ).replace(this.shortEnds,"}")
                 .replace(/%AND%/g,"&&")
                 .replace(/%OR%/g,"||");
        },
        
        parseForFrom: function(a) {
            return a.replace(
                    this.forEachExp,
                    function(d,e,c,b) {
                        return["for (var _ITERATOR_ = 0, _ARRAYLENGTH_ = ",
                               c, //source name
                               ".length; _ITERATOR_ < _ARRAYLENGTH_; _ITERATOR_++){\n",
                               "\tvar ",
                               e, //var name
                               " = ",
                               c, //source name
                               "[_ITERATOR_];\n\t",
                               b, //inner code
                              ].join("");
                    }
                );
        },
        
        parseEachFrom: function(a) {
            return a.replace(
                    this.eachExp,
                    function(d,e,c,b) {
                        return["var _ITERATOR_ = ",
                               c, //source name
                               ".reverse().length;\nwhile(_ITERATOR_--){",
                               "\tvar ",
                               e, //var name
                               " = ",
                               c, //source name
                               "[_ITERATOR_];\n\t",
                               b //inner code
                              ].join("");
                    }
                );
        },
        
        escape: function(a) {
            return a.replace(/'/g,"%%LIT_QUOT_SING%%")
                    .replace(/"/g,"%%LIT_QUOT_DB%%")
                    .replace(/\r|\n/g,"%%LIT_NEW_LINE%%");
        },
        
        unescape: function(a) {
            return a.replace(/%%LIT_QUOT_SING%%/g,"'")
                    .replace(/%%LIT_QUOT_DB%%/g,'"')
                    .replace(/%%LIT_NEW_LINE%%/g,"\n");
        },
        
        build: function(g,f) {
            var c=this,
                e,
                b,
                d=this.outkey,
                a=this.includes;

            g=this.escape(g);
            g=g.replace(
                this.pattern,
                function(i,j){
                    j = c.parseEachFrom(
                            c.parseForFrom(
                                c.parseShortTags(
                                    c.unescape(j))));
                    
                    var h,k;
                    if(i.charAt(2)==d){
                        h=["buffer.push(",j,");\n"];
                    } else {
                        if(i.charAt(2)==a) {
                            k=c.process(j.trim().replace(/"|'/g,""),f);
                            h=['buffer.push("',c.escape(k),'");\n'];
                        } else {
                            h=[j.replace(/^\s+|\s+$/g,""),"\n"];
                        }
                    }
                    
                    return['");\n',h.join(""),'buffer.push("'].join("");
                }
            );
            
            return["var $ = this, buffer = [], print = function(data){ buffer.push(data); },\n","include = function(src){ buffer.push($._include(src, $)); };\n",'\nbuffer.push("',g,'");\n','return buffer.join("");\n'].join("");
        },
        
        peek: function(a) {
            return this.build(a);
        },
        
        parse: function(e,d) {
            var b=this;

            d._include = function(g,f){
                var p = b.process(g,f);
                var e = b.escape(p);
                return e;
            };
            
            var c=this.build(e,d),
                a=new Function(c);
            a = a.apply(d);
                
            delete d._include;
            
            return this.unescape(a);
        },
        
        process: function(b,c) {
            var a=[this.options.path,b,".",this.options.suffix].join("");
            b=new File(a);
            if(!b.exists()) {
                throw new Error("Cannot open template "+a);
            }
            
            var d=b.open("r").read();
            
            return this.parse(d,c);
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

	mixin: function(obj){
		if (!obj.name) return;
		this.$mixins[obj.name] = obj;
		if (obj.init) window.addEvent('domready', obj.init.bind(obj));
		delete obj.name;
		return this;
	},

	register: function(obj){
		var self = this;
		if (obj.init) window.addEvent('domready', obj.init.bind(obj));
		if (obj.mixins){
			var mixins = obj.mixins.split(';'),
				len = mixins.reverse().length;
			while (len--) {
				var mixin = this.$mixins[mixins[len].trim()];
				if (mixin) $extend(obj, mixin);
			}
			delete obj.mixins;
		}
		obj.obs = this;
		obj.publish = this.publish.bind(this);
		obj.subscribe = this.subscribe.bind(this);
		return obj;
	},

    publish: function (type, args, once) {
		if (!this.$events || !this.$events[type]) return this;
		if (once) this.$done[type] = args || true;
        return this.fireEvent(type, args);
    },

	subscribe: function(obj, value){
		var keys, len, curr, 
			_tmp = {},
			results = [];
		if ($type(obj) == 'string'){
			 _tmp[obj] = value; obj = _tmp;
		}
		for (var key in obj){
			keys = key.split(';');
			len = keys.reverse().length;
			while (len--){
				curr = keys[len].trim();
				if (this.$done[curr]) results.push(obj[key].apply(this, this.$done[curr]));
				else this.addEvent.call(this, curr, obj[key]);
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
 * @version		1.0.2
 *
 * @license		MIT-style license
 * @author		Harald Kirschner <mail [at] digitarald.de>
 * @modified    Andrew Korzhuev <wolfon@gmail.com>
 * @modified    Ignacio Freiberg <hookdump@gmail.com>
 *
 * @copyright	Author
 */

var Roar = new Class({

	Implements: [Options, Events, Chain],

	options: {
		duration: 3000,
		position: 'upperLeft',
		container: null,
		bodyFx: null,
		itemFx: null,
		margin: {x: 10, y: 10},
		offset: 10,
		className: 'roar',
		onShow: $empty,
		onHide: $empty,
		onRender: $empty
	},

	initialize: function(options) {
		this.setOptions(options);
		this.items = [];
		this.container = $(this.options.container) || document;
	},

	alert: function(title, message, options) {
		var params = Array.link(arguments, {title: String.type, message: String.type, options: Object.type});
		var items = []; 
        if (params.options.avatar)
            items.push(new Element('img',
                {class: 'avatar',
                 src: params.options.avatar}));

        items.push(new Element('h3',
            {html: $pick(params.title, '')}));

		if (params.message)
            items.push(new Element('p',
                {html: params.message}));

		return this.inject(items, params.options);
	},

	inject: function(elements, options) {
		if (!this.body) this.render();
		options = options || {};

		var offset = [-this.options.offset, 0];
		var last = this.items.getLast();
		if (last) {
			offset[0] = last.retrieve('roar:offset');
			offset[1] = offset[0] + last.offsetHeight + this.options.offset;
		}

        var rightElem;
        if (options.group_avatar)
            rightElem = new Element('img', {
                'class': 'group_icon',
                'src': options.group_avatar
            });
        else
            rightElem = new Element('div', {
                    'class': 'dismiss',
                    'html': 'click to dismiss'
                });

		var item = new Element('div', {
			'class': this.options.className,
			'opacity': 0
		}).adopt(
			new Element('div', {
				'class': 'roar-bg',
				'opacity': 0.7
			}).adopt(rightElem), elements);

		var to = {'opacity': 1};

		// I added this check to support both Absolute Position and Left/Right/Top/Bottom Position. [Ignacio]
		var isArray = options.position instanceof Array;
        if (isArray) {
			// Andrew's absolute position code ---
			if (options.position) {
	            item.setStyle('left', options.position[0] - this.body.offsetLeft);
	            item.setStyle('top', options.position[1] - this.body.offsetTop);
	            to['opacity'] = [0.7, 1];
	        }
		} else {
			// Original position code ---
			to[this.align.y] = offset;
			item.setStyle(this.align.x, 0);
		}

        item.store('roar:offset', offset[1]).set('morph', $merge({
            unit: 'px',
			link: 'cancel',
			onStart: Chain.prototype.clearChain,
			transition: Fx.Transitions.Back.easeOut
		}, this.options.itemFx));

		var remove = this.remove.create({
			bind: this,
			arguments: [item],
			delay: 10
		});
		this.items.push(item.addEvent('click', remove));

        item.set('over', false);
		
        if (this.options.duration) {
            var duration = this.options.duration;
            if (options.duration)
                duration = options.duration;

			var over = false;
			var trigger = (function() {
				trigger = null;
				if (!item.over) remove();
			}).delay(duration);
			item.addEvents({
				mouseover: function() {
					item.over = true;
				},
				mouseout: function(e) {
                    var fromOutside = !e.relatedTarget.getParents('div.roar').length;
                    if (!fromOutside)
                        return;
					item.over = false;
					if (!trigger) remove();
				}
			});
		}
        if (options.color) {
            //set style for roar-bg inner div (first child)
            item.firstElementChild.setStyle('background-color', options.color);
        }
		item.inject(this.body).morph(to);
		return this.fireEvent('onShow', [item, this.items.length]);
	},

	remove: function(item) {
		var index = this.items.indexOf(item);
		if (index == -1) return this;
		this.items.splice(index, 1);
		item.removeEvents();
		var to = {opacity: 0};
		to[this.align.y] = item.getStyle(this.align.y).toInt() - item.offsetHeight - this.options.offset;
		item.morph(to).get('morph').chain(item.destroy.bind(item));
		return this.fireEvent('onHide', [item, this.items.length]).callChain(item);
	},

	empty: function() {
		while (this.items.length) this.remove(this.items[0]);
		return this;
	},

	render: function() {
		this.position = this.options.position;
		if ($type(this.position) == 'string') {
			var position = {x: 'center', y: 'center'};
			this.align = {x: 'left', y: 'top'};

            if ((/left|west/i).test(this.position)) position.x = 'left';
			else if ((/right|east/i).test(this.position)) this.align.x = position.x = 'right';
			if ((/upper|top|north/i).test(this.position)) position.y = 'top';
			else if ((/bottom|lower|south/i).test(this.position)) this.align.y = position.y = 'bottom';
			this.position = position;
        }
		this.body = new Element('div', {'class': 'roar-body'}).inject(document.body);
		if (Browser.Engine.trident4) this.body.addClass('roar-body-ugly');
		this.moveTo = this.body.setStyles.bind(this.body);
		this.reposition();
		if (this.options.bodyFx) {
			var morph = new Fx.Morph(this.body, $merge({
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

	reposition: function() {
		var max = document.getCoordinates(), scroll = document.getScroll(), margin = this.options.margin;
		max.left += scroll.x;
		max.right += scroll.x;
		max.top += scroll.y;
		max.bottom += scroll.y;
		var rel = ($type(this.container) == 'element') ? this.container.getCoordinates() : max;

        //dirty chrome hack (cuz it returns document width without scrollbars)
        if (Browser.Engine.webkit) {
            margin.x = 20;
            margin.y = 20;
        }
		this.moveTo({
			left: (this.position.x == 'right')
				? (Math.min(rel.right, max.right) - margin.x)
				: (Math.max(rel.left, max.left) + margin.x),
			top: (this.position.y == 'bottom')
				? (Math.min(rel.bottom, max.bottom) - margin.y)
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

	condition : function(event){
		event.stopPropagation();
		return false;
	},

	onAdd : function(fn){
		this.getDocument().addEvent('click', fn);
	},

	onRemove : function(fn){
		this.getDocument().removeEvent('click', fn);
	}

};

Element.implement({

	does: function(fn){
		if (fn instanceof Function) fn.apply(this);
		return this;
	},

	setData: function(attrib, value){
		return this.set('data-' + attrib, value);
	},

	getData: function(attrib){
		return this.get('data-' + attrib);
	}

});

Elements.from = function(text, excludeScripts){
	if ((excludeScripts !== undefined) ? excludeScripts : true) text = text.stripScripts();
	var container, match = text.match(/^\s*<(t[dhr]|tbody|tfoot|thead)/i);
	if (match){
		container = new Element('table');
		var tag = match[1].toLowerCase();
		if (({'td':1, 'th':1, 'tr':1})[tag]){
			container = new Element('tbody').inject(container);
			if (tag != 'tr') container = new Element('tr').inject(container);
		}
	}
	return (container || new Element('div')).set('html', text).getChildren();
};

Function.implement({

	toHandler: function(bound){
		var func = this;
		return function(e){
			e = e || {};
			if (e.preventDefault) e.preventDefault();
			return func.apply(bound, [this, e]);
		};
	},

	toListener: function(bound){
		var func = this;
		return function(e){
			e = e || {};
			return func.apply(bound, [this, e]);
		};
	}

});

String.implement({
    isEmpty : function() {
        return (!this.test(/\w+/));
    },

	cleanup: function(){
		return this.replace(/&gt;|%3E|&lt;|%3C|%20/g, function(match){
			var x;
			switch (match) {
				case '&gt;':
				case '%3E':
					x = '>'; break;
				case '&lt;':
				case '%3C':
					x = '<'; break;
				case '%20':
					x = ' '; break;
			}
			return x;
		});
	},

	rtrim: function(str){
		if (this.lastIndexOf(str) == this.length - 1) return this.substring(0, this.lastIndexOf(str));
		return this;
	},

	remove: function(exp){
		return this.replace(exp, '');
	},

	linkify: function(){
		var regexp = new RegExp("\
			(?:(?:ht|f)tp(?:s?)\\:\\/\\/|~\\/|\\/){1}\
			(?:\\w+:\\w+@)?\
			(?:(?:[-\\w]+\\.)+\
			(?:com|org|net|gov|mil|biz|info|mobi|name|aero|jobs|museum|travel|[a-z]{2}))\
			(?::[\\d]{1,5})?(?:(?:(?:\\/(?:[-\\w~!$+|.,=]|%[a-f\\d]{2})+)+|\\/)+|\\?|#)?\
			(?:(?:\\?(?:[-\\w~!$+|.,*:]|%[a-f\\d{2}])+=(?:[-\\w~!$+|.,*:=]|%[a-f\\d]{2})*)\
			(?:&(?:[-\\w~!$+|.,*:]|%[a-f\\d{2}])+=(?:[-\\w~!$+|.,*:=]|%[a-f\\d]{2})*)*)*\
			(?:#(?:[-\\w~!$+|.,*:=]|%[a-f\\d]{2})*)?".replace(/\(\?x\)|\s+#.*$|\s+/gim, ''), 'g');
		return this.replace(regexp, function(match){
			return ['<a href="', match,'" target="_blank">', match,'</a>'].join('');
		});
	},

	lpad: function(chara, count){
		var ch = chara || "0";
		var cnt = count || 2;
		var s = "";
		while (s.length < (cnt - this.length)) { s += ch; }
		s = s.substring(0, cnt-this.length);
		return s+this;
	},

	rpad: function(chara, count){
		var ch = chara || "0";
		var cnt = count || 2;
		var s = "";
		while (s.length < (cnt - this.length)) { s += ch; }
		s = s.substring(0, cnt-this.length);
		return this+s;
	}

});

// GENERALS

if (!window.console) {
	window.console = {
		log: $empty, debug: $empty, info: $empty, warn: $empty, error: $empty,
		assert: $empty, dir: $empty, dirxml: $empty, trace: $empty, time: $empty,
		timeEnd: $empty, profile: $empty, profileEnd: $empty
	};
}
