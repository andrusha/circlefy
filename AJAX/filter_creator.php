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
flag  -
    flag to show personal responses you've tapped in a personal feed ( not used )
more -
    the more offset if you want a different set of the feed rather then the latest ( i.e. pagiation )
id -
    the id of the channel you want to call
EOF;
    
session_start();
require('../config.php');
require('../api.php');
require('../modules/Taps.php');

$type = $_POST['type'];
$search = $_POST['search'];
$outside = $_POST['outside'];
$more = $_POST['more'];
$flag = $_POST['flag'];
$id = $_POST['id'];
$anon = $_POST['anon'];

if(isset($type)){
    $filter_function = new filter_functions();
    $res = $filter_function->filter($type,$search,$outside,$id,$flag,$more,$anon);
    echo json_encode($res);
} else {
    api_usage($usage);
}

class filter_functions {
    
    function filter($type,$search,$outside,$id,$flag,$more,$anon) {
        $uid = intval($_SESSION['uid']);
        $params = array();
        $current_user = new User($uid);

        if ($more)
            $params['#start_from#'] = $more;

        if (!$outside)
            $outside = '1, 2';

        $params['#outside#'] = $outside;

        if (isset($anon))
            $params['#anon#'] = $anon;
        
        $search = mysql_escape_string($search);
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
                $params['#uid#'] = $uid;
                break;
            case   1:
                $filter = 'ind_group';
                $params['#gid#'] = intval($id);
                Action::log($current_user, 'group', 'view', array('gid' => intval($id)));
                break;
            case  22:
                $filter = 'aggr_personal';
                $params['#uid#'] = $uid;
                break;
            case   2:
                $filter = 'personal';
                $params['#uid#'] = intval($id);
                Action::log($current_user, 'user', 'view', array('uid' => intval($id)));
                break;
            case  33:
                $group_info = false;
                $user_info = true;
                $filter = 'aggr_private';
                $params['#uid#'] = $uid;
                break;
            case   3:
                $group_info = false;
                $user_info = true;
                $filter = 'private';
                $params['#from#'] = $uid;
                $params['#to#'] = intval($id);
                break;
            case  4:
                $group_info = true;
                $user_info = true;
                $filter = 'aggr_all';
                $params['#uid#'] = $uid;
            case  5:
                $filter = 'convos_all';
                $params['#uid#'] = $uid;
        }

        $taps = new Taps();
        $data = $taps->getFiltered($filter, $params, $group_info, $user_info);

        $results = !empty($data);
        return array('results' => $results, 'data' => $data);
    }

};
