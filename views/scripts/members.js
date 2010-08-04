/*
 * script: members.js
 * Controls member panel on the right
 */

/*
 * module: _members
 *  Controls user list, user count
 *  and all related to it info in right panel
*/
var _members = _tap.register({

    init: function() {
        this.toggle = $('list-action');
        this.toggle2 = $('total-member-count');
        if (!this.toggle) return;
        this.list = $('member-panel');
        this.topList = this.list.getElements('li.panel-item');
        this.toggle.addEvent('click', this.toggleList.toHandler(this));
        this.toggle2.addEvent('click', this.toggleList.toHandler(this));
    },

    toggleList: function(el, e) {
        $('member-panel').empty();
        var el = $('list-action');
        if (el.hasClass('all')) this.showAll();
        else this.showTop();
    },


    /* showAll
     This gets the list of members
     depending on the type: 0 = list all , 1 = serach
     */
    showAll: function() {

        var self = this,
                list = this.allList;
        if (list) return this.changeList('all', list);

        var online_state = $('online-checked').checked;

        new Request({
            url: "/AJAX/group_userlist.php",
            data: {gid: _vars.filter.gid,type:0,online_only:online_state},
            onSuccess: function() {
                var response = JSON.decode(this.response.text);
                if (response.grouplist && $type(response.grouplist) == 'array') {
                    list = Elements.from(_template.parse('list.member', response.grouplist));
                } else {
                    list = [];
                }
                self.changeList('all', list);
            }
        }).send();
    },

    showTop: function() {
        this.changeList('top', this.topList);
    },

    changeList: function(type, list) {
        this.toggle.set({
            'text': type == 'all' ? 'top members' : 'all members'
        }).removeClass(type).addClass(type == 'all' ? 'top' : 'all');
        this.toggle.getNext('span').set({
            'text': type == 'all' ? 'all members' : 'top members'
        });
        this.list.getElements('li.panel-item').dispose();
        if (list.length > 0) {
            list.inject(this.list);
        } else {
            new Element('li', {
                'class': 'notify',
                'text': type == 'all' ? 'no members' : 'no top members'
            }).inject(this.list);
        }
    }

});

