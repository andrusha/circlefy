/**
 * Roar - Notifications
 *
 * Inspired by Growl
 *
 * @version		1.0.1
 *
 * @license		MIT-style license
 * @author		Harald Kirschner <mail [at] digitarald.de>
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
		var items = [new Element('h3', {'html': $pick(params.title, '')+'<div>click to dismiss</div>'})];
		if (params.message) items.push(new Element('p', {'html': params.message}));
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

		var item = new Element('div', {
			'class': this.options.className,
			'opacity': 0
		}).adopt(
			new Element('div', {
				'class': 'roar-bg',
				'opacity': 0.7
			}),
			elements
		);

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
