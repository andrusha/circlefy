<?php
/* CALLS:
    group_create.js
*/

class ajax_create extends Base {
    protected $view_output = 'JSON';

    function __invoke() {
        $this->data = $this->create();
    }

    private function create() {
        $domain = null;
        if (Group::$auths['email'] == $_POST['auth']) {
            if (!empty($_POST['auth-email']) && 
                filter_var($_POST['auth-email'], FILTER_VALIDATE_EMAIL) !== false) 
            {
                $ar = preg_split('/@/', $_POST['auth-email']);
                $domain = $ar[1];
            } else 
                return array('success' => 0, 'reason' => 'Email not valid');
        }
        
        $group = Group::create($this->user,
                               null,
                               $_POST['title'],
                               $_POST['symbol'],
                               $_POST['descr'],
                               null,                // TODO: tags
                               intval($_POST['type']),
                               intval($_POST['auth']),
                               'public',            // TODO: public/private
                               ($_POST['secret'] == 'true' ? 1 : 0),
                               $domain
        );
        
        // create symlinks to default images
        $small_img  = GROUP_PIC_PATH . '/small_'. $group->id .'.jpg';
        $medium_img = GROUP_PIC_PATH . '/medium_'. $group->id .'.jpg';
        $large_img  = GROUP_PIC_PATH . '/large_'. $group->id .'.jpg';
        @unlink($small_img);
        @unlink($medium_img);
        @unlink($large_img);
        symlink(GROUP_PIC_PATH.'/small_group.png', $small_img);
        symlink(GROUP_PIC_PATH.'/medium_group.png', $medium_img);
        symlink(GROUP_PIC_PATH.'/large_group.png', $large_img);
        
        return array('success' => 1, 'group' => $group->asArray(false));
    }
};
