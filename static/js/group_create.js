/*
 * All stuff related to group creation (including group pic)
*/

_add.group = _tap.register({

	init: function() {
        var form   = this.form   = $('group-create'),
            fields = this.fields = {},
            inputs = form.getElements('input:not([type="submit"]), textarea, select');

        this.sidebar = $$('div.box.circle-title')[0];

        new CirTooltip({
            hovered:  inputs.combine($$('create-avatar-changer')),
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
            onFormValidate: this.create.bind(this)
        });
        
        /*
        var avatar = $('create-avatar-changer');
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
        */
        
        form.validator.ignoreField(fields['auth-email']);
        
        $('create-auth').addEvent('change', function(e){
            var auth = e.target.getSelected().get('text')[0];
            console.log(form.validator);
            if (auth == 'email') {
                $('auth-email-cont').removeClass('hidden');
                form.validator.enforceField(fields['auth-email']);
            } else {
                $('auth-email-cont').addClass('hidden');
                form.validator.ignoreField(fields['auth-email']);
            }
        });
        
        this.subscribe('modal.hide', function () {
            Object.each(fields, function (el) {
                el.fireEvent('hideTip');
            });
        });
    },

    create: function(passed, el, e) {
        e.stop();
        var data = Object.map(this.fields, function(elem, name) {
            if (elem.type == 'checkbox')
                return elem.checked;
            return elem.get('value');
        });

        new Request.JSON({
            url: '/AJAX/group/create',
            onSuccess: function (response) {
                if (!response.success)
                    return;
                document.location = '/circle/'+response.group.symbol+'?edit';
            }.bind(this)
        }).post(data);
    }
});