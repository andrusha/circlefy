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

Copyright and License:
	Copyright, 2010, Mark Obcena. MIT-style license.

Acknowledgements:
	Original inspired by Charlie Savages' simple templating engine.

*/

(function(){

this.Template = new Class({

	Implements: Options,

	options: {
		pattern: 'raccoon',
		path: '',
		suffix: ''
	},

	regexps: {

		raccoon: {
			pattern: /<#[:|=]?(.*?)#>/g,
			outkey: ':',
			include: '='
		},

		asp: {
			pattern: /<%[=|@]?(.*?)%>/g,
			outkey: '=',
			include: '@'
		},

		php: {
			pattern: /<\?[=|@]?(.*?)\?>/g,
			outkey: '=',
			include: '@'
		}
	},

	forEachExp: /for\s+\((?:var\s*)?(.*?)\s+from\s+(.*?)\s*\)\s*\{\s*([^]*?)\s*\}/g,
	eachExp: /each\s+\((?:var\s*)?(.*?)\s+from\s+(.*?)\s*\)\s*\{\s*([^]*?)\s*\}/g,
	shortTags: /(each|for|if|else|while)+(.*):/g,
	shortEnds: /end(for|if|while|each)?;/g,

	initialize: function(options){
		this.setOptions(options);

		var pattern = this.options.pattern,
			regexps = this.regexps,
			def = regexps.raccoon;
		if (typeOf(pattern) == 'object') {
			this.pattern = pattern.pattern || def.pattern;
			this.outkey = pattern.outkey || def.outkey;
			this.includes = pattern.include || def.include;
		} else {
			this.pattern = regexps[pattern].pattern || def.pattern;
			this.outkey = regexps[pattern].outkey || def.raccoon.outkey;
			this.includes = regexps[pattern].include || def.include;
		}
	},

	parseShortTags: function(str){
		return str.replace(this.shortTags, function(m, tag, exp){
			return [tag == 'else' ? '} ' : '', tag, exp, '{'].join('');
		}).replace(this.shortEnds, '}');
	},

	parseForFrom: function(str){
		return str.replace(this.forEachExp, function(m, key, arr, body){
			return [
				"for (var _ITERATOR_ = 0, _ARRAYLENGTH_ = ",
				arr, ".length; _ITERATOR_ < _ARRAYLENGTH_; _ITERATOR_++){\n",
				"\tvar ", key, " = ", arr, "[_ITERATOR_];\n\t",
				body,
				"\n}"
			].join('');
		});
	},

	parseEachFrom: function(str){
		return str.replace(this.eachExp, function(m, key, arr, body){
			return [
				"var _ITERATOR_ = ", arr, ".reverse().length;\nwhile(_ITERATOR_--){",
				"\tvar ", key, " = ", arr, "[_ITERATOR_];\n\t",
				body,
				"\n}"
			].join('');
		});
	},

	escape: function(str){
		return str.replace(/'/g, '%%LIT_QUOT_SING%%').replace(/"/g, '%%LIT_QUOT_DB%%').replace(/\r|\n/g, '%%LIT_NEW_LINE%%');
	},

	unescape: function(str){
		return str.replace(/%%LIT_QUOT_SING%%/g, "'").replace(/%%LIT_QUOT_DB%%/g, '"').replace(/%%LIT_NEW_LINE%%/g, "\n");
	},

	build: function(str, data){
		var self = this, func, result,
			literal = this.outkey,
			include = this.includes;
		str = this.escape(this.parseEachFrom(this.parseForFrom(this.parseShortTags(str))));
		str = str.replace(this.pattern, function(match, item){
			item = self.unescape(item);
			var chunk, external;
			if (match.charAt(2) == literal) {
				chunk = ['buffer.push(', item, ');\n'];
			} else if (match.charAt(2) == include) {
				external = self.process(item.trim().replace(/"|'/g, ''), data);
				chunk = ['buffer.push("', self.escape(external), '");\n'];
			} else {
				chunk = [item.replace(/^\s+|\s+$/g, ''), '\n'];
			}
			return ['");\n', chunk.join(''), 'buffer.push("'].join('');
		});
		return [
				'var $ = this, buffer = [], print = function(data){ buffer.push(data); },\n',
				'include = function(src){ buffer.push($._include(src, $)); };\n',
				'\nbuffer.push("', str, '");\n',
				'return buffer.join("");\n'
			].join('');
	},

	peek: function(str){
		return this.build(str);
	},

	parse: function(str, data){
		var self = this;
		// return this.peek(str);
		data._include = function(src, data){
			return self.escape(self.process(src, data));
		};
		var func = this.build(str, data),
			result = new Function(func).apply(data);
		delete data._include;
		return this.unescape(result);
	},

	process: function(file, data){
		var name = [this.options.path, file, '.', this.options.suffix].join('');
		file = new File(name);
		if (!file.exists()) throw new Error('Cannot open template ' + name);
		var str = file.open("r").read();
		return this.parse(str, data);
	}

});

})();

/*
Script: Validator.js
	Basic Validation

License:
	Copyright 2009, Mark Obcena <markobcena@gmail.com>
	MIT-style license.
*/

var Validator=new Hash({exps:new Hash({alpha:/^[a-zA-z\s\D]+$/,alphaStrict:/^[a-zA-z]+$/,alphaNum:/^[0-9a-zA-Z\s\D]+$/,alphaNumStrict:/^[0-9a-zA-Z]+$/,number:/^[0-9]+$/,email:/^[a-z0-9_+.-]+\@([a-z0-9-]+\.)+[a-z0-9]{2,4}$/,URL:/https?:\/\/([0-9a-zA-Z][-\w]*[0-9a-zA-Z]\.)+([a-zA-Z]{2,9})(:\d{1,4})?([-\w\/#~:.?+=&%@~]*)/}),test:function(B,A){return($type(B)=="string")?(this.exps.get(A)||this.exps.get("alphaNum")).test(B):null;},isEmpty:function(A){return A.replace(/\s/g,"").length==0;},ofLength:function(C,B,A){B=B||0;A=A||10000000000000000;return C.length>=B&&C.length<=A;},isEqual:function(B,A){return B==A;},addType:function(B,C){if($type(B)=="string"&&$type(C)=="regexp"){var A="is"+B.capitalize();this.exps.set(B,C);this.set(A,function(D){return this.test(D,B);});return true;}else{return false;}},addTypes:function(B){var A=this;if($type(B)=="object"){B=$H(B);B.each(function(D,C){A.addType(C,D);});return true;}else{return false;}}});(function(){Validator.exps.each(function(B,A){Validator.addType(A,B);});})();if($type(Validator)=="hash"){Validator.addType=function(B,C){if($type(B)=="string"&&$type(C)=="regexp"){var A="is"+B.capitalize();this.exps.set(B,C);this.set(A,function(E){return this.test(E,B);});var D={};D[A]=function(){return this.validate(B);};Native.implement([String,Element],D);return true;}else{return false;}};String.implement({validate:function(A){return Validator.test(this,A);},isEmpty:function(){return Validator.isEmpty(this);},ofLength:function(B,A){return Validator.ofLength(this,B,A);},isEqual:function(A){return Validator.isEqual(A,this);}});Validator.Stringable=["input","textarea"];Validator.canValidate=function(A){return Validator.Stringable.contains(A.get("tag"));};Element.implement({validate:function(A){return Validator.canValidate(this)?Validator.test(this.value,A):null;},isEmpty:function(){return Validator.canValidate(this)?Validator.isEmpty(this.value):null;},ofLength:function(B,A){return Validator.canValidate(this)?Validator.ofLength(this.value,B,A):null;},isEqual:function(A){return Validator.canValidate(this)?Validator.isEqual(A,this.value):null;}});(function(){var A={};Validator.exps.getKeys().each(function(C){var B="is"+C.capitalize();A[B]=function(){return this.validate(C);};});Native.implement([String,Element],A);})();}

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
	
	stash: function(attrib, value){
		return this.set('data-' + attrib, value);
	},
	
	fetch: function(attrib){
		return this.get('data-' + attrib);
	}

});

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

if (!window.console) {
	window.console = {
		log: $empty, debug: $empty, info: $empty, warn: $empty, error: $empty,
		assert: $empty, dir: $empty, dirxml: $empty, trace: $empty, time: $empty,
		timeEnd: $empty, profile: $empty, profileEnd: $empty
	};
}
