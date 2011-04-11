<?php
/* CALLS:
    user_edit.js
*/

class ajax_update extends Base {
    protected $view_output = 'JSON';

    function __invoke() {
        $id = intval($_POST['id']);

        if ($id !== $this->user->id) {
            $this->data = array('success' => 0);
            return;
        }

        if (!empty($_FILES))
            $this->data = $this->avatar();
        else
            $this->data = $this->update();
    }

    private function update() {
       $this->user->fname = $_POST['fname'];
       $this->user->lname = $_POST['lname'];
       $this->user->email = $_POST['email'];
       $this->user->update();

       return array('success' => 1, 'user' => $this->user->asArray());
    }

    private function avatar() {
        if ($_FILES['Filedata']['size'] > 2 * 1024 * 1024 ||
            !getimagesize($_FILES['Filedata']['tmp_name']))
            return array('success' => 0);

        $uploaded = USER_PIC_PATH . $this->user->id . '.jpg'; 
        move_uploaded_file($_FILES['Filedata']['tmp_name'], $uploaded);
        $pictures = Images::makeUserpics($this->user->id, $uploaded, USER_PIC_PATH); 

        return array('success' => 1, 
                     'large'  => USER_PIC_REL.'large_'.$this->user->id.'.jpg',
                     'medium' => USER_PIC_REL.'medium_'.$this->user->id.'.jpg',
                     'small'  => USER_PIC_REL.'small_'.$this->user->id.'.jpg');
    }
};
