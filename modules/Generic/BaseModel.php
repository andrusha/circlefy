<?php
abstract class BaseModel implements ArrayAccess, Serializable {
    /*  @var DB  */
    protected $db;

    //a list of fields
    public static $fields = array();

    //specify allowed class members to get
    protected static $addit = array();

    //used for typecasting
    protected static $intFields = array();

    //entity ID
    protected $id = null;
    protected $data = array();

    public function __construct($id) {
        $this->connect();

        if (is_array($id)) {
            $this->data = $id;
            array_walk($id, array($this, 'typeCast'), static::$intFields);
            $this->id = $id['id'];
        } else {
            $this->id = $id;
        }
    }

    protected function connect() {
        $this->db = DB::getInstance();
    }

    /*
        Checks if a key is allowed
    */
    public function offsetExists ($offset) {
        return $this->keyExists($offset) && isset($this->data[$offset]);
    }

    private function keyExists ($offset) {
        //Late Static Binding here
        return in_array($offset, static::$addit) ||
               in_array($offset, static::$fields);
    }

    public function __get($key) {
        if ($key == 'id')
            return $this->id;

        if ($this->keyExists($key)) {
            $name = 'get'.ucfirst($key);

            if (method_exists($this, $name) && !isset($this->data[$key])) {
                if ($this->id === null)
                    throw new InitializeException('ID should be set before data fetching');

                $this->data[$key] = $this->$name();
            }

            return $this->data[$key];
        }

        throw new DataException("There is no data named `$key` or you are not allowed to get it");
    }

    /*
        Warning!
        It doesn't update corresponding table actually, use with care
    */
    public function __set($key, $val) {
        if ($this->keyExists($key))
            $this->data[$key] = $val;
        else
            throw new DataException("You are not allowed to set `$key`");
    }

    public function asArray() {
        $data = $this->data;
        foreach (static::$addit as $key)
            if (is_object($data[$key]) && method_exists($data[$key], 'asArray'))
                $data[$key] = $data[$key]->asArray();

        return $data;
    }

    /* Stubs, you should use __get, __set instead */
    public function offsetGet ($offset) { }
    public function offsetSet ($offset, $value) { }
    public function offsetUnset ($offset) { }

    /*
        Cast provided fields into int
        use with array_walk
    */
    protected static function typeCast(&$val, $key, array $fields) {
        if (in_array($key, $fields))
            $val = intval($val);
    }

    public function serialize() {
        return serialize(array($this->id, $this->data));
    }

    public function unserialize($str) {
        list($this->id, $this->data) = unserialize($str);
        $this->connect();
    }
};
