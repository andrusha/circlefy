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

    protected function __construct(array $data) {
        $this->data = $data;
    }

    public function asArray() {
        return $this->data;
    }

    public function asArrayAll() {
        return array_map(function ($x) { return $x->asArray(); }, $this->data);
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
        foreach($this->data as $item)
            $result[] = $item->$key;

        return $result;
    }

    /*
        Unique collection by specified key

        @return Collection
    */
    public function unique($key) {
        $uniq = array();
        foreach($this->data as $val)
            if (!array_key_exists($val->$key, $uniq))
                $uniq[ $val->$key ] = $val;

        $this->data = array_values($uniq);
        return $this;
    }

    /*
        Merge lists
    */
    public static function merge() {
        $merged = array();
        foreach (func_get_args() as $arg)
            $merged = array_merge($merged, $arg->asArray());

        $class_name = get_called_class(); 
        return new $class_name($merged);
    }
    
    public function lastOne() {
        end($this->data);
        return current($this->data);
    }

    public function getFirst() {
        reset($this->data);
        return current($this->data);
    }

    protected function joinDataById(array $data, $name, $default = 0) {
        foreach ($this->data as &$d) {
            if (isset($data[$d->id]))
                $d->$name = $data[$d->id];
            else
                $d->$name = $default;
        }

        return $this;
    }
};
