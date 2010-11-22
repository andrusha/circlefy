/*
    post modal
*/

var _post = _tap.register({
    mixins: 'searching',

    init: function(){
        this.initSearch($('group-search-post'), $('post-search-results'), 1);

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
        Elements.from(_template.parse('post-search', resp.groups)).inject(this.list.empty());
        
        this.list.getElements('li').each(function(el) {
            el.addEvent('click', (function(e) {
                e.stop();
                var group = el.get('rel'),
                    gname = el.getData('name'),
                    title = el.getElement('.title').get('text');
                
                this.search.setData('gid', group);
                this.search.setData('name', gname);
                this.search.value = title;
                this.suggest.addClass('hidden');
            }).bind(this));
        });
    },

    submitForm: function(passed, form, e) {
        e.stop();
        if (!passed)
            return;
        
        var gid  = this.search.getData('gid'),
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
