_tap.mixin({
    name: 'searching',

    initSearch: function(search, suggest, type) {
        var search = this.search = {
            input:         search,
            suggest:       suggest,
            list:          suggest.getElement('ul'),
            keyword:       null,
            last_keypress: new Date().getTime()/1000,
            type:          type};

        search.input.overtext = new OverText(search.input, {
            positionOptions: {
                offset: {x: 10, y: 9},
                relativeTo: search.input,
                relFixedPosition: false,
                ignoreScroll: true,
                ignoreMargin: true
            }}).show();

        search.input.addEvents({
            'blur': this.end.toHandler(this),
            'focus': this.start.toHandler(this),
            'keyup': this.checkKeys.bind(this)
        });
    },
 
    start: function(el) {
        this.search.suggest.removeClass('hidden');
    },

    /*
    handler: end()
        fired when the search input is blurred
    */
    end: function(el){
        (function(){
            this.search.suggest.addClass('hidden');
        }).bind(this).delay(500);
    },

     /*
    handler: checkKeys()
        checks whether the search keys are valid or when enter is pressed
    */
    checkKeys: function(e){
        if (e.key == 'enter') return this.goSearch(e);
        //skip all meta-keys except del & backspace
        if ((e.code < 48) && !([8, 46].contains(e.code)))  return;

        //2 - minimal timeout before searches
        var now = new Date().getTime()/1000,
            delta = 0.5 - (now - this.last_keypress);
        this.search.last_keypress = now;

        Elements.from($('template-search-placeholder').innerHTML.cleanup()).inject(this.search.list.empty());
        if (delta < 0) {
            clearTimeout(this.search.search_event);
            this.goSearch(e);
        } else {
            clearTimeout(this.search.search_event);
            this.search.search_event = (function () {
                this.goSearch(e);
            }).delay(delta*1000, this);
        }
    },

     /*
    handler: goSearch()
        performs the search
    */
    goSearch: function(e){
        var keyword = this.search.input.value,
            self = this;
        //do not search for empty strings, strings < 2 chars & same keywords 
        if (!keyword.isEmpty() && keyword.length > 1 && this.search.keyword != keyword){
            if (!this.search.request)
                this.search.request = new Request.JSON({
                    url: '/AJAX/group/search',
                    link: 'cancel',
                    onSuccess: this.onSearch.bind(this)
                });
            this.search.request.post({search: keyword, type: this.search.type});
        } else if (keyword.length <= 1)
            this.search.list.empty();
        this.search.keyword = keyword;
    } 
});

/*
module: _search
    controls the global searchbox
*/
var _search = _tap.register({
    mixins: 'searching',

    init: function(){
        this.initSearch($('group-search'), $('search-results'), 'withUsers');
    },

    onSearch: function(resp) {
        Elements.from(_template.parse('search', resp)).inject(this.search.list.empty());
        
        this.search.list.getElement('.create').addEvent('click', (function(e){
            e.stop();
            this.publish('modal.show.group.create', []);
        }).bind(this));
    }
});
