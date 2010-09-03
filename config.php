<?php
function __autoload($classname){
	require_once(dirname(__FILE__).'/modules/'.$classname.'.php');
}

session_start();

define('BASE_PATH', realpath(dirname(__FILE__)).'/');

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
define("OK_TOPBAR",BASE_PATH."views/parts/ok_topbar.phtml");
define("OK_FOOTER",BASE_PATH."views/parts/ok_footer.phtml");
define("MODAL_WINDOWS",BASE_PATH."views/parts/modal_windows.phtml");
define("TAP_STREAM", BASE_PATH."views/parts/tap_stream.phtml");

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

define("ROOT","/");
