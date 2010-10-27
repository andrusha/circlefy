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
 * namespace for all editing classes
 */
var _edit = {};

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
    compiled: {},

	/*
	prop: map
		a mapping of the type and the id in #templates
	*/
    map: {
        'search':      'template-search',
        'replies':     'template-replies',
        'suggestions': 'template-suggestions',
        'typing':      'template-typing',
        'messages':    'template-messages',
        'circles':     'template-circles'
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
        var template = this.compiled[type];
        if (!template) {
            template = this.map[type];
            if (!template) return '';
            template = this.compiled[type] = this.templater.compile($(template).innerHTML.cleanup());
        }
        return template.apply(data);
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
            if (timestamp)
                el.text = Date.timeDiffInWords(new Date(timestamp*1000));
        });
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


var _tooltips = _tap.register({
    init: function() {
        this.subscribe('feed.init; feed.changed', function() {
            new CirTooltip({
               hovered: $$('#feed .avatar-author, #feed .circle'),
               template: 'name-tooltip',
               position: 'top',
               align: 'center'
            });
        }.bind(this));
        this.subscribe('groups.get', function() {
            new CirTooltip({
                hovered: $$('#sidebar .circle-thumb'),
                template: 'side-tooltip'
            });
            new CirTooltip({
                hovered: $$('#experience .circle-thumb'),
                template: 'title-desc-tooltip'
            });
        }.bind(this));
    },
});

// To be called when DOM is loaded
var _startup = _tap.register({
    init: function() {
        // Tooltips
        new CirTooltip({
            hovered: $$('#sidebar .follower-thumb'),
            template: 'follow-tooltip',
            position: 'top',
			align: 'center'
        });
        new CirTooltip({
            hovered: $$('#sidebar .circle-thumb'),
            template: 'side-tooltip',
            position: 'top',
			align: 'center'
        });
        new CirTooltip({
            hovered: $$('#experience .circle-thumb'),
            template: 'title-desc-tooltip'
        });
        
        // Links detector
        new MediaEmbed({
            element: $$('#reply textarea')
        });
        
        // Media viewer
        var self = this;
        this.subscribe('feed.init; feed.changed; feed.updated', function() {
            var elems = $$('#feed .display-video, #feed .display-image');
            elems.each( function(elem) {
                if (elem.hasClass('display-video')) {
                    elem.addEvent('click', function(e) {
                        e.stop();
                        var close_btn = e.target.getParent('div.message').getElement('.video-embed-close');
                            embed_div = e.target.getParent('div.message').getElement('.video-embed');
                            thumbnail = e.target.getParent('a.display-video');
                        embed_div.removeClass('hidden');
                        close_btn.removeClass('hidden');
                        thumbnail.addClass('hidden');
                        close_btn.addEvent('click', function(e) { 
                            e.stop();
                            embed_div.addClass('hidden');
                            close_btn.addClass('hidden');
                            thumbnail.removeClass('hidden');
                        });
                    }.bind(this));
                } else {
                    elem.addEvent('click', function(e) {
                        var sizes = e.target.getParent().getData('embedcode').split(','),
                            url   = e.target.getParent().getData('imageurl'),
                            embed = '<img src="'+ url +'" width="'+ sizes[0] +'" height="'+ sizes[1] +'" />';
                        self.publish('modal.show.image-display', [embed, sizes]);
                    }.bind(this));
                }
            });
        }.bind(this));
    }
});
