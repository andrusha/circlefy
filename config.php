<?php
function __autoload($classname){
	require_once(dirname(__FILE__).'/modules/'.$classname.'.php');
}

session_start();

define("METRIC_KEY","05a951428ed5199e27d52356aae83a22");

define("PUBLIC_TEMPLATE","views/public_template.phtml");
define("PUBLIC_TEMPLATE_USER","views/public_template_user.phtml");
define("PUBLIC_TEMPLATE_GROUP","views/public_template_group.phtml");

define("D_ADDR","127.0.0.1");
define("D_PASS","root");
define("D_USER","root");
define("D_DATABASE","rewrite2");

define("OK_HEADER","parts/ok_header.phtml");
define("OK_SIDEBAR","parts/ok_sidebar.phtml");
define("OK_TOPBAR","parts/ok_topbar.phtml");
define("OK_FOOTER","parts/ok_footer.phtml");
define("MODAL_SIGNUP","parts/modal_signup.phtml");
define("TAP_STREAM", "parts/tap_stream.phtml");
	

//define("FOOTER","parts/footer.phtml");						// OLD
//define("HEADER","parts/final_header.phtml");					// OLD
//define("HEADER_LOGOUT","parts/final_header_logout.phtml");	// OLD
//define("HEADER_SLIM","parts/slim_header.phtml");
//define("HEADER_LOGOUT","parts/final_header_logout.phtml");

define("JAVASCRIPT_TEMPLATES","parts/javascript_templates.phtml");
define("LOGIN","parts/login.phtml");
define("FBCONNECT_BOTTOM","parts/fbconnect.phtml");
define("FBCONNECT_HTML","fbconnect_html.phtml");
define("YOU","parts/you.phtml");

define("NEW_HEADER","parts/new_header_2.phtml");
define("HEADER_CLEAN","parts/header_clean.phtml");
define("ANALYTICS","parts/analytics.phtml");
define("LEFT","parts/left.phtml");
define("SIGNUP","parts/sign_up/sign_up.phtml");
define("CATEGORY","parts/lists/category.phtml");
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
?>
