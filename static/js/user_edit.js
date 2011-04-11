/*global _edit, _tap, _vars, Form, CirTooltip, Swiff, console, Request, window*/

/*
 * All stuff related to user profile and settings edit 
*/

_edit.user = _tap.register({

	init: function () {
        if (!_vars.user.id) {
            return;
        }

        var form   = this.form   = $('user-edit'),
            fields = this.fields = {},
            inputs = form.getElements('input:not([type="submit"])'),
            avatar = $('user-avatar-changer');

        if (!avatar) {
            return;
        }

        new CirTooltip({
            hovered:  inputs.combine(avatar),
            template: 'error-tooltip',
            position: 'centerTop',
            sticky:   true
        });

        inputs.each(function (el) {
            fields[el.name] = el;
        });

        form.validator = new Form.Validator(form, {
            onFormValidate: this.update.bind(this)
        });

        var avparent = avatar.getParent('div');
        avparent.set('spinner', {message: 'uploading...', maskMargins: true});

        new Swiff.Uploader({
            path: '/static/Swiff.Uploader.swf',
            url: '/AJAX/user/update',
            data: {'id': _vars.user.id},
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
            onSelectSuccess: function (files) {
                avparent.spin();
            },
            onSelectFail: function (files) {
                avatar.fireEvent('showCustomTip', [{content: 'Please select image smaller than 2 Mb'}]);
            },
            onFileComplete: function (file) {
                var resp = JSON.decode(file.response.text);
                console.log(resp); 
                if (resp.success) {
                    //a bit dirty, but there is no good solution for chrome
                    var newLarge = avatar.clone(true, true);
                    newLarge.src = resp.medium + '?' + Math.random();
                    newLarge.replaces(avatar);
                    avatar = newLarge;
                }
            }.bind(this),
            onComplete: function () {
                avparent.unspin();
            }
        });

        this.subscribe('modal.hide', function () {
            Object.each(fields, function (el) {
                el.fireEvent('hideTip');
            });
        });
    },

    update: function (passed, el, e) {
        e.stop();
        var data = Object.map(this.fields, function (elem, name) {
            if (elem.type == 'checkbox') {
                return elem.checked;
            }

            return elem.value;
        });

        new Request.JSON({
            url: '/AJAX/user/update',
            onSuccess: function (response) {
                if (!response.success) {
                    return;
                }

                window.location.reload();
            }.bind(this)
        }).post(data);
    },

    /*
     * Lazy info updater, works without page reload
     */
    updateInfo: function (group) {
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

