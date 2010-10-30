<?php
/* CALLS:
    group_edit.js
*/

class ajax_moderate extends Base {
    protected $view_output = 'JSON';

    function __invoke() {
        $id = intval($_POST['id']);
        $users = $_POST['users'];
        $action = $_POST['action'];
        $group = Group::byId($id);

        if ($group->id === null ||
            !$group->isPermitted($this->user)) {

            $this->data = array('success' => 0);
            return;
        }

        $this->data = $this->moderate($group, $users, $action);
    }

    private function moderate(Group $group, $users, $action) {
        
        $group->moderateMembers($users, ($action == 'approve') ? true : false);
        
        return array('success' => 1);
    }
};
