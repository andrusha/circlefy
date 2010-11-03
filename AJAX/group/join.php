<?php
/* CALLS:
    modal.js

    This is the join method to the auth:email based groups
*/

class ajax_join extends Base {
    protected $view_output = 'JSON';

    public function __invoke() {
        $id = !is_array($_POST['gid']) ? intval($_POST['gid']) : $_POST['gid'];
        $email = $_POST['email'];
        
        if (!empty($email) && 
            filter_var($email, FILTER_VALIDATE_EMAIL) !== false) 
        {

            $group = Group::byId($id);
            $gdata = $group->asArray(false);
            if (Group::$auths['email'] == $gdata['auth']) {
                $ar = preg_split('/@/', $this->user->email);
                $domain = $ar[1];

                if ($domain != $gdata['auth_email']) {
                    $this->data = array('success' => 0, 'reason' => "Email domain doesn't match with the circle email.");
                    return;
                }
                $res = $group->sendActivation($this->user, $email);
                $this->data = array('success' => $res ? 1 : 0);
            }
        } else
            $this->data = array('success' => 0, 'reason' => "Invalid email.");
    }
};
