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
    2 = IND People
    22 = AGGR Peoples
    3 = IND Filter
    33 = AGGR Filters
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
            case   2:
                $filter = 'personal';
                $params['#uid#'] = intval($id);
                Action::log($current_user, 'user', 'view', array('uid' => intval($id)));
                break;
        }

        $taps = new Taps();
        $data = $taps->getFiltered($filter, $params);

        return array('results' => True, 'data' => $data);
    }

};
