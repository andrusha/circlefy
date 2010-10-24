<?php

class Media extends BaseModel {
    //ENUM()
    public static $types = array();

    public static $fields = array();

    //for type-conversations
    protected static $intFields = array();

    //used for lazy db-updates via __set
    protected static $tableName = 'media';

    public function create() {

    }
};
