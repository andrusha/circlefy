/*
    post modal
*/

var _post = _tap.register({
    mixins: 'searching',

    init: function(){
        this.initSearch($('group-search-post'), $('post-search-results'), 'yourGroups');

        this.form = $('posttapform');
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

    onSearch: function(resp) {
        Elements.from(_template.parse('post-search', resp.groups)).inject(this.search.list.empty());
        
        this.search.list.getElements('li').each(function(el) {
            el.addEvent('click', (function(e) {
                e.stop();
                var group = el.get('rel'),
                    gname = el.getData('name'),
                    title = el.getElement('.title').get('text'),
                    clear = $('group-search-clear');
                
                this.search.input.setData('gid', group);
                this.search.input.setData('name', gname);
                this.search.input.value = title;
                this.search.input.addClass('selected');
                this.search.suggest.addClass('hidden');
                clear.removeClass('hidden');
                
                clear.addEvent('click', (function(e) {
                    e.stop();
                    this.search.input.setData('gid', '');
                    this.search.input.setData('name', '');
                    this.search.input.value = '';
                    this.search.input.removeClass('selected');
                    this.search.suggest.addClass('hidden');
                    this.search.suggest.getElement('ul').innerHTML = '';
                    clear.addClass('hidden');
                }).bind(this));
            }).bind(this));
        }, this);
    },

    submitForm: function(passed, form, e) {
        e.stop();
        if (!passed)
            return;
        
        var gid  = this.search.input.getData('gid'),
            pmsg = $('postmessage'),
            msg  = pmsg.value,
            self = this;
        
        var data = {id: gid,
                    type: 'group',
                    msg: msg};
        
        if (pmsg.getData('mediatype') && pmsg.getData('mediatype').length) {
            var media = {
                'mediatype': pmsg.getData('mediatype'),
                'link': pmsg.getData('link'),
                'code': pmsg.getData('embed'),
                'title': pmsg.getData('title'),
                'description': pmsg.getData('description'),
                'thumbnail_url': pmsg.getData('thumbnail')
            }
            if (pmsg.getData('fullimage'))
                media.fullimage_url = pmsg.getData('fullimage');
            data.media = media;
        }
        new Request({
            url: '/AJAX/taps/new',
            data: data,
            onSuccess: function(){
                var response = JSON.decode(this.response.text);
                if (response.success) {
                    self.search.input.value = '';
                    self.search.input.setData('gid', '');
                    self.search.input.setData('name', '');
                    $('postmessage').value = '';
                    self.publish('modal.hide');
                    _notifications.alert('Success', "You've posted! Any replies you will receive notifications for", {color: 'darkgreen', duration: 2000});
                }
            }
            
        }).send();
        
    }
});
