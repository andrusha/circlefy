<?php

class Media extends BaseModel {
    //ENUM()
    public static $types = array(
      'video' => 1,
      'image' => 2
    );

    public static $fields = array(
      'id', 'type', 'link', 'code', 'title', 'description',
      'thumbnail_url', 'fullimage_url'
    );

    //for type-conversations
    protected static $intFields = array(
      'id', 'type'
    );

    //used for lazy db-updates via __set
    protected static $tableName = 'media';

    public function create($type, $link, $code, $title, $description, $thumbnail_url, $fullimage_url='') {
      $db = DB::getInstance();
      $db->startTransaction();
      
      $data = array('type' => $this->types[type], 'link' => $link, 'code' => $code,
        'title' => $title, 'description' => $description,
        'thumbnail_url' => $thumbnail_url, 'fullimage_url' => $fullimage_url);
      
      try {
          $id = $db->insert('media', $data);
      } catch (SQLException $e) {
          $db->rollback();
          throw $e;
      }
      
      $db->commit();
      return $id;
    }
};
