/*
 * script: infobox.js
 *
 * Controls infobox - a box with info
 * about channel
 */

/*
 * module: _infobox
 *  controls infobox, right above tap info about channels
*/
var _infobox = _tap.register({

    init: function() {
        var box = this.box = $('infobox');
        var button = this.button = box.getElement('button');
        if (button && box.hasClass('pubgroup')) {
            button.addEvent('click', this.groupAction.toHandler(this));
        }
    },

    groupAction: function(el, e) {
        if (el.hasClass('login')) return window.location = '/';
        if (el.hasClass('join')) return this.joinGroup();
        if (el.hasClass('request')) return this.requestJoinGroup();
        if (el.hasClass('leave')) return this.leaveGroup();
        if (el.hasClass('track')) return this.track(true);
        if (el.hasClass('untrack')) return this.track(false);
    },

    requestJoinGroup: function() {
        var self = this,
                id = this.button.getData('id');
        new Request({
            url: '/AJAX/request_join_group.php',
            method: 'post',
            data: {gid: id},
            onSuccess: function() {
                var response = JSON.decode(this.response.text);
                if (!response.good) return;
                self.button.set({
                    'text': 'waiting',
                    'class': 'leave'
                });
                $$('.count-one').each(function(el) {
                    el.set('html', el.innerHTML.toInt() + 1).fade('hide').fade();
                });
                $$('.waiting-click')[0].fade('hide');
                $$('.waiting-click')[0].fade(1).fade.delay(2000, $$('.waiting-click')[0], 0);


            }
        }).send();
    },

    joinGroup: function() {
        var self = this,
                id = this.button.getData('id');
        new Request({
            url: '/AJAX/join_group.php',
            method: 'post',
            data: {gid: id},
            onSuccess: function() {
                var response = JSON.decode(this.response.text);
                if (!response.good) return;
                self.button.set({
                    'text': 'leave channel',
                    'class': 'leave'
                });
                $$('.count-one').each(function(el) {
                    el.set('html', el.innerHTML.toInt() + 1).fade('hide').fade();
                });
                $$('.positive-click')[0].fade('hide');
                $$('.positive-click')[0].fade(1).fade.delay(2000, $$('.positive-click')[0], 0);


            }
        }).send();
    },

    leaveGroup: function() {
        var self = this,
                id = this.button.getData('id');
        new Request({
            url: '/AJAX/leave_group.php',
            data: {gid: id},
            onSuccess: function() {
                self.button.set({
                    'text': 'follow channel',
                    'class': 'join'
                });
                $$('.count-one').each(function(el) {
                    el.set('html', el.innerHTML.toInt() - 1).fade('hide').fade();
                });
                $$('.negative-click')[0].fade('hide');
                $$('.negative-click')[0].fade(1).fade.delay(2000, $$('.negative-click')[0], 0);
            }
        }).send();
    },

    track: function(type) {
        var self = this,
                id = this.button.getData('id');
        new Request({
            url: '/AJAX/track.php',
            data: {
                fid: id,
                state: (type) ? 1 : 0
            },
            onSuccess: function() {
                var response = JSON.decode(this.response.text);
                if (response.success) {
                    self.button.set({
                        'text': (type) ? 'unfollow' : 'follow',
                        'class': (type) ? 'untrack' : 'track'
                    });
                    $$('.count-one').each(function(el) {

                        if (type) {
                            $$('.count-one').each(function(el) {
                                el.set('html', el.innerHTML.toInt() + 1).fade('hide').fade();
                            });
                            $$('.positive-click')[0].fade('hide');
                            $$('.positive-click')[0].fade(1).fade.delay(2000, $$('.positive-click')[0], 0);
                        } else {
                            $$('.count-one').each(function(el) {
                                el.set('html', el.innerHTML.toInt() - 1).fade('hide').fade();
                            });
                            $$('.negative-click')[0].fade('hide');
                            $$('.negative-click')[0].fade(1).fade.delay(2000, $$('.negative-click')[0], 0);
                        }
                    });
                }
            }
        }).send();
    }

});

