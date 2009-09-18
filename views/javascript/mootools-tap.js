/*
Script: Element.Delegation.js
	Extends the Element native object to include the delegate method for more efficient event management.
*/
(function(){

	var check = function(e, test){
				for (var t = e.target; t && t != this; t = t.parentNode)
			if (Element.match(t, test)) return $(t);
	};

	var regs = {
		test: /^.*:relay?\(.*?\)$/,
		event: /.*?(?=:relay\()/,
		selector: /^.*?:relay\((.*)\)$/,
		warn: /^.*?\(.*?\)$/
	};

	var splitType = function(type){
		if (type.test(regs.test)){
			return {
				event: type.match(regs.event)[0],
				selector: type.replace(regs.selector, "$1")
			};
		} else if (type.test(/^.*?\(.*?\)$/)) {
			if (window.console && console.warn) {
				console.warn('The selector ' + type + ' could not be delegated; the syntax has changed. Check the documentation.');
			}
		}
		return {event: type};
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
								var events = this.retrieve('events');
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
								fn.create({'bind': bind||this, 'delay': delay, 'arguments': args})();
			}, this);
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
Script: Tap.Extensions.js
	Additional native methods used in Tap
*/

Function.implement({

	toHandler: function(bound){
		var func = this;
		return function(e){
			e.preventDefault();
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

	rtrim: function(str) {
		if (this.lastIndexOf(str) == this.length - 1) return this.substring(0, this.lastIndexOf(str));
		return this;
	}

});