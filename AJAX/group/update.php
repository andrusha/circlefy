<?php
/* CALLS:
    group_edit.js
*/

class ajax_update extends Base {
    protected $view_output = 'JSON';

    function __invoke() {
        $id = intval($_POST['id']);
        $group = Group::byId($id);

        if ($group->id === null ||
            !$group->isPermitted($this->user)) {

            $this->data = array('success' => 0);
            return;
        }

        if (!empty($_FILES))
            $this->data = $this->avatar($group);
        else
            $this->data = $this->update($group);
    }

    private function update(Group $group) {
       $group->symbol = $_POST['symbol'];
       $group->name   = $_POST['title'];
       $group->descr  = $_POST['descr'];
       $group->type   = intval($_POST['type']);
       $group->auth   = intval($_POST['auth']);
       $group->secret = $_POST['secret'] == 'true' ? 1 : 0;
       $group->update();

       return array('success' => 1, 'group' => $group->asArray());
    }

    private function avatar(Group $group) {
        if ($_FILES['Filedata']['size'] > 2 * 1024 * 1024 ||
            !getimagesize($_FILES['Filedata']['tmp_name']))
            return array('success' => 0);

        $uploaded = GROUP_PIC_PATH . $group->id . '.jpg'; 
        move_uploaded_file($_FILES['Filedata']['tmp_name'], $uploaded);
        $pictures = Images::makeUserpics($group->id, $uploaded, GROUP_PIC_PATH); 

        return array('success' => 1, 'pic' => GROUP_PIC_REL.'large_'.$group->id.'.jpg');
    }
};
