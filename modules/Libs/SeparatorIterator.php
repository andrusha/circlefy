<?php

/*
    Bad-ass iterator, separate MySQLi result by
    provided table names

    Special magic of SeparatorIterator is fetching
    columns metadata before fetching a resulted columns
*/
class SeparatorIterator implements Iterator {
    /* @var MySQLi_Result */
    private $result = null;
    private $tables = array();
    private $fields = array();

    private $row = array();
    private $count = 0;
    private $pos = 0;

    public function __construct(MySQLi_Result $result, array $tables) {
        $this->result = $result;
        $this->tables = $tables;
        $this->count  = $result->num_rows;
        $this->fields = $result->fetch_fields();
    }

    public function rewind() {
        $this->result->data_seek(0);
    }

    public function valid() {
        return $this->pos < $this->count;
    }

    public function current() {
        return $this->row;
    }

    public function key() {
        return $this->pos;
    }

    /*
        Fetch every line from result and return it in following format

        array(
            0 => array( table_name from $tables => array(field_name => val, ...
                        ...,
                        'rest' => fields),
            ...
    */
    public function next() {
        $row = array();
        foreach ($this->result->fetch_row() as $id => $val) {
            $table = $this->fields[$id]->table;
            if (!in_array($table, $tables))
                $table = 'rest';

            $row[$table][$this->fields[$id]->name] = $val;
        }

        $this->row = $row;
    }
};
