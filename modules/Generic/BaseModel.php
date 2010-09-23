<?php
abstract class BaseModel implements ArrayAccess {
    /*  @var DB  */
    protected $db;
    protected $data = array();

    //specify allowed class members to get
    protected $allowed = array();

    //specify allowed info in $data
    protected $allowedArrays = array();

    public function __construct() {
        $this->connect();
    }

    protected function connect() {
        $this->db = DB::getInstance();
        $this->db->Start_Connection('mysql');
    }

    abstract public function __get($key);

    /*
        Checks if a key is allowed
    */
    public function offsetExists ($offset) {
        if (in_array($offset, $this->allowed))
            return true;

        foreach ($this->allowedArrays as $a)
            if (isset($this->data[$a]))
                if (array_key_exists($offset, $this->data[$a]))
                    return true;
        
        return false;
    }

    /* Stubs, you should use __get, __set instead */
    public function offsetGet ($offset) { }
    public function offsetSet ($offset, $value) { }
    public function offsetUnset ($offset) { }

    /*
        Cast provided fields into int
        use with array_walk
    */
    protected function typeCast(&$val, $key, array $fields) {
        if (in_array($key, $fields))
            $val = intval($val);
    }
};