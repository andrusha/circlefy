<?php
class DB {
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

    /*
        Query log for debugging  
    */
    private $queries = array();

    /*
        A list of pending updates 

        array(
            table => array(
                id => array(
                    column => value
    */
    private $updates = array();

    /*
        A list of pending inserts

        array(
            table => array(
                array(
                    column => value
    */
    private $inserts = array();

    private function __construct() {}

    function __destruct() {
        if ($this->transactions > 0)
            throw new TransactionException('You forgot to commit or rollback transactions');
    }

    /*
        Sending debug log to browser (making explain queries too)
    */
    public function flush_log() {
        if (empty($this->queries))
            return;

        $firephp = FirePHP::getInstance(true);
        $firephp->group('SQL', array('Collapsed' => true));
        $total = 0;
        foreach ($this->queries as $q) {
            list($q, $t) = $q;
            $firephp->log($q, $t);

            $total += $t;

            if (stripos($q, 'delete') !== false || stripos($q, 'update') !== false || stripos($q, 'insert') !== false)
                continue;

            $explain = array();
            $explain[] = array('id', 'select_type', 'table', 'type', 'possible_keys',
                             'key', 'key_len', 'ref', 'rows', 'extra');
            $result = $this->db->query('EXPLAIN '.$q);
            while ($row = $result->fetch_row())
                $explain[] = $row;
            $firephp->table('Explain', $explain);
        }
        $firephp->log($total, 'Total');
        $firephp->groupEnd();
        $this->queries = array();

        if (!empty($this->updates)) {
            $firephp->warn($this->updates, 'Uncommited updates');
        }
    }

    static public function getInstance() { 
        if (empty(self::$instance)) {
            self::$instance = new DB();
            self::$instance->db = MySQL::getInstance();
        }

        return self::$instance;
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
            return call_user_func_array(array($this, $name), $args);
        else if (method_exists($this->db, $name))
            return call_user_func_array(array($this->db, $name), $args);

        throw new NotImplementedException('Wrong method named `'.$name.'`');
    }

    /*
        A simple query with params support
        if params is empty, then query db as usual

        if not, replace #placeholder# with 
        value or 'value' or 'value1', 'value2', 'value3'
        depending on value type (int, str, array)
    */
    public function query($query, array $params = array()) {
        if (!empty($params)) {
            $this->params = $params;
            $query = preg_replace_callback('/#([a-z_0-9^#]*)#/i', array($this, 'prepareSql'), $query);
        }

        $time = microtime(true);
        $result = $this->db->query($query);
        $time = microtime(true) - $time;

        if (DEBUG)
            $this->queries[] = array($query, round($time, 5));

        if ($this->db->errno) {
            if (DEBUG)
                $this->flush_log();
            throw new SQLException('Error '.$this->db->errno.' occured: '.$this->db->error);
        }

        return $result;
    }

    public function insert($table, array $params) {
        $fields = implode(', ', array_keys($params));
        $values = implode(', ', array_map(function ($x) { return "#$x#"; }, array_keys($params)));
        $query  = "INSERT INTO `$table` ($fields) VALUES ($values)";
        $this->query($query, $params, $dump);
        return $this->db->insert_id;
    }

    /*
        Formats and inserts a list of values into DB
    */
    public function listInsert($query, array $list) {
        if (empty($list))
            throw new LogicException('You should insert at least 1 item');

        $formatted = array_map(array($this, 'formatByType'),  $list);
        $formatted = ' ('.implode('),(', $formatted).')';

		$query = str_ireplace('#values#', $formatted, $query);
        return $this->query($query, array());
    }

    /*
        Fetch every line from result and return it in following format
        
        @param MySQLi_Result $result
        @param array         $tables this tables should be separated

        array(
            0 => array( table_name from $tables => array(field_name => val, ...
                        ...,
                        'rest' => fields),
            ...

        @return SeparatorIterator
    */
    public static function getSeparator(MySQLi_Result $result, array $tables) {
        return new SeparatorIterator($result, $tables);
    }

    /*
        Return escaped & formatted parameter of sql query
    */
    private function prepareSql($matches) {
        if (is_array($matches))
            $name = $matches[1];
        else
            $name = $matches;

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
        } else if (is_int($value) || is_float($value) || in_array($value, array('CURRENT_TIMESTAMP'))) {
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

    public function lazyUpdate($table, $id, $column, $value) {
        $this->updates[$table][$id][$column] = $value;
        return $this;
    }

    /*
        Saves all lazy commits for table
    */
    public function commitLazyUpdate($table, $id) {
        if (empty($this->updates[$table][$id]))
            return;
        
        $columns = $this->updates[$table][$id];
        $fields = implode(', ', 
            array_map(function ($elem) {
                    return "$elem = #$elem#";
                },
                array_keys($columns)));

        $query = "UPDATE `$table` SET ".$fields." WHERE id = #id#";
        $this->query($query, array_merge($columns, array('id' => $id)));

        unset($this->updates[$table][$id]);
    }

    /*
        You should provide same array keys for each table
        or it would cauze SQLException
    */
    public function lazyInsert($table, array $values) {
        $this->inserts[$table][] = $values;
        return $this;
    }

    public function commitLazyInsert($table) {
        if (empty($this->inserts[$table]))
            return;

        $columns = $this->inserts[$table];
        $fields  = implode(',', array_keys(current($columns)));
        $query   = "INSERT INTO `$table` ($fields) VALUES #values#";

        $this->listInsert($query, $columns);

        unset($this->inserts[$table]);
    }
};
