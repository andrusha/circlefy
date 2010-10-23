/*
 * All stuff related to group editing (including group pic)
*/

_edit.group = _tap.register({

	init: function() {
       var form   = this.form   = $('edit'),
           fields = this.fields = {},
           inputs = form.getElements('input:not([type="submit"]), textarea, select');

        new CirTooltip({
            hovered:  inputs,
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
    },

    update: function(passed, el, e) {
        e.stop();
        var data = Object.map(this.fields, function(elem, name) {
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

