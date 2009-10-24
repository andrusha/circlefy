/*
Script: Element.Delegation.js
	Extends the Element native object to include the delegate method for more efficient event management.
*/
(function() {

    var check = function(e, test) {
        for (var t = e.target; t && t != this; t = t.parentNode)
        if (Element.match(t, test)) return $(t);
    };

    var regs = {
		test: /^.*:relay?\(.*?\)$/,
		event: /.*?(?=:relay\()/,
		selector: /^.*?:relay\((.*)\)$/,
		warn: /^.*?\(.*?\)$/
	};

    var splitType = function(type) {
        if (type.test(regs.test)) {
            return {
                event: type.match(regs.event)[0],
                selector: type.replace(regs.selector, "$1")
            };
        } else if (type.test(/^.*?\(.*?\)$/)) {
            if (window.console && console.warn) {
                console.warn('The selector ' + type + ' could not be delegated; the syntax has changed. Check the documentation.');
            }
        }
        return {
            event: type
        };
    };

    var oldAddEvent = Element.prototype.addEvent,
    oldRemoveEvent = Element.prototype.removeEvent;
    Element.implement({
        addEvent: function(type, fn) {
            var splitted = splitType(type);
            if (splitted.selector) {
                var monitors = this.retrieve('$moo:delegateMonitors', {});
                if (!monitors[type]) {
                    var monitor = function(e) {
                        var el = check.call(this, e, splitted.selector);
                        if (el) this.fireEvent(type, [e, el], 0, el);
                    }.bind(this);
                    monitors[type] = monitor;
                    oldAddEvent.call(this, splitted.event, monitor);
                }
            }
            return oldAddEvent.apply(this, arguments);
        },
        removeEvent: function(type, fn) {
            var splitted = splitType(type);
            if (splitted.selector) {
                var events = this.retrieve('events');
                if (!events || !events[type] || (fn && !events[type].keys.contains(fn))) return this;
                if (fn) oldRemoveEvent.apply(this, [type, fn]);
                else oldRemoveEvent.apply(this, type);
                var events = this.retrieve('events');
                if (events && events[type] && events[type].length == 0) {
                    var monitors = this.retrieve('$moo:delegateMonitors', {});
                    oldRemoveEvent.apply(this, [splitted.event, monitors[type]]);
                    delete monitors[type];
                }
                return this;
            }
            return oldRemoveEvent.apply(this, arguments);
        },
        fireEvent: function(type, args, delay, bind) {
            var events = this.retrieve('events');
            if (!events || !events[type]) return this;
            events[type].keys.each(function(fn) {
                fn.create({
                    'bind': bind || this,
                    'delay': delay,
                    'arguments': args
                })();
            },
            this);
            return this;
        }
    });

})();


/*
Script: Template.jx
	Basic templating system.

License:
	Copyright 2009, Mark Obcena <markobcena@gmail.com>
	MIT-style license.

Acknowledgements:
	Code inspired by Charlie Savages' simple templating engine.
*/

var Template = new Class({

	Implements: Options,

	options: {
		pattern: 'raccoon',
		path: '',
		suffix: ''
	},

	regexps: {

		raccoon: {
			pattern: /<#\:?(.*?)#>/g,
			outkey: ':'
		},

		asp: {
			pattern: /<%=?(.*?)%>/g,
			outkey: '='
		},

		php: {
			pattern: /<\?=?(.*?)\?>/g,
			outkey: '='
		}
	},

	initialize: function(options){
		this.setOptions(options);

		var pattern = this.options.pattern;
		if ($type(pattern) == 'object') {
			this.pattern = pattern.pattern || this.regexps.raccoon.pattern;
			this.outkey = pattern.outkey || this.regexps.raccoon.outkey;
		} else {
			this.pattern = this.regexps[pattern].pattern || this.regexps.raccoon.pattern;
			this.outkey = this.regexps[pattern].outkey || this.regexps.raccoon.outkey;
		}
	},

	parse: function(str, data){
		str = str.replace(/\n/g, '%%%');
		var outkey = this.outkey;
		var del = '_%_', delexp = /_%_/g;
		str = str.replace(this.pattern, function(match, item){
			var chunk = (match.charAt(2) == outkey ? ['buffer  += ', item, ';\n'] : [item, '\n']).join('');
			var buffer = [del, ';\n', chunk];
			buffer.push('buffer += '+ del);
			return buffer.join('');
		});
		var func = ['var buffer = ', del, str, del, ';\n', 'return buffer;\n'].join('');
		func = func.replace(/'/g, "\\'").replace(delexp, "'");
		return new Function(func).apply(data).replace(/%%%/g, '\n');
	},

	process: function(file, data){
		var name = [this.options.path, file, '.', this.options.suffix].join('');
		var file = new File(name);
		if (!file.exists()) throw new Error('Cannot open template ' + name);
		var str = file.open("r").read();
		return this.parse(str, data);
	}

});

/*
Script: Validator.js
	Basic Validation

License:
	Copyright 2009, Mark Obcena <markobcena@gmail.com>
	MIT-style license.
*/

var Validator=new Hash({exps:new Hash({alpha:/^[a-zA-z\s\D]+$/,alphaStrict:/^[a-zA-z]+$/,alphaNum:/^[0-9a-zA-Z\s\D]+$/,alphaNumStrict:/^[0-9a-zA-Z]+$/,number:/^[0-9]+$/,email:/^[a-z0-9_+.-]+\@([a-z0-9-]+\.)+[a-z0-9]{2,4}$/,URL:/https?:\/\/([0-9a-zA-Z][-\w]*[0-9a-zA-Z]\.)+([a-zA-Z]{2,9})(:\d{1,4})?([-\w\/#~:.?+=&%@~]*)/}),test:function(B,A){return($type(B)=="string")?(this.exps.get(A)||this.exps.get("alphaNum")).test(B):null;},isEmpty:function(A){return A.replace(/\s/g,"").length==0;},ofLength:function(C,B,A){B=B||0;A=A||10000000000000000;return C.length>=B&&C.length<=A;},isEqual:function(B,A){return B==A;},addType:function(B,C){if($type(B)=="string"&&$type(C)=="regexp"){var A="is"+B.capitalize();this.exps.set(B,C);this.set(A,function(D){return this.test(D,B);});return true;}else{return false;}},addTypes:function(B){var A=this;if($type(B)=="object"){B=$H(B);B.each(function(D,C){A.addType(C,D);});return true;}else{return false;}}});(function(){Validator.exps.each(function(B,A){Validator.addType(A,B);});})();if($type(Validator)=="hash"){Validator.addType=function(B,C){if($type(B)=="string"&&$type(C)=="regexp"){var A="is"+B.capitalize();this.exps.set(B,C);this.set(A,function(E){return this.test(E,B);});var D={};D[A]=function(){return this.validate(B);};Native.implement([String,Element],D);return true;}else{return false;}};String.implement({validate:function(A){return Validator.test(this,A);},isEmpty:function(){return Validator.isEmpty(this);},ofLength:function(B,A){return Validator.ofLength(this,B,A);},isEqual:function(A){return Validator.isEqual(A,this);}});Validator.Stringable=["input","textarea"];Validator.canValidate=function(A){return Validator.Stringable.contains(A.get("tag"));};Element.implement({validate:function(A){return Validator.canValidate(this)?Validator.test(this.value,A):null;},isEmpty:function(){return Validator.canValidate(this)?Validator.isEmpty(this.value):null;},ofLength:function(B,A){return Validator.canValidate(this)?Validator.ofLength(this.value,B,A):null;},isEqual:function(A){return Validator.canValidate(this)?Validator.isEqual(A,this.value):null;}});(function(){var A={};Validator.exps.getKeys().each(function(C){var B="is"+C.capitalize();A[B]=function(){return this.validate(C);};});Native.implement([String,Element],A);})();}


/*
UvumiTools TextArea v1.1.0 http://tools.uvumi.com/textarea.html

Copyright (c) 2008 Uvumi LLC

Permission is hereby granted, free of charge, to any person
obtaining a copy of this software and associated documentation
files (the "Software"), to deal in the Software without
restriction, including without limitation the rights to use,
copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the
Software is furnished to do so, subject to the following
conditions:

The above copyright notice and this permission notice shall be
included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
OTHER DEALINGS IN THE SOFTWARE.
*/

var UvumiTextarea = new Class({

	Implements:Options,

	options:{
		selector:'textarea',	//textareas CSS selector, default 'textarea' select all textboxes in the document. ALSO ACCEPTS AN ELEMENT OR AN ID

		maxChar:1000,			//maximum number of characters per textarea. SET TO 0 OR FALSE TO DESACTIVATE COUNTER

		resizeDuration:250,		//animation duration of progress bar and resizing, in milliseconds

		minSize:false,			//minimum height in pixels you can reduce the textarea to. If set to false, the default value, the original textarea's height will be used as a minimum

		catchTab:true,			//if the textarea should override the tab default event and insert a tab in the text. Default is true, but if you're not going to support it on the back-end, you should disable it

		classPrefix:'tb'		//The CSS classes associated to the new elements will start with the string defined in this option.
								//Usefull if you use this plugin on several pages with different styles. if you have a red theme and a blue theme,
								//initialize an instance with classPrefix:'red' and another with classPrefix:'blue', and you'll just have to create a set of CSS rules
								//redControls, redProgress, redProgressBar, redCounter and another where you replace red by blue, blueControls, blueProgress...
	},

	initialize: function(options){
		this.setOptions(options);
		//each textarea will have its own elements, all storred in arrays
		this.tbDummies =[];
		this.tbCounters =[];
		this.tbProgress = [];
		this.tbProgressBar = [];
		window.addEvent('domready',this.domReady.bind(this));
	},

	domReady: function(){
		//the text area array is initialized with an optional CSS selector. The default selector is just 'textarea', affecting all textareas in the document
		if($(this.options.selector)){
			this.options.selector = $(this.options.selector);
		}
		this.textareas=$$(this.options.selector);
		this.textareas.each(this.buildProgress,this);
		if(this.options.maxChar){
			this.tbProgressEffects = new Fx.Elements(this.tbProgressBar,{
				duration:'short',
				link:'cancel'
			});
		}
		this.tbEffects = new Fx.Elements(this.textareas,{
			duration:this.options.resizeDuration,
			link:'cancel'
		});
		this.textareas.each(function(el,i){
			var value = el.get('value');
			this.previousLength = value.length;
			if(this.options.maxChar){
				if(this.previousLength > this.options.maxChar){
					value = value.substring(0, this.options.maxChar);
					this.previousLength = value.length;
					el.set('value', value);
				}
				var count = this.options.maxChar - this.previousLength;
				var percentage = (count * this.tbProgress[i].getSize().x / this.options.maxChar).toInt();
				this.tbProgressBar[i].setStyle('width',percentage);
				if(!count){
					var ct = 'No character left';
				}else if(count == 1){
					var ct = '1 character left';
				}else{
					var ct = count + ' characters left';
				}
				this.tbCounters[i].set('text',ct);
			}
			this.tbDummies[i].set('value',value);
			var height = (this.tbDummies[i].getScrollSize().y>this.options.minSize?this.tbDummies[i].getScrollSize().y:this.options.minSize);
			if(this.tbDummies[i].retrieve('height')!=height){
				this.tbDummies[i].store('height',height);
				el.setStyle('height',height);
			}
		},this);

	},

	//this functions builds all the new HTML elements and assigns events
	buildProgress: function(textbox,i){
		textbox.setStyle('overflow','hidden');
		//if minimum size option is false, we use the original size as minimum.
		if(!this.options.minSize){
			this.options.minSize = textbox.getSize().y;
		}

		//This will not be visible by user. It's div with the exact same specification as the textarea : same size, same font, same padding, same line-height....
		//on every key stroke, the textarea content is copied in this div, and if the div size is different from on previous key stroke, the textarea grow or shrink to this new height.
		//we had to use this hack because if working diretly with the textarea itself, comparing it's height and scroll-height, it wored fine for growing, but there was to good looking way to make it shrink to the right position.

		this.tbDummies[i] = textbox.clone().setStyles({
				'width':textbox.getStyle('width').toInt(),
				'position':'absolute',
				'top':0,
				'height':this.options.minSize,
				'left':-3000
		}).store('height',0).inject($(document.body));

		textbox.addEvents({
			'keydown':this.onKeyPress.bindWithEvent(this,[i,this.options.catchTab]), // here and like on all the other events, we must use bindWithEvent because we pass an additionnal parameter beside the event object
			'keyup':this.onKeyPress.bindWithEvent(this,i),
			'focus':this.startObserver.bind(this,i),
			'blur':this.stopObserver.bind(this)
		});

		if(this.options.maxChar){
			this.tbProgress[i]=new Element('div',{
				'class':this.options.classPrefix+'Progress',
				'styles':{
					'position':'relative',
					'overflow':'hidden',
					'display':'block',
					'position':'relative',
					'width':textbox.getSize().x-1,
					'margin':'5px 0 5px '+textbox.getPosition(textbox.getParent()).x+'px'
				}
			}).inject(textbox,'after');
			this.tbProgressBar[i]=new Element('div',{
				'class':this.options.classPrefix+'ProgressBar',
				'styles':{
					'position':'absolute',
					'top':0,
					'left':0,
					'height':'100%',
					'width':'100%'
				}
			}).inject(this.tbProgress[i]);
			this.tbCounters[i] = new Element('div', {
				'class':this.options.classPrefix+'Counter',
				'styles':{
					'position':'absolute',
					'top':0,
					'left':0,
					'height':'100%',
					'width':'100%',
					'text-align':'center'
				}
			}).inject(this.tbProgress[i]);
			this.update = this.updateCounter;
		}else{
			this.update = this.updateNoCounter;
		}
	},

	onKeyPress: function(event,i,tab) {
		if(tab && event.key == "tab"){
			event.preventDefault();
			this.insertTab(i);
		}
		if(!event.shift && !event.control && !event.alt && !event.meta){
			this.update(i);
		}
		this.startObserver(i);
	},

	startObserver:function(i){
		$clear(this.observer);
		this.observer = this.observe.periodical(500,this,i);
	},

	stopObserver:function(){
		$clear(this.observer);
	},

	observe:function(i){
		if(this.textareas[i].get('value').length != this.previousLength){
			this.previousLength = this.textareas[i].get('value').length;
			this.update(i);
		}
	},

	updateCounter: function(i) {
		var value = this.textareas[i].get('value');
		if(value.length > this.options.maxChar){
			value =  value.substring(0, this.options.maxChar);
			this.textareas[i].set('value',value);
		}
		this.previousLength = value.length;
		var count = this.options.maxChar - this.previousLength;
		var percentage = (count * this.tbProgress[i].getSize().x / this.options.maxChar).toInt();
		var effect = {};
		effect[i]={'width':percentage};
		this.tbProgressEffects.start(effect);
		if (count == 0) {
			var ct = 'No character left';
			this.tbProgress[i].highlight("#f66");
		}else if (count == 1){
			var ct = '1 character left';
		}else{
			var ct = count + ' characters left';
		}
		this.tbCounters[i].set('text',ct);
		this.updateHeight(i,value);
	},

	updateNoCounter:function(i){
		var value = this.textareas[i].get('value');
		this.previousLength = value.length;
		this.updateHeight(i,value);
	},

	updateHeight: function(i,value){
		this.tbDummies[i].set('value',value);
		var height = (this.tbDummies[i].getScrollSize().y>this.options.minSize?this.tbDummies[i].getScrollSize().y:this.options.minSize);
		if(this.tbDummies[i].retrieve('height')!=height){
			this.tbDummies[i].store('height',height);
			effect = {};
			effect[i]={'height':height};
			this.tbEffects.start(effect);
		}
	},

	insertTab: function(i){
		if(Browser.Engine.trident) {
			var range = document.selection.createRange();
			range.text = "\t";
		}else{
			var start = this.textareas[i].selectionStart;
			var end = this.textareas[i].selectionEnd;
			var value = this.textareas[i].get('value');
			this.textareas[i].set('value', value.substring(0, start) + "\t" + value.substring(end, value.length));
			start++;
			this.textareas[i].setSelectionRange(start, start);
		}
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

// GENERALS

window.addEvent('domready', function(){
	var logo = $('logo');
	if (logo) logo.set('styles', {cursor: 'pointer'}).addEvent('click', function(){ window.location = '/'; });
});
