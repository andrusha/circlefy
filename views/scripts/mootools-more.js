/*
Script: Element.Delegation.js
	Extends the Element native object to include the delegate method for more efficient event management.
*/
(function(){

	var match = /(.*?):relay\(([^)]+)\)$/,
		combinators = /[+>~\s]/,
		splitType = function(type){
			var bits = type.match(match);
			return !bits ? {event: type} : {
				event: bits[1],
				selector: bits[2]
			};
		},
		check = function(e, selector){
			var t = e.target;
			if (combinators.test(selector = selector.trim())){
				var els = this.getElements(selector);
				for (var i = els.length; i--; ){
					var el = els[i];
					if (t == el || el.hasChild(t)) return el;
				}
			} else {
				for ( ; t && t != this; t = t.parentNode){
					if (Element.match(t, selector)) return document.id(t);
				}
			}
			return null;
		};

	var oldAddEvent = Element.prototype.addEvent,
		oldRemoveEvent = Element.prototype.removeEvent;

	Element.implement({

		addEvent: function(type, fn){
			var splitted = splitType(type);
			if (splitted.selector){
				var monitors = this.retrieve('$moo:delegateMonitors', {});
				if (!monitors[type]){
					var monitor = function(e){
						var el = check.call(this, e, splitted.selector);
						if (el) this.fireEvent(type, [e, el], 0, el);
					}.bind(this);
					monitors[type] = monitor;
					oldAddEvent.call(this, splitted.event, monitor);
				}
			}
			return oldAddEvent.apply(this, arguments);
		},

		removeEvent: function(type, fn){
			var splitted = splitType(type);
			if (splitted.selector){
				var events = this.retrieve('events');
				if (!events || !events[type] || (fn && !events[type].keys.contains(fn))) return this;

				if (fn) oldRemoveEvent.apply(this, [type, fn]);
				else oldRemoveEvent.apply(this, type);

				events = this.retrieve('events');
				if (events && events[type] && events[type].length == 0){
					var monitors = this.retrieve('$moo:delegateMonitors', {});
					oldRemoveEvent.apply(this, [splitted.event, monitors[type]]);
					delete monitors[type];
				}
				return this;
			}

			return oldRemoveEvent.apply(this, arguments);
		},

		fireEvent: function(type, args, delay, bind){
			var events = this.retrieve('events');
			if (!events || !events[type]) return this;
			events[type].keys.each(function(fn){
				fn.create({bind: bind || this, delay: delay, arguments: args})();
			}, this);
			return this;
		}

	});

})();

/*
Script: Template.jx
	Basic templating system.

Copyright and License:
	Copyright, 2010, Mark Obcena. MIT-style license.

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

/* Tips */

//MooTools More, <http://mootools.net/more>. Copyright (c) 2006-2009 Aaron Newton <http://clientcide.com/>, Valerio Proietti <http://mad4milk.net> & the MooTools team <http://mootools.net/developers>, MIT Style License.

