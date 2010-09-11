<?php

/*
    Tag workaround, may be used with any other model

    Lazy = initiate/save on demand
*/
class Tags extends BaseModel {
    /*
        If id is null, then we should
        create new tag group
    */
    private $tagGroupId = null;

    /*
        Array(tag_id => tag, tag_id => tag, ...,
              'new' => array(tag, tag, ...),
              'del' => array(tag_id, tag_id, ...)
    */
    private $tags = array('new' => array(), 'del' => array());

    /*
        
    */
    private $inited = false;

    /*
        Use existing tag group or creates new
    */
    public function __construct($tagGroupId = null) {
        parent::__construct();
        $this->tagGroupId = $tagGroupId;
    }
    
    /*
        Saves all modifications on exit
    */
    public function __destruct() {
        $this->commit();
    }

    /*
        Fetch tags from DB
    */
    private function init() {
        if ($this->tagGroupId === null ||
            $this->inited)
            return;

        $query = "
            SELECT t.tag_id, t.tag
              FROM tags_group tg
             INNER
              JOIN tags t
                ON t.tag_id = tg.tag_id
             WHERE tg.tag_group_id = #tgid#";

        $result = $this->db->query($query, array('tgid' => $this->tagGroupId));
        if ($result->num_rows)
            while ($res = $result->fetch_assoc())
                $this->tags[ intval($res['tag_id']) ] = $res['tag'];

        $this->inited = true;
    }

    /*
        So, it simply returns a list of tags
        and corresponding tag id's for them
    */
    public function get() {
        $this->init();
        //we should commit changes first
        $this->commit();

        $tags = $this->tags;
        unset($tags['new']);
        unset($tags['del']);

        return $tags;
    }

    public function getGroupId() {
        return $this->tagGroupId;
    }

    /*
        Adds a new tag, if not exists
        and changes state to changed,
        so it can be commited to DB
    */
    public function addTag($tag) {
        $this->init(); //it would actually work without it

        if (!in_array($tag,$this->tags)) {
            $tag = trim($tag);

            $this->tags['new'][] = $tag;
            return true;
        }

        return false;
    }

    /*
        Simply adds a bounch of tags
    */
    public function addTags(array $tags) {
        foreach ($tags as $tag)
            $this->addTag($tag);
    }

    /*
        Removes tag from tag array
    */
    public function removeTag($tag) {
        $this->init(); //we actually may do without it

        if (in_array($tag, $this->tags)) {
            $key = array_search($tag, $this->tags);
            $this->tags['del'][] = $key;
            unset($this->tags[$key]);

            return true;
        } else if (in_array($tag, $this->tags['new'])) {
            $key = array_search($tag, $this->tags['new']);
            unset($this->tags['new'][$key]);

            return true;
        }

        return false;
    }
    
    /*
        Answers is there some changes in out tags or not
    */
    private function changed() {
        return !empty($this->tags['new']) || 
               !empty($this->tags['del']);
    }

    /*
        Saves tag modifications
    */
    public function commit() {
        if (!$this->changed())
            return;

        $this->db->startTransaction();
        $ok = true;

        if ($this->tagGroupId === null) {
            $this->tagGroupId = $this->createGroup();
        }

        //insert new tags
        if (!empty($this->tags['new'])) {
            $existing = $this->tagIdsByTaglist($this->tags['new']);
            $toInsert = array_udiff($this->tags['new'], $existing, 'strcasecmp');
            $existing = array_keys($existing); //leave only keys
            
            $inserted = array();
            if (!empty($toInsert))
                $inserted = array_keys($this->insertTags($toInsert));

            $ok = $ok && $this->insertIntoGroup(array_merge($existing, $inserted));
        }

        //delete old ones
        if (!empty($this->tags['del'])) {
            $toDelete = $this->tags['del'];
            $ok = $ok && $this->deleteFromGroup($toDelete);
        }

        if ($ok) {
            $this->db->commit();

            $this->tags['new'] = array();
            $this->tags['del'] = array();
        } else
            $this->db->rollback();

        return $ok;
    }

    /*
        Specify tag_id for each tag
    */
    private function tagIdsByTaglist(array $taglist) {
        $query = "
            SELECT tag_id, tag
              FROM tags
             WHERE tag IN (#taglist#)";

        $tags = array();
        $result = $this->db->query($query, array('taglist' => $taglist), true);
        if ($result->num_rows)
            while ($res = $result->fetch_assoc())
                $tags[ intval($res['tag_id']) ] = $res['tag'];
        
        return $tags;
    }

    /*
        Deletes tags from tag group
    */
    private function deleteFromGroup(array $idlist) {
        $query = "
            DELETE tags_group
             WHERE tag_id IN (#list#)";
        $ok = $this->db->query($query, array('list' => $idlist));

        return $ok;
    }

    /*
        Inserts tags into tag group
    */
    private function insertIntoGroup(array $idlist) {
        if (!function_exists('merge_items')) {
            function merge_items(&$item, $key, $toMerge) {
                $item = array_merge((array)$toMerge, (array)$item);
            }
        }
        
        $query = "INSERT INTO tags_group (tag_group_id, tag_id) VALUES #values#";

        //add group id for each item
        array_walk($idlist, 'merge_items', $this->tagGroupId);
        $ok = $this->db->listInsert($query, $idlist);

        return $ok;
    }

    /*
        Returns id for new group
    */
    private function createGroup() {
        $query = "
            SELECT (MAX(tag_group_id) + 1) AS id
              FROM tags_group";

        $result = $this->db->query($query)->fetch_assoc();
        $id = intval($result['id']);

        return $id;
    }

    /*
        Inserts a tags to DB
    */
    private function insertTags($taglist) {
        $query = "INSERT INTO tags (tag) VALUES #values#";
        $this->db->listInsert($query, $taglist);

        //ugly, but thing based on last insert id wouldn't work
        //see: http://stackoverflow.com/questions/3677557/is-bulk-insert-atomic
        $new_tags = $this->tagIdsByTaglist($taglist); 

        return $new_tags;
    }

    /*
        Revert all modification
    */
    public function rollback() {
        if (!$this->changed())
            return;

        $this->tags = array('new' => array(), 'del' => array());
        $this->inited = false;
    }

    /*
        Filter tag groups by tags, sorted in order, that most
        relevant group comes first

        @return array (tag => relevancy, tag => relevancy)
    */
    public static function filterGroupsByTags(array $taglist) {
        $db = DB::getInstance()->Start_Connection('mysql');

        $query = '
            SELECT id, relevancy
              FROM (
                    SELECT tag_group_id AS id, COUNT(tg.tag_id) AS relevancy
                      FROM tags_group tg
                     INNER
                      JOIN tags t
                        ON t.tag_id = tg.tag_id
                     WHERE t.tag IN (#taglist#)
                     GROUP
                        BY tag_group_id
                   ) i
             ORDER 
                BY relevancy DESC';

        $groups = array();
        $result = $db->query($query, array('taglist' => $taglist));
        if ($result->num_rows)
            while ($res = $result->fetch_assoc())
                $groups[ intval($res['id']) ] = $res['relevancy'];

        return $groups;
    }
};
