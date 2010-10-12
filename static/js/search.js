/*
module: _search
    controls the global searchbox
*/
var _search = _tap.register({

    init: function(){
        var search = this.search = $('group-search');
        this.placeholder = 'Start or join a conversation';

        this.suggest = $('search-results');
        this.list = this.suggest.getElement('ul');
        this.selected = null;
        this.keyword = null;
        this.new_keyword = null;
        this.last_keypress = new Date();

        search.addEvents({
            'focus': this.start.toHandler(this),
            'blur': this.end.toHandler(this),
            'keyup': this.checkKeys.bind(this)
        });
        this.list.addEvents({
            'click:relay(li)': function () {
                this.publish('modal.show.group.create', []);
            }.bind(this)
        });
        search.value = this.placeholder;
        //search.focus();
    },

    /*
    handler: start()
        fired when the search input is focused
    */
    start: function() {
        if (this.search.value == this.placeholder)
            this.search.value = '';
        this.suggest.removeClass('hidden');
    },

    /*
    handler: end()
        fired when the search input is blurred
    */
    end: function(el){
        this.selected = null;
        (function(){
            this.suggest.addClass('hidden');
            if (this.search.value == '')
                this.search.value = this.placeholder;
        }).bind(this).delay(300);
    },

    /*
    handler: checkKeys()
        checks whether the search keys are valid or when enter is pressed
    */
    checkKeys: function(e){
        //var updown = ({'down': 1, 'up': -1})[e.key];
        //if (updown) return this.navigate(updown);
        if (e.key == 'enter') return this.goSearch(e);
        if (({'left': 37,'right': 39,'esc': 27,'tab': 9})[e.key]) return;
        this.search.value = new Date.diff(this.last_keypress, 'ms');
        //this.goSearch(e);
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
        var keyword = this.keyword = this.search.value;
        if (!keyword.isEmpty() && keyword.length){
            if (!this.request)
                this.request = new Request({
                    url: '/AJAX/group/search',
                    link: 'cancel',
                    onSuccess: this.parseResponse.bind(this)
                });
            this.request.send({data: {search: keyword}});
        } else {
            this.list.empty();
            new Element('li', {'text': 'searching..', 'class': 'notice'}).inject(this.list);
        }
    },

    /*
    method: parseResponse()
        parses the response from the server and inject it in the suggestion box
    */
    parseResponse: function(txt){
        var resp = JSON.decode(txt),
            list = this.list.empty();
        this.selected = null;
        var exact_match = false;
        var keyword = this.keyword;
        if (!resp){
            this.list.empty();
            var no_res_el = new Element('li', {'text': 'hmm, nothing found..', 'class': 'notice'}).inject(this.list);
            this.new_keyword = this.keyword.replace(/ /g,'-');
            var data = [
                {
                    id: 0,
                    symbol: 'search',
                    name: 'Create New Conversation Channel <span style="color:blue;">'+this.new_keyword+'</span>',
                    online: '',
                    total: '',
                    img: false,
                    desc: 'Click here to great this channel on the fly!',
                    joined: 'no'
                }
                  ];
            $$(Elements.from(_template.parse('suggest.group', data)).slice(0, 6)).inject(list);
            return this;
        }
        var data = resp.map(function(item){
            var els = Elements.from(item[3]),
                info = item[1].split(':');
            var a = info[4],b = info[5];
            info.shift();
    
            if(item[0].rtrim(' ').toLowerCase() == keyword.rtrim(' ').toLowerCase() || exact_match != false ){
                exact_match = true;
            }

            return {
                id: info.pop(),
                symbol: info.shift(),
                name: item[0],
                online: a,
                total: b,
                img: els.filter('img').pop().get('src'),
                desc: els.filter('span').pop().get('text'),
                joined: item[4]
            };
        });

        

        this.new_keyword = this.keyword.replace(/ /g,'-');
        if(!exact_match){
            data.push(
                {
                    id: 0,
                    symbol: 'search',
                    name: 'Create New Conversation Channel <span style="color:blue;">'+this.new_keyword+'</span>',
                    online: '',
                    total: '',
                    img: false,
                    desc: 'Click here to great this channel on the fly!',
                    joined: 'no'
                }
            );
        }
        
        $$(Elements.from(_template.parse('suggest.group', data)).slice(0, 6)).inject(list);
        this.suggest.addClass('on');
    }

});
