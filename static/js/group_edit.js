/*
 * All stuff related to group editing (including group pic)
*/

_edit.group = _tap.register({

	init: function() {
       var form   = this.form   = $('edit'),
           fields = this.fields = {},
           inputs = form.getElements('input:not([type="submit"])');

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
        alert(passed);
        e.stop();
    }

});

