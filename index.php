<?php
/*
    Here goes queries _routing_

    It is the single entry point for
    both AJAX & php pages
*/
require_once('config.php');

if (DEBUG) {
    $firephp->group('Server params', array('Collapsed' => true));
    $firephp->log($_GET,     'GET');
    $firephp->log($_POST,    'POST');
    $firephp->log($_COOKIES, 'Cookies');
    $firephp->log($_SESSION, 'Session');
    $firephp->groupEnd();
}

//default page should be always allowed
$default_page = 'homepage';
$pages = array(
        'homepage' => array(
            'allowed'  => true,
            'template' => 'homepage'
        ),
        'circle'   => array(
            'allowed'  => true,
            'template' => 'circle',
            'actions' => array(
                'edit' => 'group_edit'
            )
        ),
        'user'     => array(
            'allowed'  => true,
            'template' => 'user'
        ),
        'convo'    => array(
            'allowed'  => true,
            'template' => 'conversation'
        ),
        'group_edit' => array(
            'allowed'  => false,
            'template' => 'group_edit'
        ),
    );

$ajaxs = array(
        'user' => array(
                'check'    => true,
                'facebook' => true,
                'login'    => true,
                'typing'   => true
        ),
        'group' => array(
                'suggest'   => true,
                'search'    => true,
                'get'       => true,
                'exists'    => true,
                'update'    => true
        ),
        'taps' => array(
               'responses' => true,
               'respond'   => true,
               'filter'    => true,
               'new'       => true
        ),
        'follow' => true
    );

$ajax_bypass = array('typing');

$page = $_GET['page'];
$ajax = $_GET['ajax'];
$type = $_GET['type'];

if (isset($page) || !isset($ajax)) {
    if (array_key_exists($page, $pages) && $pages[$page]['allowed']) {
        //ugly piece of shit:
        if (isset($pages[$page]['actions']))
            foreach ($pages[$page]['actions'] as $action => $p)
                if (isset($_GET[$action]) && array_key_exists($p, $pages)) {
                    $page = $p;
                    break;
                }
    } else {
        $page = $default_page;
    }

    $name = 'page_'.$page;
    require_once(BASE_PATH.'pages/'.$page.'.php');
    $newpage = new $name($pages[$page]['template']);
    $newpage();
} elseif (isset($ajax)) {
    if (isset($type) && array_key_exists($type, $ajaxs))
        $ajaxs = $ajaxs[$type];

    if (!array_key_exists($ajax, $ajaxs) || !$ajaxs[$ajax])
        die('You are not allowed to use this API');

    $name = 'ajax_'.$ajax;
    require_once(BASE_PATH.'AJAX/'.($type ? $type.'/' : '').$ajax.'.php');
    if (!in_array($ajax, $ajax_bypass)) {
        $ajax = new $name();
        $ajax();
    }
}
