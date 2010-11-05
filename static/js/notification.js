var _notification = _tap.register({
    init: function() {
        var parent = this.parent = $('notifications');
        if (!parent)
            return;

        this.page = 0;

        parent.addEvents({
            'click:relay(a.toggle)': this.toggle.toHandler(this),
            'click:relay(button.close)': this.close.toHandler(this),
            'click:relay(button.paginate)': this.paginate.toHandler(this),
        });
    },

    toggle: function(el, e) {
        e.stop();
        el.getSiblings('div.list').toggleClass('hidden');
        el.toggleClass('toggled');
    },

    close: function(el, e) {
        e.stop();
        new Request.JSON({
            url: '/AJAX/taps/events',
            onSuccess: function (response) {
                if (response.success)
                    this.parent.addClass('hidden');
            }.bind(this)
        }).post({action: 'all_read'});
    },

    paginate: function(el, e) {
        e.stop();
        var type = el.getData('type');

        if (type == 'next') {
            this.page++;
            this.parent.getElements('button[data-type="prev"]').removeProperty('disabled');
        } else if (this.page > 0) {
            this.page--;
            this.parent.getElements('button[data-type="next"]').removeProperty('disabled');
        }

        if (this.page == 0)
            this.parent.getElements('button[data-type="prev"]').setProperty('disabled', true);

        new Request.JSON({
            url: '/AJAX/taps/events',
            onSuccess: function (response) {
                if (!response.success)
                    return;

                var items = Elements.from(_template.parse('notifications', response.events)),
                    list  = this.parent.getElement('div.events');

                list.empty();
                items.inject(list);
                
                if (items.length < 5)
                    this.parent.getElements('button[data-type="next"]').setProperty('disabled', true);
            }.bind(this)
        }).post({action: 'fetch', page: this.page});
    },
});