MooTools.More={version:"1.2.4.4",build:"6f6057dc645fdb7547689183b2311063bd653ddf"};(function(){var a=function(c,b){return(c)?($type(c)=="function"?c(b):b.get(c)):"";
};this.Tips=new Class({Implements:[Events,Options],options:{onShow:function(){this.tip.setStyle("display","block");},onHide:function(){this.tip.setStyle("display","none");
},title:"title",text:function(b){return b.get("rel")||b.get("href");},showDelay:100,hideDelay:100,className:"tip-wrap",offset:{x:16,y:16},windowPadding:{x:0,y:0},fixed:false},initialize:function(){var b=Array.link(arguments,{options:Object.type,elements:$defined});
this.setOptions(b.options);if(b.elements){this.attach(b.elements);}this.container=new Element("div",{"class":"tip"});},toElement:function(){if(this.tip){return this.tip;
}return this.tip=new Element("div",{"class":this.options.className,styles:{position:"absolute",top:0,left:0}}).adopt(new Element("div",{"class":"tip-top"}),this.container,new Element("div",{"class":"tip-bottom"})).inject(document.body);
},attach:function(b){$$(b).each(function(d){var f=a(this.options.title,d),e=a(this.options.text,d);d.erase("title").store("tip:native",f).retrieve("tip:title",f);
d.retrieve("tip:text",e);this.fireEvent("attach",[d]);var c=["enter","leave"];if(!this.options.fixed){c.push("move");}c.each(function(h){var g=d.retrieve("tip:"+h);
if(!g){g=this["element"+h.capitalize()].bindWithEvent(this,d);}d.store("tip:"+h,g).addEvent("mouse"+h,g);},this);},this);return this;},detach:function(b){$$(b).each(function(d){["enter","leave","move"].each(function(e){d.removeEvent("mouse"+e,d.retrieve("tip:"+e)).eliminate("tip:"+e);
});this.fireEvent("detach",[d]);if(this.options.title=="title"){var c=d.retrieve("tip:native");if(c){d.set("title",c);}}},this);return this;},elementEnter:function(c,b){this.container.empty();
["title","text"].each(function(e){var d=b.retrieve("tip:"+e);if(d){this.fill(new Element("div",{"class":"tip-"+e}).inject(this.container),d);}},this);$clear(this.timer);
this.timer=(function(){this.show(this,b);this.position((this.options.fixed)?{page:b.getPosition()}:c);}).delay(this.options.showDelay,this);},elementLeave:function(c,b){$clear(this.timer);
this.timer=this.hide.delay(this.options.hideDelay,this,b);this.fireForParent(c,b);},fireForParent:function(c,b){b=b.getParent();if(!b||b==document.body){return;
}if(b.retrieve("tip:enter")){b.fireEvent("mouseenter",c);}else{this.fireForParent(c,b);}},elementMove:function(c,b){this.position(c);},position:function(e){if(!this.tip){document.id(this);
}var c=window.getSize(),b=window.getScroll(),f={x:this.tip.offsetWidth,y:this.tip.offsetHeight},d={x:"left",y:"top"},g={};for(var h in d){g[d[h]]=e.page[h]+this.options.offset[h];
if((g[d[h]]+f[h]-b[h])>c[h]-this.options.windowPadding[h]){g[d[h]]=e.page[h]-this.options.offset[h]-f[h];}}this.tip.setStyles(g);},fill:function(b,c){if(typeof c=="string"){b.set("html",c);
}else{b.adopt(c);}},show:function(b){if(!this.tip){document.id(this);}this.fireEvent("show",[this.tip,b]);},hide:function(b){if(!this.tip){document.id(this);
}this.fireEvent("hide",[this.tip,b]);}});})();
/*
Script: Observer.js
	A Registry Class

License:
	Copyright 2010, Mark Obcena <markobcena@gmail.com>
	MIT-style license.
*/

var Observer = new Class({

	Implements: Events,

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

	publish: function(){
		return this.fireEvent.apply(this, arguments);
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
	},

	fireEvent: function(type, args, once){
		type = Events.removeOn(type);
		if (!this.$events || !this.$events[type]) return this;
		if (once) this.$done[type] = args || true;
		var results = this.$events[type].map(function(fn){
			return fn.create({'bind': this, 'arguments': args})();
		}, this);
		return results;
	}

});

/*
Script: Validator.js
	Basic Validation

License:
	Copyright 2010, Mark Obcena <markobcena@gmail.com>
	MIT-style license.
*/

