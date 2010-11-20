/*
module: _search
    controls the global searchbox
*/
var _search = _tap.register({

    init: function(){
        var search = this.search = $('group-search');

        this.suggest = $('search-results');
        this.list = this.suggest.getElement('ul');
        this.keyword = null;
        this.last_keypress = new Date().getTime()/1000;

        search.overtext = new OverText(search, {
            positionOptions: {
                offset: {x: 10, y: 9},
                relativeTo: search,
                relFixedPosition: false,
                ignoreScroll: true,
                ignoreMargin: true
            }}).show();
        search.addEvents({
            'blur': this.end.toHandler(this),
            'focus': this.start.toHandler(this),
            'keyup': this.checkKeys.bind(this)
        });
        /*
        this.list.addEvents({
            'click:relay(li)': function (e) {
                this.publish('modal.show.group.create', []);
            }.bind(this)
        });
        */
    },

    start: function(el) {
        this.suggest.removeClass('hidden');
    },

    /*
    handler: end()
        fired when the search input is blurred
    */
    end: function(el){
        (function(){
            this.suggest.addClass('hidden');
        }).bind(this).delay(500);
    },

    /*
    handler: checkKeys()
        checks whether the search keys are valid or when enter is pressed
    */
    checkKeys: function(e){
        //var updown = ({'down': 1, 'up': -1})[e.key];
        //if (updown) return this.navigate(updown);
        if (e.key == 'enter') return this.goSearch(e);
        //skip all meta-keys except del & backspace
        if ((e.code < 48) && !([8, 46].contains(e.code)))  return;

        //2 - minimal timeout before searches
        var now = new Date().getTime()/1000,
            delta = 0.5 - (now - this.last_keypress);
        this.last_keypress = now;

        Elements.from($('template-search-placeholder').innerHTML.cleanup()).inject(this.list.empty());
        if (delta < 0) {
            clearTimeout(this.search_event);
            this.goSearch(e);
        } else {
            clearTimeout(this.search_event);
            this.search_event = (function () {
                this.goSearch(e);
            }).delay(delta*1000, this);
        }
    },

    /*
    method: navigate()
        controls the up/down keys for the search results
        
        args:
        1. updown (number) the number of movements to do (up: positive, down: negative)
    */
    navigate: function(updown){
        var current = (this.selected !== null) ? this.selected : -1,
            items = this.list.getElements('li:not(.notice)');
        var selected = this.selected = current + updown;
        if (items.length != 0 && !(selected < 0) && selected < items.length){
            items.removeClass('selected');
            items[selected].addClass('selected');
        } else {
            this.selected = (selected < 0) ? 0 : items.length - 1;
        }
    },

    /*
    handler: goSearch()
        performs the search
    */
    goSearch: function(e){
        var keyword = this.search.value,
            self = this;
        //do not search for empty strings, strings < 2 chars & same keywords 
        if (!keyword.isEmpty() && keyword.length > 1 && this.keyword != keyword){
            if (!this.request)
                this.request = new Request({
                    url: '/AJAX/group/search',
                    link: 'cancel',
                    onSuccess: function () {
                        var resp = JSON.decode(this.response.text);
                        Elements.from(_template.parse('search', resp)).inject(self.list.empty());
                        
                        
                        self.list.getElement('.create').addEvent('click', function(e){
                            e.stop();
                            self.publish('modal.show.group.create', []);
                        });
                        
                    }
                });
            this.request.send({data: {search: keyword}});
        } else if (keyword.length <= 1)
            this.list.empty();
        this.keyword = keyword;
    }
});
