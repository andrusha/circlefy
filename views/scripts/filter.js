/*
 * script: filter.js
 *
 * Controls filter/search bar for tapstream
*/

/*
module: _filter
	Controls the filter/search bar for the tapstream
*/
var _filter = _tap.register({

    init: function() {
        this.box = $('filter');
        if (!this.box) return;
        this.group = _vars.filter.gid;
        this.info = _vars.filter.info;
        this.title = this.box.getElement('span.title');
        this.clearer = this.box.getElement('a.clear');
        this.filter = $('filterkey');
        this.filter.addEvents({
            'keydown': this.checkKeys.bind(this)
        });
        this.clearer.addEvent('click', this.clearSearch.bind(this));
        this.subscribe({
            'list.item': this.change.bind(this)
        });
    },

	/*
	method: change()
		changes the filter box depending on the list item clicked
		
		args:
		1. type (string) the type of the item clicked
		2. id (string) the id of the item click
		3. info (obj) additional info for the item
	*/
    change: function(type, id, info) {
        var box = this.box;
        if (type == 'channels') {
            this.group = id;
            this.info = info;
            box.slide('in');
            this.setTitle(info.symbol || info.name);
        } else {
            this.group = null;
            this.info = null;
            box.slide('out');
        }
        this.filter.set('value', '');
    },

	/*
	method: setTitle()
		sets the filterbox's title
		
		args:
		1. title (string) the title to be displayed
	*/
    setTitle: function(title) {
        this.title.set('text', title.toLowerCase());
        return this;
    },

	/*
	method: search()
		main control logic for searching
		
		args:
		1. keyword (string, opt) the keyword to use for searching; if null, the search is cleared
	*/
    search: function(keyword) {
        if (!!keyword) {
            this.active = true;
            this.clearer.addClass('active');
        } else {
            this.active = false;
            this.clearer.removeClass('active');
        }
        this.publish('filter.search', [this.group, this.info, keyword || null]);
        return this;
    },

	/*
	handler: checkKeys()
		checks whether the enter key is pressed and performs a search
	*/
    checkKeys: function(e) {
        var keyword = $(e.target).get('value');
        if (this.group && e.key == 'enter') this.search(keyword);
    },

	/*
	method: clearSearch()
		resets the search data
	*/
    clearSearch: function(e) {
        e.preventDefault();
        if (!this.active) return this;
        this.filter.set('value', '');
        this.search();
        return this;
    }

});

