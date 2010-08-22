/*
script: tap.js
	Main script; Needed for the entire site.
*/

/*
global: _tap
	The global observer object.
*/
window._tap = new Observer();

/*
global: _body and _head
	Shortcuts for document.body and document.head
*/
_tap.register({
	init: function(){
		window._body = $(document.body);
		window._head = $(document.head);
	}

});

/*
module: _live
	namespace for live push events
*/
var _live = {};

/*
 * If we include tap, we'll anyway need
 * some additional global functions, so,
 * here it is:
*/

/*
local: _template
	The templater object.
*/
var _template = {

    templater: new Template(),
    prepared: {},

	/*
	prop: map
		a mapping of the type and the id in #templates
	*/
    map: {
        'taps': 'template-taps',
        'responses': 'template-responses',
        'list.convo': 'template-list-convo',
        'suggest.group': 'template-suggest-group',
        'list.member': 'template-list-member',
        'error': 'template-error',
        'number': 'template-notification-number'
    },

	/*
	method: parse()
		generates the html from the template using the
		data passed
		
		args:
		1. type (string) type of template corresponding to the map key
		2. data (object) data to use in parsing the template
		
		returns:
		- (string) html content of the template evaled with the data
	*/
    parse: function(type, data) {
        var template = this.prepared[type];
        if (!template) {
            template = this.map[type];
            if (!template) return '';
            template = this.prepared[type] = $(template).innerHTML.cleanup();
        }
        return this.templater.parse(template, data);
    }

};

/*
local: _dater
	Controls fancy date updating
*/
var _dater = _tap.register({

    init: function() {
        var self = this;
        this.changeDates();
        this.changeDates.periodical(60000, this);
        this.subscribe({
            'dates.update; stream.updated; responses.updated': this.changeDates.bind(this)
        });
    },

	/*
	method: changeDates
		Goes to each element with the data-attrib 'timestamp'
		and updates their dates.
	*/
    changeDates: function() {
        var items = _body.getElements("[data-timestamp]");
        items.each(function(el) {
            var timestamp = el.getData('timestamp') * 1;
            el.set('text', _dater.timestampToStr(timestamp));
        });
    },

    /*
    method: timestampToStr
        Convert timestamp to human-readable format
        with X min/hour/day/week ago stuff

        args:
        1.timestamp integer

        returns:
        -string represents time
    */
    timestampToStr: function(timestamp) {
        var now = new Date().getTime(),
                orig = new Date(timestamp * 1000),
                diff = ((now - orig) / 1000),
                day_diff = Math.floor(diff / 86400);

        if (diff < 0)
            return "Just Now";

        if (isNaN(timestamp) || $type(diff) == false || day_diff >= 31)
            return orig.format('jS M Y');

        return day_diff == 0 && (
                diff < 120 && "Just Now" ||
                        diff < 3600 && Math.floor(diff / 60) + "min ago" ||
                        diff < 7200 && "An hour ago" ||
                        diff < 86400 && Math.floor(diff / 3600) + " hours ago") ||
                day_diff == 1 && "Yesterday" ||
                day_diff < 7 && day_diff + " days ago" ||
                day_diff == 7 && "A week ago" ||
                day_diff < 31 && Math.ceil(day_diff / 7) + " weeks ago";
    }

});

/*
 * module: _notifications
 *
 * Uses to notify user about some events/errors
 */
var _notifications = new Roar({
    position: 'lowerRight'
});
