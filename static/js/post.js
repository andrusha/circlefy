/*
    post modal
    based on search.js, please refer to it for inline docs
*/

var _post = _tap.register({
    init: function(){
        var search = this.search = $('group-search-post');

        this.suggest = $('post-search-results');
        this.form = $('posttapform');
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
        this.form.validator = new Form.Validator(this.form, {
            fieldSelectors: 'textarea, input',
            onFormValidate: this.submitForm.bind(this),
            evaluateFieldsOnBlur: false,
            evaluateFieldsOnChange: false
        });
        new CirTooltip({
            hovered:  $$('#postmessage, #group-search-post'),
            template: 'error-tooltip',
            position: 'centerTop',
            sticky:   true
        });
    },
    start: function(el) {
        this.suggest.removeClass('hidden');
        el.fireEvent('hideTip');
    },
    end: function(el){
       (function(){
           this.suggest.addClass('hidden');
       }).bind(this).delay(500);
    },
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
                        Elements.from(_template.parse('post-search', resp.groups)).inject(self.list.empty());
                        
                        self.list.getElements('li').each(function(el) {
                            el.addEvent('click', function(e) {
                                e.stop();
                                var group = el.get('rel'),
                                    gname = el.getData('name'),
                                    title = el.getElement('.title').get('text');
                                
                                self.search.setData('gid', group);
                                self.search.setData('name', gname);
                                self.search.value = title;
                                self.suggest.addClass('hidden');
                            });
                        });
                    }
                });
            this.request.send({data: {search: keyword}});
        } else if (keyword.length <= 1)
            this.list.empty();
        this.keyword = keyword;
    },
    submitForm: function(passed, form, e) {
        e.stop();
        if (!passed)
            return;
        
        var gid  = this.search.getData('gid'),
            msg  = $('postmessage').value,
            self = this;
        
        new Request({
            url: '/AJAX/taps/new',
            data: {
                id: gid,
                type:  'group',
                msg:  msg
            },
            onSuccess: function(){
                var response = JSON.decode(this.response.text);
                if (response.success) {
                    self.search.value = '';
                    self.search.setData('gid', '');
                    self.search.setData('name', '');
                    $('postmessage').value = '';
                     self.publish('modal.hide');
                }
            }
            
        }).send();
        
    }
});