var Validator=new Hash({exps:new Hash({alpha:/^[a-zA-z\s\D]+$/,alphaStrict:/^[a-zA-z]+$/,alphaNum:/^[0-9a-zA-Z\s\D]+$/,alphaNumStrict:/^[0-9a-zA-Z]+$/,number:/^[0-9]+$/,email:/^[a-z0-9_+.-]+\@([a-z0-9-]+\.)+[a-z0-9]{2,4}$/,URL:/https?:\/\/([0-9a-zA-Z][-\w]*[0-9a-zA-Z]\.)+([a-zA-Z]{2,9})(:\d{1,4})?([-\w\/#~:.?+=&%@~]*)/}),test:function(B,A){return($type(B)=="string")?(this.exps.get(A)||this.exps.get("alphaNum")).test(B):null;},isEmpty:function(A){return A.replace(/\s/g,"").length==0;},ofLength:function(C,B,A){B=B||0;A=A||10000000000000000;return C.length>=B&&C.length<=A;},isEqual:function(B,A){return B==A;},addType:function(B,C){if($type(B)=="string"&&$type(C)=="regexp"){var A="is"+B.capitalize();this.exps.set(B,C);this.set(A,function(D){return this.test(D,B);});return true;}else{return false;}},addTypes:function(B){var A=this;if($type(B)=="object"){B=$H(B);B.each(function(D,C){A.addType(C,D);});return true;}else{return false;}}});(function(){Validator.exps.each(function(B,A){Validator.addType(A,B);});})();if($type(Validator)=="hash"){Validator.addType=function(B,C){if($type(B)=="string"&&$type(C)=="regexp"){var A="is"+B.capitalize();this.exps.set(B,C);this.set(A,function(E){return this.test(E,B);});var D={};D[A]=function(){return this.validate(B);};Native.implement([String,Element],D);return true;}else{return false;}};String.implement({validate:function(A){return Validator.test(this,A);},isEmpty:function(){return Validator.isEmpty(this);},ofLength:function(B,A){return Validator.ofLength(this,B,A);},isEqual:function(A){return Validator.isEqual(A,this);}});Validator.Stringable=["input","textarea"];Validator.canValidate=function(A){return Validator.Stringable.contains(A.get("tag"));};Element.implement({validate:function(A){return Validator.canValidate(this)?Validator.test(this.value,A):null;},isEmpty:function(){return Validator.canValidate(this)?Validator.isEmpty(this.value):null;},ofLength:function(B,A){return Validator.canValidate(this)?Validator.ofLength(this.value,B,A):null;},isEqual:function(A){return Validator.canValidate(this)?Validator.isEqual(A,this.value):null;}});(function(){var A={};Validator.exps.getKeys().each(function(C){var B="is"+C.capitalize();A[B]=function(){return this.validate(C);};});Native.implement([String,Element],A);})();}

/*
Script: TextOverlay.js
	Makes overlays easy.

License:
	Copyright 2010, Garrick Cheung
	MIT-style license.
*/

var TextOverlay = new Class({
    Implements: [Options, Events],

    property:'TextOverlay',

    options: {
        /*onFocus: $empty(),*/
        onTextHide: function(element, text){
            text.setStyle('display','none');
        },
        onTextShow: function(element, text){
            text.setStyle('display','block');
        }
    },

    initialize: function(element, overtext, options){
        if(!element && !overtext){
            return false;
        }
        this.visible = true;
        this.element = document.id(element);
        this.overtext = document.id(overtext);
        this.setOptions(options);
        this.attach(this.element);
    },

    toElement: function(){
        return this.element;
    },

    attach: function(){
        this.overtext.addEvents({
            click: this.hide.bind(this)
        });

        this.element.addEvents({
            focus: this.focus.bind(this),
            blur: this.assert.bind(this),
            change: this.assert.bind(this)
        }).store('TextOverlay', this);
        this.assert();
    },

    hide: function(){
        if(this.visible && !this.element.get('disabled')){
            this.fireEvent('textHide', [this.element, this.overtext]);
            this.visible = false;
            try{
                this.element.fireEvent('focus').focus();
            } catch(e){}
        }
        return this;
    },

    show: function(){
        if(!this.visible){
            this.fireEvent('textShow', [this.element, this.overtext]);
            this.visible = true;
        }
        return this;
    },

    focus: function(){
        if(!this.visible && this.element.get('disabled')){
            return;
        }
        this.hide();
    },

    assert: function(){
        this[this.test() ? 'show' : 'hide']();
    },

    test: function(){
        return !this.element.get('value');
    }

});

/*
---

script: Fx.Slide.js

description: Effect to slide an element in and out of view.

license: MIT-style license

authors:
- Valerio Proietti

requires:
- core:1.2.4/Fx Element.Style
- /MooTools.More

provides: [Fx.Slide]

...
*/

Fx.Slide = new Class({

	Extends: Fx,

	options: {
		mode: 'vertical',
		hideOverflow: true
	},

	initialize: function(element, options){
		this.addEvent('complete', function(){
			this.open = (this.wrapper['offset' + this.layout.capitalize()] != 0);
			if (this.open && Browser.Engine.webkit419) this.element.dispose().inject(this.wrapper);
		}, true);
		this.element = this.subject = document.id(element);
		this.parent(options);
		var wrapper = this.element.retrieve('wrapper');
		var styles = this.element.getStyles('margin', 'position', 'overflow');
		if (this.options.hideOverflow) styles = $extend(styles, {overflow: 'hidden'});
		this.wrapper = wrapper || new Element('div', {
			styles: styles
		}).wraps(this.element);
		this.element.store('wrapper', this.wrapper).setStyle('margin', 0);
		this.now = [];
		this.open = true;
	},

	vertical: function(){
		this.margin = 'margin-top';
		this.layout = 'height';
		this.offset = this.element.offsetHeight;
	},

	horizontal: function(){
		this.margin = 'margin-left';
		this.layout = 'width';
		this.offset = this.element.offsetWidth;
	},

	set: function(now){
		this.element.setStyle(this.margin, now[0]);
		this.wrapper.setStyle(this.layout, now[1]);
		return this;
	},

	compute: function(from, to, delta){
		return [0, 1].map(function(i){
			return Fx.compute(from[i], to[i], delta);
		});
	},

	start: function(how, mode){
		if (!this.check(how, mode)) return this;
		this[mode || this.options.mode]();
		var margin = this.element.getStyle(this.margin).toInt();
		var layout = this.wrapper.getStyle(this.layout).toInt();
		var caseIn = [[margin, layout], [0, this.offset]];
		var caseOut = [[margin, layout], [-this.offset, 0]];
		var start;
		switch (how){
			case 'in': start = caseIn; break;
			case 'out': start = caseOut; break;
			case 'toggle': start = (layout == 0) ? caseIn : caseOut;
		}
		return this.parent(start[0], start[1]);
	},

	slideIn: function(mode){
		return this.start('in', mode);
	},

	slideOut: function(mode){
		return this.start('out', mode);
	},

	hide: function(mode){
		this[mode || this.options.mode]();
		this.open = false;
		return this.set([-this.offset, 0]);
	},

	show: function(mode){
		this[mode || this.options.mode]();
		this.open = true;
		return this.set([0, this.offset]);
	},

	toggle: function(mode){
		return this.start('toggle', mode);
	}

});

Element.Properties.slide = {

	set: function(options){
		var slide = this.retrieve('slide');
		if (slide) slide.cancel();
		return this.eliminate('slide').store('slide:options', $extend({link: 'cancel'}, options));
	},

	get: function(options){
		if (options || !this.retrieve('slide')){
			if (options || !this.retrieve('slide:options')) this.set('slide', options);
			this.store('slide', new Fx.Slide(this, this.retrieve('slide:options')));
		}
		return this.retrieve('slide');
	}

};

Element.implement({

	slide: function(how, mode){
		how = how || 'toggle';
		var slide = this.get('slide'), toggle;
		switch (how){
			case 'hide': slide.hide(mode); break;
			case 'show': slide.show(mode); break;
			case 'toggle':
				var flag = this.retrieve('slide:flag', slide.open);
				slide[flag ? 'slideOut' : 'slideIn'](mode);
				this.store('slide:flag', !flag);
				toggle = true;
			break;
			default: slide.start(how, mode);
		}
		if (!toggle) this.eliminate('slide:flag');
		return this;
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

if (window.TextboxList) {
	TextboxList.implement({
		empty: function(){
			this.list.getChildren().map(function(el){
				var bit = this.getBit(el);
				if (bit.is('editable')) return null;
				return bit.remove();
			}, this).clean();
			this.index.empty();
			return this;
		}
	});
}

Hash.Cookie = new Class({

	Extends: Cookie,

	options: {
		autoSave: true
	},

	initialize: function(name, options){
		this.parent(name, options);
		this.load();
	},

	save: function(){
		var value = JSON.encode(this.hash);
		if (!value || value.length > 4096) return false; //cookie would be truncated!
		if (value == '{}') this.dispose();
		else this.write(value);
		return true;
	},

	load: function(){
		this.hash = new Hash(JSON.decode(this.read(), true));
		return this;
	}

});

Hash.each(Hash.prototype, function(method, name){
	if (typeof method == 'function') Hash.Cookie.implement(name, function(){
		var value = method.apply(this.hash, arguments);
		if (this.options.autoSave) this.save();
		return value;
	});
});

(function() {

var dayNames = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"],
	dayNamesShort = ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"],
	monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"],
	monthNamesShort = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"],
	suffixes = {1:"st", 2:"nd", 3:"rd", 21:"st", 22:"nd", 23:"rd", 31:"st"};

Date.implement('format', function(str){
	return str.replace(/([a-zA-Z])/gi, (function(ma, ch){
		var d, t, day, diff, year, m, base, o;
		switch (ch) {
			case "d": return this.getDate().toString().lpad();
			case "j": return this.getDate();
			case "w": return this.getDay();
			case "N": return this.getDay() || 7;
			case "S": return suffixes[this.getDate()] || "th";
			case "D": return dayNamesShort[(this.getDay() || 7)-1];
			case "l": return dayNames[(this.getDay() || 7)-1];
			case "z":
				t = this.getTime();
				d = new Date(t);
				d.setDate(1);
				d.setMonth(0);
				diff = t - d.getTime();
				return diff / (1000 * 60 * 60 * 24);
			case "W":
				d = new Date(this.getFullYear(), this.getMonth(), this.getDate());
				day = d.getDay() || 7;
				d.setDate(d.getDate() + (4-day));
				year = d.getFullYear();
				day = Math.floor((d.getTime() - new Date(year, 0, 1, -6)) / (1000 * 60 * 60 * 24));
				return (1 + Math.floor(day / 7)).toString().lpad();
			case "m": return (this.getMonth()+1).toString().lpad();
			case "n": return (this.getMonth()+1);
			case "M": return monthNamesShort[this.getMonth()];
			case "F": return monthNames[this.getMonth()];
			case "t":
				t = this.getTime();
				m = this.getMonth();
				d = new Date(t);
				day = 0;
				do {
					day = d.getDate();
					t += 1000 * 60 * 60 * 24;
					d = new Date(t);
				} while (m == d.getMonth());
				return day;
			case "L":
				d = new Date(this.getTime());
				d.setDate(1);
				d.setMonth(1);
				d.setDate(29);
				return (d.getMonth() == 1 ? "1" : "0");
			case "Y": return this.getFullYear().toString().lpad();
			case "y": return this.getFullYear().toString().lpad().substring(2);
			case "a": return (this.getHours() < 12 ? "am" : "pm");
			case "A": return (this.getHours() < 12 ? "AM" : "PM");
			case "G": return this.getHours();
			case "H": return this.getHours().toString().lpad();
			case "g": return this.getHours() % 12;
			case "h": return (this.getHours() % 12).toString().lpad();
			case "i": return this.getMinutes().toString().lpad();
			case "s": return this.getSeconds().toString().lpad();
			case "Z": return -60*this.getTimezoneOffset();
			case "O":
			case "P":
				base = this.getTimezoneOffset()/-60;
				o = Math.abs(base).toString().lpad();
				if (ch == "P") { o += ":"; }
				o += "00";
				return (base >= 0 ? "+" : "-")+o;
			case "U": return this.getTime()/1000;
			case "u": return "0";
			case "c": return arguments.callee.call(this, "Y-m-d")+"T"+arguments.callee.call(this, "H:i:sP");
			case "r": return arguments.callee.call(this, "D, j M Y H:i:s O");
		}
	}).bind(this));
});

})();

var Drag = new Class({

	Implements: [Events, Options],

	options: {/*
		onBeforeStart: $empty(thisElement),
		onStart: $empty(thisElement, event),
		onSnap: $empty(thisElement)
		onDrag: $empty(thisElement, event),
		onCancel: $empty(thisElement),
		onComplete: $empty(thisElement, event),*/
		snap: 6,
		unit: 'px',
		grid: false,
		style: true,
		limit: false,
		handle: false,
		invert: false,
		preventDefault: false,
		stopPropagation: false,
		modifiers: {x: 'left', y: 'top'}
	},

	initialize: function(){
		var params = Array.link(arguments, {'options': Object.type, 'element': $defined});
		this.element = document.id(params.element);
		this.document = this.element.getDocument();
		this.setOptions(params.options || {});
		var htype = $type(this.options.handle);
		this.handles = ((htype == 'array' || htype == 'collection') ? $$(this.options.handle) : document.id(this.options.handle)) || this.element;
		this.mouse = {'now': {}, 'pos': {}};
		this.value = {'start': {}, 'now': {}};

		this.selection = (Browser.Engine.trident) ? 'selectstart' : 'mousedown';

		this.bound = {
			start: this.start.bind(this),
			check: this.check.bind(this),
			drag: this.drag.bind(this),
			stop: this.stop.bind(this),
			cancel: this.cancel.bind(this),
			eventStop: $lambda(false)
		};
		this.attach();
	},

	attach: function(){
		this.handles.addEvent('mousedown', this.bound.start);
		return this;
	},

	detach: function(){
		this.handles.removeEvent('mousedown', this.bound.start);
		return this;
	},

	start: function(event){
		if (event.rightClick) return;
		if (this.options.preventDefault) event.preventDefault();
		if (this.options.stopPropagation) event.stopPropagation();
		this.mouse.start = event.page;
		this.fireEvent('beforeStart', this.element);
		var limit = this.options.limit;
		this.limit = {x: [], y: []};
		for (var z in this.options.modifiers){
			if (!this.options.modifiers[z]) continue;
			if (this.options.style) this.value.now[z] = this.element.getStyle(this.options.modifiers[z]).toInt();
			else this.value.now[z] = this.element[this.options.modifiers[z]];
			if (this.options.invert) this.value.now[z] *= -1;
			this.mouse.pos[z] = event.page[z] - this.value.now[z];
			if (limit && limit[z]){
				for (var i = 2; i--; i){
					if ($chk(limit[z][i])) this.limit[z][i] = $lambda(limit[z][i])();
				}
			}
		}
		if ($type(this.options.grid) == 'number') this.options.grid = {x: this.options.grid, y: this.options.grid};
		this.document.addEvents({mousemove: this.bound.check, mouseup: this.bound.cancel});
		this.document.addEvent(this.selection, this.bound.eventStop);
	},

	check: function(event){
		if (this.options.preventDefault) event.preventDefault();
		var distance = Math.round(Math.sqrt(Math.pow(event.page.x - this.mouse.start.x, 2) + Math.pow(event.page.y - this.mouse.start.y, 2)));
		if (distance > this.options.snap){
			this.cancel();
			this.document.addEvents({
				mousemove: this.bound.drag,
				mouseup: this.bound.stop
			});
			this.fireEvent('start', [this.element, event]).fireEvent('snap', this.element);
		}
	},

	drag: function(event){
		if (this.options.preventDefault) event.preventDefault();
		this.mouse.now = event.page;
		for (var z in this.options.modifiers){
			if (!this.options.modifiers[z]) continue;
			this.value.now[z] = this.mouse.now[z] - this.mouse.pos[z];
			if (this.options.invert) this.value.now[z] *= -1;
			if (this.options.limit && this.limit[z]){
				if ($chk(this.limit[z][1]) && (this.value.now[z] > this.limit[z][1])){
					this.value.now[z] = this.limit[z][1];
				} else if ($chk(this.limit[z][0]) && (this.value.now[z] < this.limit[z][0])){
					this.value.now[z] = this.limit[z][0];
				}
			}
			if (this.options.grid[z]) this.value.now[z] -= ((this.value.now[z] - (this.limit[z][0]||0)) % this.options.grid[z]);
			if (this.options.style) {
				this.element.setStyle(this.options.modifiers[z], this.value.now[z] + this.options.unit);
			} else {
				this.element[this.options.modifiers[z]] = this.value.now[z];
			}
		}
		this.fireEvent('drag', [this.element, event]);
	},

	cancel: function(event){
		this.document.removeEvent('mousemove', this.bound.check);
		this.document.removeEvent('mouseup', this.bound.cancel);
		if (event){
			this.document.removeEvent(this.selection, this.bound.eventStop);
			this.fireEvent('cancel', this.element);
		}
	},

	stop: function(event){
		this.document.removeEvent(this.selection, this.bound.eventStop);
		this.document.removeEvent('mousemove', this.bound.drag);
		this.document.removeEvent('mouseup', this.bound.stop);
		if (event) this.fireEvent('complete', [this.element, event]);
	}

});

Element.implement({

	makeResizable: function(options){
		var drag = new Drag(this, $merge({modifiers: {x: 'width', y: 'height'}}, options));
		this.store('resizer', drag);
		return drag.addEvent('drag', function(){
			this.fireEvent('resize', drag);
		}.bind(this));
	}

});

(function(){
  
var special = ['À','à','Á','á','Â','â','Ã','ã','Ä','ä','Å','å','Ă','ă','Ą','ą','Ć','ć','Č','č','Ç','ç', 'Ď','ď','Đ','đ', 'È','è','É','é','Ê','ê','Ë','ë','Ě','ě','Ę','ę', 'Ğ','ğ','Ì','ì','Í','í','Î','î','Ï','ï', 'Ĺ','ĺ','Ľ','ľ','Ł','ł', 'Ñ','ñ','Ň','ň','Ń','ń','Ò','ò','Ó','ó','Ô','ô','Õ','õ','Ö','ö','Ø','ø','ő','Ř','ř','Ŕ','ŕ','Š','š','Ş','ş','Ś','ś', 'Ť','ť','Ť','ť','Ţ','ţ','Ù','ù','Ú','ú','Û','û','Ü','ü','Ů','ů', 'Ÿ','ÿ','ý','Ý','Ž','ž','Ź','ź','Ż','ż', 'Þ','þ','Ð','ð','ß','Œ','œ','Æ','æ','µ'];

var standard = ['A','a','A','a','A','a','A','a','Ae','ae','A','a','A','a','A','a','C','c','C','c','C','c','D','d','D','d', 'E','e','E','e','E','e','E','e','E','e','E','e','G','g','I','i','I','i','I','i','I','i','L','l','L','l','L','l', 'N','n','N','n','N','n', 'O','o','O','o','O','o','O','o','Oe','oe','O','o','o', 'R','r','R','r', 'S','s','S','s','S','s','T','t','T','t','T','t', 'U','u','U','u','U','u','Ue','ue','U','u','Y','y','Y','y','Z','z','Z','z','Z','z','TH','th','DH','dh','ss','OE','oe','AE','ae','u'];

var tidymap = {
	"[\xa0\u2002\u2003\u2009]": " ",
	"\xb7": "*",
	"[\u2018\u2019]": "'",
	"[\u201c\u201d]": '"',
	"\u2026": "...",
	"\u2013": "-",
	"\u2014": "--",
	"\uFFFD": "&raquo;"
};

var getRegForTag = function(tag, contents) {
	tag = tag || '';
	var regstr = contents ? "<" + tag + "[^>]*>([\\s\\S]*?)<\/" + tag + ">" : "<\/?" + tag + "([^>]+)?>";
	reg = new RegExp(regstr, "gi");
	return reg;
};

String.implement({

	standardize: function(){
		var text = this;
		special.each(function(ch, i){
			text = text.replace(new RegExp(ch, 'g'), standard[i]);
		});
		return text;
	},

	repeat: function(times){
		return new Array(times + 1).join(this);
	},

	pad: function(length, str, dir){
		if (this.length >= length) return this;
		var pad = (str == null ? ' ' : '' + str).repeat(length - this.length).substr(0, length - this.length);
		if (!dir || dir == 'right') return this + pad;
		if (dir == 'left') return pad + this;
		return pad.substr(0, (pad.length / 2).floor()) + this + pad.substr(0, (pad.length / 2).ceil());
	},

	getTags: function(tag, contents){
		return this.match(getRegForTag(tag, contents)) || [];
	},

	stripTags: function(tag, contents){
		return this.replace(getRegForTag(tag, contents), '');
	},

	tidy: function(){
		var txt = this.toString();
		$each(tidymap, function(value, key){
			txt = txt.replace(new RegExp(key, 'g'), value);
		});
		return txt;
	},

    strip: function() {
        return this.replace(/^\s+|\s+$/g, '');
    }

});

})();

// GENERALS

if (!window.console) {
	window.console = {
		log: $empty, debug: $empty, info: $empty, warn: $empty, error: $empty,
		assert: $empty, dir: $empty, dirxml: $empty, trace: $empty, time: $empty,
		timeEnd: $empty, profile: $empty, profileEnd: $empty
	};
}
