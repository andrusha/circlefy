/*
 * There is should be placed all global validators
 * so we may reuse them
 */

Form.Validator.implement('options', {
    onElementPass: function (elem) {
        elem.removeClass('failed');
        elem.addClass('valid');
        elem.fireEvent('hideTip');
    },

    onElementFail: function (elem, validators) {
        var text = '';
        validators.each(function (val) {
            text += '<p>' + Form.Validator.validators[val].getError(elem) + '</p>';
        });
        elem.setData('tiptext', text);
        elem.removeClass('valid');
        elem.addClass('failed');
        elem.fireEvent('showCustomTip', [{content: text}]);
    }
});

Form.Validator.add('groupDoesNotExists', {
    errorMsg: function (elem) {
        return '<a href="/circle/'+elem.value+'">'+elem.value+'</a> circle already exists';
    },
    test: function (elem, props) {
        var symbol = elem.value,
            status = false;

        if (symbol == props.oldSymbol)
            return true;

        new Request({
            url:  '/AJAX/group/exists',
            data: {symbol: symbol},
            link: 'cancel',
            async: false,
            onSuccess: function() {
                try {
                    var resp = JSON.decode(this.response.text);
                    status = !resp.exists;
                } catch (err) {
                    status = false;
                }
            }
        }).send();

        return status;
    }
});
