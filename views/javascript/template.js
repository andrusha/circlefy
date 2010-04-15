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
		console.log(func,data);
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
