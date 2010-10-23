<?php
/* CALLS:
    group_edit.js
*/

class ajax_update extends Base {
    protected $view_output = 'JSON';

    function __invoke() {
       $id = intval($_POST['id']);

       $group = Group::byId($id);

       //TODO: add security checks
       $group->symbol = $_POST['symbol'];
       $group->name   = $_POST['title'];
       $group->descr  = $_POST['descr'];
       $group->type   = intval($_POST['type']);
       $group->auth   = intval($_POST['auth']);
       $group->secret = $_POST['secret'] == 'true' ? 1 : 0;
       $group->update();

       $this->set(1, 'success');
       $this->set($group->asArray(), 'group');
    }
};
