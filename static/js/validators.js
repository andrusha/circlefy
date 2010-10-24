/*
 * There is should be placed all global validators
 * so we may reuse them
 */

Form.Validator.implement('options', {
    onElementPass: function (elem) {
        elem.fireEvent('hideTip');
    },

    onElementFail: function (elem, validators) {
        var text = '';
        validators.each(function (val) {
            val = val.split(':')[0];
            text += '<p>' + Form.Validator.validators[val].getError(elem) + '</p>';
        });
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
                    status = !JSON.decode(this.response.text).exists;
                } catch (err) {
                    status = false;
                }
            }
        }).send();

        return status;
    }
});

Form.Validator.add('userDoesNotExists', {
    errorMsg: function (elem) {
        return 'This username already taken';
    },
    test: function (elem, props) {
        var name = elem.value,
            status = false;

        new Request({
            url:  '/AJAX/user/check',
            data: {
                type: 'uname',
                val:   name
            },
            link: 'cancel',
            async: false,
            onSuccess: function() {
                try {
                    status = JSON.decode(this.response.text).available;
                } catch (err) {
                    status = false;
                }
            }
        }).send();

        return status;
    }
});

Form.Validator.add('emailDoesNotExists', {
    errorMsg: function (elem) {
        return 'User with this email already registred';
    },
    test: function (elem, props) {
        var email  = elem.value,
            status = false;

        new Request({
            url:  '/AJAX/user/check',
            data: {
                type: 'email',
                val:   email
            },
            link: 'cancel',
            async: false,
            onSuccess: function() {
                try {
                    status = JSON.decode(this.response.text).available;
                } catch (err) {
                    status = false;
                }
            }
        }).send();

        return status;
    }
});

Form.Validator.add('validate-facebook', {
    errorMsg: function (elem) {
        switch(elem.get('errorType')) {
            case 'no_fb':
                return 'You must login into facebook before proceed';
                break;
            case 'exists':
                return 'User with this facebook account already exists';
                break;
            default:
                return 'something went wrong durning account checking';
        }
    },

    test: function (elem, props) {
        var status = false;
        new Request({
            url:  '/AJAX/user/facebook',
            data: {action: 'check'},
            link: 'cancel',
            async: false,
            onSuccess: function() {
                try {
                    var resp = JSON.decode(this.response.text);
                    status = resp.success;
                    if (resp.reason)
                        elem.set('errorType', resp.reason);
                } catch (err) {
                    status = false;
                }
            }
        }).send();

        return status;
    }
});
