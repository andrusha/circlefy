<?php
//This class defines all of the database queries as well as what connection is to be used.
//The explination of how to set queries is listed below.  The way you set your database connector
//depends on the flag that either your Base class has or your page has.  

class DB {
    //There are 2 methods of setting queries:
    //  #1. One is by using the method set_query(); in each page to set $query_list dynamically for each page
    //    #2. Creating the array statically by doing $query_list['name_of_query'] = "SELECT * FROM user etc";
    //A static list populated everytime may be faster, but it may be a pain to maintain, or not, it depends on your prefernce.
    //static public $db;
    public $query_list = array();

    //protected $db_type = 'MySQL';
    protected $db_debug = 0;
    static private $count = 0;

    static private $instance;

    //This is your $db class, it defaults to mysqli, but you can change it with db-type
    //Use: $db->query("SELECT ..");
    public $db = NULL;

    private $transactions = 0;

    /*
        Params for current query,
        set in every parametrized DB::query call
    */
    private $params = array();

    function __default() {}    
    private function __construct() {}

    function __destruct() {
        if ($this->transactions > 0)
            throw new TransactionException('You forgot to commit or rollback transactions');
    }

    static public function getInstance() { 
        if (empty(self::$instance)) 
            self::$instance = new DB();

        return self::$instance;
    }
          
     public function Start_Connection($type) {
        switch($type) {
            case 'mysql':
                $this->db = MySQL_Conn::getInstance();
                break;

            case 'postgress':
                $this->db = Postgress_Conn::getInstance();
                break;
        }

        return self::$instance;
     }
     
     protected function Get_Connection($db_type){
        $this->db_type = $db_type;
        
        switch($this->db_type) {
            case 'mysql':
                if ($this->db)
                    return $this->db;
                else 
                    echo "Error you have no database connection yet you declared a direct database connection";
            break;
        }    
    }

    /*
        Proxing all mysqli variables right to user

        TODO: make better implementation in future
    */
    public function __get($key) {
        if (!is_null($this->$key))
            return $this->$key;
        else if (!is_null($this->db->$key))
            return $this->db->$key;
            
        throw new OutOfBoundsException('Wrong key `'.$key.'`');
    }
    
    /*
        Proxing mysqli methods direct to DB class
        for back compatability

        TODO: make better implementation in future
    */
    public function __call($name, $args) {
        if (method_exists($this, $name))
            return call_user_method_array($name, $this, $args);
        else if (method_exists($this->db, $name))
            return call_user_method_array($name, $this->db, $args);

        throw new NotImplementedException('Wrong method named `'.$name.'`');
    }

    public function set_query($query,$name,$comment,$x='') {
        $comment_name = $name."_comment";
        $this->query_list[$name] = $query;
        $this->query_list[$comment_name] = $comment;
    }

    public function last_insert_id(){
        return $this->db->insert_id; 
    }

    /*
        A simple query with params support
        if params is empty, then query db as usual

        if not, replace #placeholder# with 
        value or 'value' or 'value1', 'value2', 'value3'
        depending on value type (int, str, array)
    */
    public function query($query, array $params = array(), $dump = false) {
        if (!empty($params)) {
            $this->params = $params;
            $query = preg_replace_callback('/#([a-z_0-9^#]*)#/i', array($this, 'prepareSql'), $query);
        }
        
        if ($dump)
            var_dump($query);

        $result = $this->db->query($query);

        if ($this->db->errno) {
            throw new SQLException('Error '.$this->db->errno.' occured: '.$this->db->error);
        }

        return $result;
    }

    /*
        Formats and inserts a list of values into DB
    */
    public function listInsert($query, array $list, $show = false) {
        if (empty($list))
            throw new AssertionException('You should insert at least 1 item');

        $formatted = array_map(array($this, 'formatByType'),  $list);
        $formatted = ' ('.implode('),(', $formatted).')';

        $query = str_ireplace('#values#', $formatted, $query);
        return $this->query($query, array(), $show);
    }

    /*
        Return escaped & formatted parameter of sql query
    */
    private function prepareSql($matches) {
        if (is_array($matches))
            $name = $matches[1];
        else
            $name = $matches;

        //format by type now support NULLs
        /*
        if (!isset($this->params[$name]))
            throw new QueryParamException('Undefined `'.$name.'` parameter');
        */

        $value = $this->params[$name];
        return $this->formatByType($value);
    }

    private function formatByType($value) {
        if (is_array($value)) {
            //every array element should be escaped too
            $str = '';
            foreach($value as $x)
                $str .= ', '.$this->formatByType($x);
            return substr($str, 2);
        } else if (is_int($value) || is_float($value)) {
            //int values don't need to be escaped
            return strval($value);
        } else if ($value === null) {
            return 'NULL';
        } else {
            //escape all special chars in string
            return "'".$this->db->real_escape_string($value)."'";
        }
    }

    public function startTransaction() {
        $this->db->query('START TRANSACTION');
        $this->transactions++;
    }

    public function commit() {
        $this->transactions--;
        if ($this->transactions < 0)
            throw new TransactionException('Commits more than start transactions');

        if (!$this->transactions)
            $this->db->commit();
    }

    public function rollback() {
        //we surely will got exception
        //on nested transactions
        $this->transactions = 0;
        $this->db->rollback();
    }

    public function execute_query($query_name) {
        if(!$this->db_debug) {
            $result = $this->db->query($this->query_list[$query_name]);
            return $result;
        }

        if($this->db_debug) {
            self::$count++;
            $start = microtime(true);
            $result = $this->db->query($this->query_list[$query_name]);
            echo "<br/>-- Query #".self::$count.': - '.$this->query_list[$query_name].'<br/>-- Time: <b>';
        
            $end = microtime(true);
            $que55ry_length = $end - $start;
            $query_length = $query_length;

            echo $query_length;
            echo "<br/>-- Comments:</b> ".$this->query_list[$query_name."_comment"];
        }

        if($query_length > .2)
            echo "- This query may be slowing you down    ";

        echo '<br/>';

        return $result;

    }
};
