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