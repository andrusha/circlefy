<?php
// !!!!!!!!!!!  MAKE SURE YOU CHANGE THE CLASS NAME FOR EACH NEW CLASS !!!!!!!
class fb extends Base{

    protected $text;
    protected $top;

    function __default(){
    }

    public function __toString(){
        return "Facebook Object";
    }

    function __construct(){

        $this->view_output = "HTML";
        $this->db_type = "mysql";
        $this->page_name = "fb";
        $this->need_login = 1;
        $this->need_db = 1;
        $this->useOpenGraph = 1;

        parent::__construct();
        
        $this->set($this->facebook, 'fb');
        
        //var_dump($this->facebook->facebook->getLoginUrl(array('ext_perm'=>'read_stream')));
        //exit;
        
        $uid = $_SESSION['uid'];
        //Takes awayfist settings flag
        setcookie('profile_edit','',time()-360000);
                
    }
    
    
}
?>
