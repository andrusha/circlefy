<?php
/* CALLS:
	join_group.js
    modal.js
*/

class ajax_join extends Base {
    protected $view_output = 'JSON';

    public function __invoke() {
        $action = isset($_POST['action']) ? $_POST['action'] : 'join';

        switch($action) {
            case 'join':
                $gid = intval($_POST['gid']);
                $this->data = $this->join_group($gid);
                break;

            case 'bulk':
                $gids = $_POST['gids'];
                $this->data = $this->bulk_join($gids);
                break;
        }
    }
    
    private function bulk_join(array $gids) {
        GroupsList::fromIds(array_map('intval', $gids))->bulkJoin($this->user);
        return array('good' => true);
    }

    private function join_group($gid) {
        $group = new Group($gid);
        $status = $group->join($this->user);

        return array('good' => $status);
    }

}
