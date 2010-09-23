<?php
/* CALLS:
    homepage.phtml
*/
$usage = <<<EOF
PARAMS

type - 
    Types:
    1 = IND Channel
    11 = AGGR Channels
    2 = IND Personal 
    22 = AGGR Personal
    3 = IND Private
    33 = AGGR Private
    4 = AGGR Channels & Private
    5 = AGGR Convos
search - 
    optional if you provide a search term for the feed
outside - 
    Can be : 0,1,2 
more -
    the more offset if you want a different set of the feed rather then the latest ( i.e. pagiation )
id -
    the id of the channel you want to call
EOF;
    
require('../../config.php');
require('../../api.php');

class filter extends Base {
    public function __construct() {
        $this->need_db = 0;
        $this->view_output = 'JSON';
        parent::__construct();

        $type    = $_POST['type'];
        $search  = $_POST['search'];
        $outside = $_POST['outside'];
        $more    = $_POST['more'];
        $id      = $_POST['id'];
        $anon    = $_POST['anon'];

        $this->data = $this->filter($type,$search,$outside,$id,$more,$anon);
    }
    
    private function filter($type,$search,$outside,$id,$more,$anon) {
        $params = array();

        if ($more)
            $params['#start_from#'] = $more;

        if (!$outside)
            $outside = '1, 2';

        $params['#outside#'] = $outside;

        if (isset($anon))
            $params['#anon#'] = $anon;
        
        if ($search)
            $params['#search#'] = $search;

        $group_info = true;
        $user_info = false;
        switch ($type) {
            case 100:
                $filter = 'public';
                break;
            case  11:
                $filter = 'aggr_groups';
                $params['#uid#'] = $this->user->uid;
                break;
            case   1:
                $filter = 'ind_group';
                $params['#gid#'] = intval($id);
                Action::log($this->user, 'group', 'view', array('gid' => intval($id)));
                break;
            case  22:
                $filter = 'aggr_personal';
                $params['#uid#'] = $this->user->uid;
                break;
            case   2:
                $filter = 'personal';
                $params['#uid#'] = intval($id);
                Action::log($this->user, 'user', 'view', array('uid' => intval($id)));
                break;
            case  33:
                $group_info = false;
                $user_info = true;
                $filter = 'aggr_private';
                $params['#uid#'] = $this->user->uid;
                break;
            case   3:
                $group_info = false;
                $user_info = true;
                $filter = 'private';
                $params['#from#'] = $this->user->uid;
                $params['#to#'] = intval($id);
                break;
            case  4:
                $group_info = $user_info = true;
                $filter = 'aggr_all';
                $params['#uid#'] = $this->user->uid;
            case  5:
                $user_info = $group_info = true;
                $filter = 'convos_all';
                $params['#uid#'] = $this->user->uid;
        }

        $data = TapsList::getFiltered($filter, $params, $group_info, $user_info)
                        ->lastResponses()
                        ->filter('all');

        $results = !empty($data);
        return array('results' => $results, 'data' => $data);
    }

};

$something = new filter();
