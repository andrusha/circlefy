<?php
abstract class Base{

    protected $errors = array(); //Any errors will be reported here

    //These are variables that you should not change.
    protected $data = array();
    protected $page_name;

    //This is the stylesheet that your program will use, you can chose to change 
    //this style sheet on a per-page basis by setting $this->stylesheet on any page
    protected $stylesheet = "/main.css";

    //This variable lets you chose what type of output the view will show, you can chose to have HTML
    //XML, or JSON output based on 
    protected $view_output = "HTML";
    protected $db_type = 'mysql';

    //These properties are flags
    //For example, if you need a database conncetion, in your page, set $this->need_db = 1 and it will load the DB class
    //and give you a database connection
    protected $need_db    = false;
    protected $need_login = false;

    /*
        @var DB
    */
    protected $db;

    /*
        @var User
    */
    protected $user;

    //The page has access to this variable incase you want to use mysqli/PDO directly and bypass the framework.

    //abstract function __default();

    public function __toString(){
        return "Base Class";
    }

    protected function __construct(){
        self::set($this->stylesheet,'stylesheet');
        self::set($this->view_output,'output');

        if ($this->need_db)
            $this->db = DB::getInstance()->Start_Connection($this->db_type);

        //actually, we need user everywhere
        if ($this->need_login || true) {
            $this->userActions();

            $this->set(
                array_intersect_key(
                    $this->user->info,
                    array_flip(
                        array('uname', 'uid', 'fb_uid', 'real_name',
                              'big_pic', 'small_pic', 'guest', 'fb_uid')))
                , 'me');
        }
    }

    protected function __destruct() {
        $_SESSION['user'] = serialize($this->user);
    }

    /*
        Create user, based on possible actions on it, like login/logout, etc
    */
    private function userActions() {
        if (!empty($_POST['uname']) && !empty($_POST['pass']) && empty($_POST['fb_login']))
           $this->user = Auth::logIn($_POST['uname'], $_POST['pass']);
        else if ($_POST['fb_login'] == '1')
           $this->user = Auth::logInWithFacebook();
        else
           $this->user = Auth::identify();
   
 		if ($_GET['logout'] && !$_POST['uname']){
			Auth::logOut();
            header("location: /");
            exit();
		}
    }
   
   protected function input_degbug(){
        foreach($_POST as $key => $val){
            echo $key." => ".$val."<br/>";
        }
    }
    
    public function set($text,$var){
        $this->data[$var] = $text;
    }

    public function get(){
        return $this->data;
    }

    public function page(){
        return $this->page_name;
    }
};
