/*
 * All stuff related to group editing (including group pic)
*/

_edit.group = _tap.register({

	init: function() {
        var form   = this.form   = $('edit'),
           fields = this.fields = {},
           inputs = form.getElements('input:not([type="submit"]), textarea, select');

        new CirTooltip({
            hovered:  inputs.combine($$('avatar-changer')),
            template: 'error-tooltip',
            position: 'top',
            align:    'right',
            sticky:   true
        });

        inputs.each( function (el) {
           fields[el.name] = el;
        });

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
                elem.fireEvent('showCustomTip', [{content: 'Please select image smaller than 2 Mb'}]);
            },
            onFileComplete: function(file) {
                var resp = JSON.decode(file.response.text);
                console.log(resp); 
                if (resp.success)
                    avatar.src = resp.pic+'?'+Math.random();
            },
            onComplete: function() {
                avparent.unspin();
            }
        });
    },

    update: function(passed, el, e) {
        e.stop();
        var data = Object.map(this.fields, function(elem, name) {
            if (elem.type == 'checkbox')
                return elem.checked;
            return elem.value;
        });

        new Request.JSON({
            url: '/AJAX/group/update',
            onSuccess: function (response) {
                if (response.success)
                    document.location = '/circle/'+response.group.symbol+'?edit';
            }
        }).post(data);
    }

});

