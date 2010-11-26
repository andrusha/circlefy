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

        switch ($action) {
            case 'approve':
                $group->moderateMembers($users, true);
                break;
            
            case 'reject':
                $group->moderateMembers($users, false);
                break;
            
            case 'ban':
                $group->moderateMembers($users, true, Group::$permissions['blocked']);
                break;
            
            case 'promote':
                $group->moderateMembers($users, true, Group::$permissions['moderator']);
                break;
            
            case 'demote':
                $group->moderateMembers($users, true, Group::$permissions['user']);
                break;
        }

        $this->set(true, 'success');
    }
};
