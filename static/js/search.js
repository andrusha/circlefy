_tap.mixin({
    name: 'searching',

    initSearch: function(search, suggest, byUser) {
        this.search = search; 
        this.suggest = suggest;
        this.list = this.suggest.getElement('ul');
        this.keyword = null;
        this.last_keypress = new Date().getTime()/1000;
        this.byUser = byUser;

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
    handler: goSearch()
        performs the search
    */
    goSearch: function(e){
        var keyword = this.search.value,
            self = this;
        //do not search for empty strings, strings < 2 chars & same keywords 
        if (!keyword.isEmpty() && keyword.length > 1 && this.keyword != keyword){
            if (!this.request)
                this.request = new Request.JSON({
                    url: '/AJAX/group/search',
                    link: 'cancel',
                    onSuccess: (function (resp) {
                        this.onSearch(resp);
                    }).bind(this)
                });
            this.request.post({search: keyword, byUser: this.byUser});
        } else if (keyword.length <= 1)
            this.list.empty();
        this.keyword = keyword;
    } 
});

/*
module: _search
    controls the global searchbox
*/
var _search = _tap.register({
    mixins: 'searching',

    init: function(){
        this.initSearch($('group-search'), $('search-results'), 0);
    },

    onSearch: function(resp) {
        Elements.from(_template.parse('search', resp)).inject(this.list.empty());
        
        this.list.getElement('.create').addEvent('click', function(e){
            e.stop();
            this.publish('modal.show.group.create', []);
        });
    }
});
