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
        $group = Group::create($this->user,
                               null,
                               $_POST['title'],
                               $_POST['symbol'],
                               $_POST['descr'],
                               null,                // TODO: tags
                               intval($_POST['type']),
                               intval($_POST['auth']),
                               'public',            // TODO: public/private
                               ($_POST['secret'] == 'true' ? 1 : 0)
        );

        return array('success' => 1, 'group' => $group->asArray(false));
    }
};
