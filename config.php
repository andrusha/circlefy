<?php
//some debug stuff
ini_set('error_reporting', E_ALL & ~E_NOTICE);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

function exceptionHandler($e) {
    echo <<<EOF
<style type="text/css">
/* <![CDATA[ */

table, td, th
{
    border-color: black;
    border-style: solid;
}

table
{
    border-width: 0 0 1px 1px;
    border-spacing: 0;
    border-collapse: collapse;
    width: 600px;
}

td, th
{
    margin: 0;
    padding: 4px;
    border-width: 1px 1px 0 0;
}

/* ]]> */
</style>
EOF;

    echo '<table align="center" style="margin-top: 40px">';
    echo "<tr><th colspan=2>Exception #{$e->getCode()}</th></tr>";
    echo "<tr><th>Line</td><th>File</td></tr>";
    echo "<tr><td>{$e->getLine()}</td><td>{$e->getFile()}</td></tr>";
    echo "<tr><th colspan=2>Description</td></tr>";
    echo "<tr><td colspan=2>{$e->getMessage()}</td></tr>";
    echo "<tr><th colspan=2>Trace</td></tr>";
    echo "<tr><th>Line</td><th>File</td></tr>";
    foreach ($e->getTrace() as $t) {
        echo "<tr><td>{$t['line']}</td><td>{$t['file']}</td></tr>";
        echo "<tr><td></td><td>";
        
        echo "<table>";
        echo "<tr><th>Function</th><th>Args</th></tr>";
        echo "<tr><td rowspan=".(count($t['args'])+1).">{$t['function']}</td><td ".
            (empty($t['args']) ? '' : "style='display: none'") . "></td></tr>";
        foreach ($t['args'] as $a) {
            echo "<tr><td>".var_export($a, true)."</td></tr>";
        }
        echo "</table>";

        echo "</td></tr>";
    }
    echo '</table>';
}

set_exception_handler('exceptionHandler');

define('BASE_PATH', realpath(dirname(__FILE__)).'/');

function __autoload($classname) {
    $generics = array('Base', 'BaseModel', 'Collection');
    $libs = array('DB', 'Action', 'Comet', 'Curl', 'Exceptions', 'FuncLib',
        'Images', 'Mail');
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
    	require_once(BASE_PATH.'/modules/'.$add_path.$classname.'.php');
}

session_start();


define("METRIC_KEY","05a951428ed5199e27d52356aae83a22");

define("PUBLIC_TEMPLATE",BASE_PATH."views/public_template.phtml");
define("PUBLIC_TEMPLATE_USER",BASE_PATH."views/public_template_user.phtml");
define("PUBLIC_TEMPLATE_GROUP",BASE_PATH."views/public_template_group.phtml");

define("D_ADDR","127.0.0.1");
define("D_PASS","root");
define("D_USER","root");
define("D_DATABASE","rewrite2");

define("OK_HEADER",BASE_PATH."views/parts/ok_header.phtml");
define("OK_SIDEBAR",BASE_PATH."views/parts/ok_sidebar.phtml");
define("NEW_SIDEBAR",BASE_PATH."views/parts/new_sidebar.phtml");
define("OK_TOPBAR",BASE_PATH."views/parts/ok_topbar.phtml");
define("OK_FOOTER",BASE_PATH."views/parts/ok_footer.phtml");
define("MODAL_WINDOWS",BASE_PATH."views/parts/modal_windows.phtml");
define("TAP_STREAM", BASE_PATH."views/parts/tap_stream.phtml");
define("USERS_STREAM", BASE_PATH."views/parts/users_stream.phtml");

define("JAVASCRIPT_TEMPLATES",BASE_PATH."views/parts/javascript_templates.phtml");
define("LOGIN",BASE_PATH."views/parts/login.phtml");
define("FBCONNECT_BOTTOM",BASE_PATH."views/parts/fbconnect.phtml");
define("YOU",BASE_PATH."views/parts/you.phtml");

define("NEW_HEADER",BASE_PATH."views/parts/new_header_2.phtml");
define("HEADER_CLEAN",BASE_PATH."views/parts/header_clean.phtml");
define("ANALYTICS",BASE_PATH."views/parts/analytics.phtml");
define("LEFT",BASE_PATH."views/parts/left.phtml");
define("SIGNUP",BASE_PATH."views/parts/sign_up/sign_up.phtml");
define("CATEGORY",BASE_PATH."views/parts/lists/category.phtml");
define("TITLE","tap");

define("DOMAIN",$_SERVER['HTTP_HOST']);

define("PROFILE_PIC_REL", "/user_pics/");
define("D_GROUP_PIC_REL", "/group_pics/");
define("PROFILE_PIC_PATH", "/var/data/user_pics");
define("D_GROUP_PIC_PATH","/var/data/group_pics");

define("ADMIN_GLOBAL",$_SESSION['admin']);

define("FBAPPID",'e31fd60bbbc576ac7fd96f69215268d0');
define("FBAPPSECRET",'6692d8984d00d3f67ee81bf31637970e');

define('FBPERMISSIONS', 'user_about_me,user_education_history,user_hometown,user_interests,user_likes,user_location,user_work_history,email,read_friendlists,user_groups,publish_stream');

define("ROOT","/");

//group creation options
define("G_ONLINE_COUNT", 1 << 0);
define("G_TAPS_COUNT",   1 << 1);
define("G_USERS_COUNT",  1 << 2);
define("G_EXTENDED",     1 << 3);

define("U_FOLLOWING",    1 << 0);
define("U_FOLLOWERS",    1 << 1);
define("U_LAST_CHAT",    1 << 2);
define("U_BY_UNAME",     1 << 3);
