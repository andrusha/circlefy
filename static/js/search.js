/*global _tap, OverText, Request, document, Elements, _template */

_tap.mixin({
    name: 'searching',

    initSearch: function (search, suggest, type) {
        if (!suggest) {
            return;
        }

        this.search = {
            input:         search,
            suggest:       suggest,
            list:          suggest.getElement('ul'),
            keyword:       null,
            type:          type,
            selected:      null
        };

        this.search.input.overtext = new OverText(this.search.input, {
            positionOptions: {
                offset: {x: 10, y: 9},
                relativeTo: this.search.input,
                relFixedPosition: false,
                ignoreScroll: true,
                ignoreMargin: true
            }
        }).show();

        this.search.input.addEvents({
            'blur': this.end.toHandler(this),
            'focus': this.start.toHandler(this),
            'keyup': this.checkKeys.bind(this)
        });
        this.search.input.getParent('form').addEvent('submit', function (e) { 
            e.stop(); 
        });
    },
 
    start: function (el) {
        this.search.suggest.removeClass('hidden');
    },

    /*
    handler: end()
        fired when the search input is blurred
    */
    end: function (el) {
        (function () {
            this.search.suggest.addClass('hidden');
        }).bind(this).delay(500);
    },

     /*
    handler: checkKeys()
        checks whether the search keys are valid or when enter is pressed
    */
    checkKeys: function (e) {
        // up, down
        if ([38, 40, 13].contains(e.code)) {
            e.stop();
            return this.navigate(e.code);
        }

        //skip all meta-keys except del & backspace
        if ((e.code < 48) && !([8, 46].contains(e.code))) { 
            return;
        }

        this.goSearch(e);
    },

    navigate: function (code) {
        var found    = this.search.list.getElements('li:not(.create)'),
            selected = this.search.selected || found.indexOf(this.search.list.getElement('li.selected')) || 0;

        if (!found.length) {
            return;
        }

        //enter
        if (code == 13) {
            found[selected].fireEvent('click');
            return;
        }

        if (code == 38) { //up
            selected = selected <= 0 ?  found.length - 1 : selected - 1;
        } else if (code == 40) { //down
            selected = selected == found.length - 1 ? 0 : selected + 1;
        }

        found.removeClass('selected');
        found[selected].addClass('selected');
        this.search.selected = selected;
    },

     /*
    handler: goSearch()
        performs the search
    */
    goSearch: function (e) {
        var keyword = this.search.input.value,
            self = this;
        //do not search for empty strings, strings < 2 chars & same keywords 
        if (!keyword.isEmpty() && keyword.length >= 1 && this.search.keyword != keyword) {
            if (!this.search.request)
                this.search.request = new Request.JSON({
                    url: '/AJAX/group/search',
                    link: 'cancel',
                    onSuccess: function (resp) {
                        this.search.selected = null;
                        this.onSearch(resp);
                        this.bindSearchEvents();
                    }.bind(this)
                });
            this.search.request.post({search: keyword, type: this.search.type});
        } else if (keyword.length <= 1) {
            this.search.list.empty();
        }

        this.search.keyword = keyword;
    },

    bindSearchEvents: function () {
        var found = this.search.list.getElements('li:not(.create)'),
            self  = this;

        found.addEvents({
            'mouseover': function (e) {
                if (!this.hasClass('selected')) {
                    found.removeClass('selected');
                }

                this.addClass('selected');
                self.search.selected = null;
            },
            'mouseout': function (e) {
                this.removeClass('selected');
                self.search.selected = null;
            },
            'click': function (e) {
                if (e) {
                    e.stop();
                }

                document.location.href = this.getElement('a').href;
            }
        });
    }
});

/*
module: _search
    controls the global searchbox
*/
var _search = _tap.register({
    mixins: 'searching',

    init: function () {
        this.initSearch($('group-search'), $('search-results'), 'withUsers');
    },

    onSearch: function (resp) {
        Elements.from(_template.parse('search', resp)).inject(this.search.list.empty());
        
        this.search.list.getElement('.create').addEvent('click', function (e) {
            e.stop();
            this.publish('modal.show.group.create', []);
        }.bind(this));
    }
});
