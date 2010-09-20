<?php

/*
    Parent of all *List classes, implements basic
    accessors and filter methods
*/
abstract class Collection implements IteratorAggregate, Countable {
    /*
        There would be colection of objects
    */
    protected $data = array();

    //cuz get_called_class only in php >= 5.3.0
    private static $className;

    protected function __construct(array $data, $className = 'Collection') {
        self::$className = $className;
        $this->data = $data;
    }

    public function asArray() {
        return $this->data;
    }

    //make countable
    public function count() {
        return count($this->data);
    }

    //foreach workaround
    public function getIterator() {
        return new ArrayIterator($this->data);
    }

    /*
        Try to filter data by key

        @param string $key
    */
    public function filter($key) {
        //foreach instead of array_walk because
        //array_walk replaces objects
        $result = array();
        foreach($this->data as $item) {
            if (isset($item[$key]))
                $result[] = $item->$key;
            else
                throw new OutOfBoundsException("'$key' is not valid key for data");
        }

        return $result;
    }

    /*
        Merge lists
    */
    public static function merge() {
        $merged = array();
        foreach (func_get_args() as $arg) {
            $merged = array_merge($merged, $arg->asArray());
        }

        $class_name = self::$className;
        return new $class_name($merged);
    }
};
