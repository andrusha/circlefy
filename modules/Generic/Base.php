<?php
abstract class Base {
    //template variables
    protected $data = array();
    private   $page_name;
    protected $debug = null;

    //HTML | JSON
    protected $view_output = "HTML";

    //These properties are flags
    protected $need_db    = false;
    protected $need_login = false;

    /* @var DB */
    protected $db;

    /* @var User */
    protected $user;

    //calls after object creation
    abstract public function __invoke();

    /*
        @param string|null $template null for JSON
    */
    public function __construct($template = null) {
        $this->page_name = $template;

        if (DEBUG)
            $this->debug = FirePHP::getInstance(true);

        if ($this->need_db)
            $this->db = DB::getInstance();

        //actually, we need user everywhere
        if ($this->need_login || true)
            $this->userActions();
    }

    public function __destruct() {
        if (DEBUG)
            DB::getInstance()->flush_log();

        switch($this->view_output){
            case 'HTML':
                $this->set($this->user->asArray(), 'me');
                $this->renderPage(BASE_PATH.'/views/'.$this->page_name.'.phtml', $this->data);
                break;
            
            case 'JSON':
                echo json_encode($this->data);
                break;
        }
    }

    private function renderPage($__pagename, $__variables) {
        extract($__variables);
        ob_start();
        include($__pagename);
        echo ob_get_clean();
    }

    /*
        Create user, based on possible actions on it, like login/logout, etc
    */
    private function userActions() {
 		if (isset($_GET['logout']) && !$_POST['uname']){
			Auth::logOut();
            header("location: /");
            exit();
		}

        if (!empty($_POST['uname']) && !empty($_POST['pass']) && $_POST['action'] == 'login_basic')
           $this->user = Auth::logIn($_POST['uname'], $_POST['pass']);
        else if ($_POST['fb_login'] == '1' && $_POST['action'] == 'login_basic')
           $this->user = Auth::logInWithFacebook();
        else
           $this->user = Auth::identify();
    }
      
    public function set($text, $var){
        $this->data[$var] = $text;
    }

    public function get(){
        return $this->data;
    }
};
