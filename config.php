<?php
ini_set('error_reporting', E_ALL & ~E_NOTICE);
//ini_set('error_reporting', E_ALL & E_NOTICE);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

//flash uploader & sessions workaround
if (!empty($_FILES) && !empty($_POST) && $_POST['PHPSESSID'])
    session_id($_POST['PHPSESSID']);
session_start();
 
define('BASE_PATH', realpath(dirname(__FILE__)).'/');
define('DEBUG', true);

function __autoload($classname) {
    $generics = array('Base', 'BaseModel', 'Collection');
    $libs = array('DB', 'Action', 'Comet', 'Curl', 'Exceptions', 'FuncLib',
        'Images', 'Mailer', 'FirePHP', 'SeparatorIterator', 'Validation');
    $db = array('MySQL', 'Postgress');
    $add_path = '';
    if (in_array($classname, $generics))
        $add_path = 'Generic/';
    elseif (in_array($classname, $libs))
        $add_path = 'Libs/';
    elseif (in_array($classname, $db))
        $add_path = 'Libs/DB/';


    if (substr($classname, -9) == 'Exception')
        require_once(BASE_PATH.'modules/Libs/Exceptions.php');
    else
    	require_once(BASE_PATH.'modules/'.$add_path.$classname.'.php');
}

if (DEBUG) {
    $firephp = FirePHP::getInstance(true);
    $firephp->registerErrorHandler(
                $throwErrorExceptions=true);
    $firephp->registerExceptionHandler();
    $firephp->registerAssertionHandler(
        $convertAssertionErrorsToExceptions=true,
        $throwAssertionExceptions=false);
}


//use gzipped js & css instead of regular ones
define("GZIPPED", false);

define("D_ADDR",     "127.0.0.1");
define("D_PASS",     "root");
define("D_USER",     "root");
define("D_DATABASE", "circlefy");

define("APC", function_exists('apc_fetch'));
define("STDOUT", fopen('php://stdout', 'w'));

define("DOMAIN",         'circlefy.com');
define("USER_PIC_REL",   "/static/user_pics/");
define("GROUP_PIC_REL",  "/static/group_pics/");
define("USER_PIC_PATH",  "/var/data/user_pics");
define("GROUP_PIC_PATH", "/var/data/group_pics");

define("MAIL_HEADER", BASE_PATH.'views/mails/header.phtml');
define("MAIL_FOOTER", BASE_PATH.'views/mails/footer.phtml');

define("HEADER",      BASE_PATH.'views/parts/header.phtml');
define("FOOTER",      BASE_PATH.'views/parts/footer.phtml');
define("SIDEBAR",     BASE_PATH.'views/parts/sidebar.phtml');
define("FEED",        BASE_PATH.'views/parts/feed.phtml');
define("NOTIFICATION_TAB",     BASE_PATH.'views/parts/notification_tab.phtml');
define("CIRCLE_LIST", BASE_PATH.'views/parts/circle_list.phtml');
define("USER_LIST",   BASE_PATH.'views/parts/user_list.phtml');
define("FOLLOWERS",   BASE_PATH.'views/parts/followers.phtml');
define("REPLIES",     BASE_PATH.'views/parts/replies.phtml');

define("JS_TEMPLATES",  BASE_PATH."views/parts/js_templates.phtml");
/*define("TYPECON",  BASE_PATH."views/parts/typecon.phtml");*/
define("TOOLTIP_TEMPLATES",  BASE_PATH."views/parts/tooltips.phtml");
define("MODAL_WINDOWS", BASE_PATH."views/parts/modal_windows.phtml");
define("FBCONNECT",     BASE_PATH."views/parts/fbconnect.phtml");

define("FBAPPID",     'e31fd60bbbc576ac7fd96f69215268d0');
define("FBAPPSECRET", '6692d8984d00d3f67ee81bf31637970e');
//define("FBAPPID",     '151208708259047');
//define("FBAPPSECRET", 'f6e7e5edcf67283f629034b02a9e564d');

define('FBPERMISSIONS', 'user_about_me,user_education_history,user_hometown,user_interests,'.
                        'user_likes,user_location,user_work_history,email,read_friendlists,'.
                        'user_groups,publish_stream');

define("METRIC_KEY",  "05a951428ed5199e27d52356aae83a22");

//define("TRANSLATE_KEY", 'ABQIAAAAdCk83cDkZ0ia3ghCbXi2mxREPhq7TN-nX4_0yzQXinh4q73vgRRnGh_3vcUpJMs_OohdY_B1C5P9Jw'); //circlefy
define("TRANSLATE_KEY", 'ABQIAAAAdCk83cDkZ0ia3ghCbXi2mxQl_1nmNw5LYBV_RSbp6zmT9736TxROQQ_ELFNHmVc7LXvs22C1LmE2Jg'); //andrew.tap.info
//*** OPTIONS ***

define("KEYWORDS_TRASHOLD",  3);
define("GROUPS_FROM_LIKES", 10);
define("DEFAULT_ROW_COUNT", 10);

define("G_TAPS_COUNT",      1 << 0);
define("G_USERS_COUNT",     1 << 1);
define("G_RESPONSES_COUNT", 1 << 2);
define("G_JUST_ID",         1 << 3);

define("U_LAST_CHAT", 1 << 0);
define("U_BY_UNAME",  1 << 1);
define("U_ONLY_ID",   1 << 2);
define("U_PENDING",   1 << 3);
define("U_ADMINS",    1 << 4);

define("T_LIMIT",      1 << 0);
define("T_SEARCH",     1 << 1);
define("T_GROUP_INFO", 1 << 2);
define("T_USER_INFO",  1 << 3);
define("T_USER_RECV",  1 << 4);
define("T_MEDIA",      1 << 5);
define("T_INSIDE",     1 << 6);
define("T_OUTSIDE",    1 << 7);
define("T_NEW_REPLIES",1 << 8);
define("T_ANON",       1 << 9);
