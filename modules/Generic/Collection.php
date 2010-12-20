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
        return array_map(function ($x) { 
                if ($x instanceof Collection)
                    return $x->asArrayAll();
                else if (method_exists($x, 'asArray'))
                    return $x->asArray();
                else
                    return $x;
            }, $this->data);
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
        Filters data by specified function

        @param function $function
        @return Collection instance of child collection
    */
    public function filterData($function) {
        $class = get_called_class();
        return new $class(array_filter($this->data, $function));
    }

    /*
        Maps a function through all data-set
        Returns _new_ dataset

        @param function $function
        @return Collection instance of child collection
    */
    public function map($function) {
        $class = get_called_class();
        return new $class(array_map($function, $this->data));
    }

    /*
        Injects some value for every item in collection
    */
    public function inject($key, $val) {
        foreach ($this->data as &$it)
            $it->$key = $val;
        
        return $this;
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

    /*
        Append new elemnt to the end of collection
    */
    public function append($element) {
        if (!empty($this->data)) {
            $class = get_class(current($this->data));
            if (!$element instanceof $class)
                throw new InvalidArgumentException("Appended element should be instance of $class");
        }

        $this->data[$element->id] = $element;
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

    /*
        Returns empty collection - may be useful

        @return Collection instance of proper child
    */
    public static function makeEmpty() {
        $class = get_called_class();
        return new $class(array());
    }

    /*
        Create Tree structure with 'childs' as Collections

        @param str $field used as parent key
        @return Collection
    */
    public function formTree($field, $root_id) {
        foreach ($this->data as $key=>$val) {
            $id = $val->$field;
            if (!$id || !isset($this->data[$id])) 
                continue;

            if (!isset($this->data[$id]['childs'])) 
                $this->data[$id]->childs = static::makeEmpty();

            $this->data[$id]->childs->append($val);
        }

        $this->data = array($root_id => $this->data[$root_id]);

        return $this;
    }
};
