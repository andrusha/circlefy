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
    protected $need_login = true;

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
        if ($this->need_login)
            $this->user = Auth::identify();
    }

    public function __destruct() {
        if (DEBUG) {
            DB::getInstance()->flush_log();

            $this->debug->log($this->user, 'user');
            $this->debug->group('data', array('Collapsed' => true));
            foreach($this->data as $k => $v)
                $this->debug->log($v, $k);
            $this->debug->groupEnd();
        }

        switch($this->view_output){
            case 'HTML':
                $this->set($this->user->asArray(), 'me');
                $this->set($this->user->guest,     'guest');
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

    public function set($text, $var){
        $this->data[$var] = $text;
    }

    public function get(){
        return $this->data;
    }
};
