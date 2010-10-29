/*
 * All stuff related to group editing (including group pic)
*/

_edit.group = _tap.register({

	init: function() {
        var form   = this.form   = $('group-edit'),
           fields = this.fields = {},
           inputs = form.getElements('input:not([type="submit"]), textarea, select');

        this.sidebar = $$('div.box.circle-title')[0];

        new CirTooltip({
            hovered:  inputs.combine($$('avatar-changer')),
            template: 'error-tooltip',
            position: 'centerTop',
            sticky:   true
        });

        inputs.each( function (el) {
           fields[el.name] = el;
        });

        this.oldSymbol = fields.symbol.value;

        fields.title.addEvent('keyup', function () {
            fields.symbol.value = fields.title.value.makeSymbol();
        });

        form.validator = new Form.Validator(form, {
            ignoreDisabled: false,
            onFormValidate: this.update.bind(this)
        });

        var avatar = $('avatar-changer');
        if (!avatar) return;
        var avparent = avatar.getParent('div');

        avparent.set('spinner', {message: 'uploading...', maskMargins: true});

        new Swiff.Uploader({
            path: '/static/Swiff.Uploader.swf',
            url: '/AJAX/group/update',
            data: {'id': $('edit-id').value},
            target: avatar,
            queued: false,
            multiple: false,
            instantStart: true,
            appendCookieData: true,
            mergeData: true,
            fileSizeMax: 2 * 1024 * 1024,
            typeFilter: {
                'Images (*.jpg, *.jpeg, *.gif, *.png)': '*.jpg; *.jpeg; *.gif; *.png'
            },
            onSelectSuccess: function(files) {
                avparent.spin();
            },
            onSelectFail: function(files) {
                avatar.fireEvent('showCustomTip', [{content: 'Please select image smaller than 2 Mb'}]);
            },
            onFileComplete: function(file) {
                var resp = JSON.decode(file.response.text);
                console.log(resp); 
                if (resp.success) {
                    //a bit dirty, but there is no good solution for chrome
                    var newLarge = avatar.clone(true, true);
                    newLarge.src = resp.medium+'?'+Math.random();
                    newLarge.replaces(avatar);
                    avatar = newLarge;

                    var oldMed = this.sidebar.getElement('img.profile-thumb'),
                        newMed = oldMed.clone(true, true);
                    newMed.src = resp.medium+'?'+Math.random();
                    newMed.replaces(oldMed);
                }
            }.bind(this),
            onComplete: function() {
                avparent.unspin();
            }
        });

        this.subscribe('modal.hide', function () {
            Object.each(fields, function (el) {
                el.fireEvent('hideTip');
            });
        });
    },

    update: function(passed, el, e) {
        e.stop();
        var data = Object.map(this.fields, function(elem, name) {
            if (elem.type == 'checkbox')
                return elem.checked;
            return elem.get('value');
        });

        new Request.JSON({
            url: '/AJAX/group/update',
            onSuccess: function (response) {
                if (!response.success)
                    return;
                if (response.group.symbol != this.oldSymbol)
                    document.location = '/circle/'+response.group.symbol+'?edit';
                else
                    this.updateInfo(response.group);
            }.bind(this)
        }).post(data);
    },

    /*
     * Lazy info updater, works without page reload
     */
    updateInfo: function(group) {
        var f = this.fields,
            s = this.sidebar;
        //in some cases we may change title, but not symbol
        f.title.value = group.name;
        f.descr.value = group.descr;
        f.type.value = group.type;
        f.auth.value = group.auth;
        f.secret.checked = group.secret;

        s.getElement('p').innerHTML = group.descr;
        s.getElement('span.group-name').innerHTML = group.name;

        this.publish('modal.hide', []);
    }

});

