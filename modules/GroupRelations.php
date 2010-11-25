<?php

/*
    Not actually a model, a useable piece of code related to Groups

    We implementing here Clojure table with cached depth

    See: http://www.slideshare.net/billkarwin/models-for-hierarchical-data?from=fblanding [starting slide 33]
*/
class GroupRelations {
    const table = 'group_relations';

    private static $depthCache = array();

    /*
        Initialize newly created groups in relations table

        @param Group|GroupList $group
    */
    public static function init($group) {
        if (!($group instanceof Group || $group instanceof GroupsList))
            throw new InvalidArgumentException('$group should be instance of Group or GroupsList');

        $db = DB::getInstance();
        if ($group instanceof Group)
            $group = new GroupsList(array($group->id => $group));

        $db->startTransaction();
        try {
            foreach ($group as $g)
                $db->lazyInsert(self::table,
                    array('ancestor'   => $g->id,
                          'descendant' => $g->id,
                          'depth'      => 0));

            $db->commitLazyInsert(self::table);
        } catch (SQLException $e) {
            $db->rollback();
            throw $e;
        }

        $db->commit();
    }

    /*
        Making parent relations between groups
    */
    public static function link(Group $children, Group $parent) {
        self::unlink($children);
        self::updateDepth($children, $parent);

        $query = "INSERT INTO `".self::table."` (ancestor, descendant, depth) 
                  SELECT t2.ancestor AS ancestor, t.descendant, t.depth 
                    FROM ".self::table." t
                   INNER
                    JOIN ".self::table." t2
                      ON t2.descendant = #pid#
                   WHERE t.ancestor    = #gid#";
        
        DB::getInstance()->query($query, array('pid' => $parent->id, 'gid' => $children->id));
    }

    /*
        Delete all links to group and reset depth counter for it childrens
    */
    public static function unlink(Group $children) {
        $parent = self::getClosestParent($children, false, false);
        if ($parent->id === null)
            return;

        self::updateDepth($children, $parent, false);

        $q = "DELETE FROM `".self::table."` WHERE descendant = #gid# AND ancestor <> descendant";
        DB::getInstance()->query($q, array('gid' => $children->id));
    }

    /*
        Reduce or increase depth of node and all it childs
    */
    private static function updateDepth(Group $children, Group $parent, $plus = true) {
        $sign = $plus ? '+' : '-';

        $depth = self::getDepth($parent);
        $query = "UPDATE `".self::table."` SET depth = depth $sign #depth# $sign 1 WHERE ancestor = #gid#";
    
        DB::getInstance()->query($query, array('gid' => $children->id, 'depth' => $depth));
    }

    /*
        @return int depth
    */
    public static function getDepth(Group $g, $cache = false) {
        if ($cache && isset(self::$depthCache[$g->id]))
            return self::$depthCache[$g->id];

        $db = DB::getInstance();
       
        $q = "SELECT depth FROM `".self::table."` WHERE ancestor = #gid# AND ancestor = descendant";
        $res = $db->query($q, array('gid' => $g->id));
        if (!$res->num_rows)
            throw new LogicException("Group relations haven't been initialized for `$g->id` group");

        $res = $res->fetch_assoc();
        $depth = intval($res['depth']);
        self::$depthCache[$g->id] = $depth;

        return $depth;
    }

    /*
        @return Group
    */
    public static function getClosestParent(Group $g, $info = true, $cached = true) {
        $depth = self::getDepth($g, $cached);
        
        $group = GroupsList::search('parent', 
                                    array('gid' => $g->id, 'depth' => $depth),
                                    ($info ? 0 : G_JUST_ID))
                           ->getFirst();

        if (!$group)
            return new Group(null);

        return $group;
    }

    /*
        @return GroupsList
    */
    public static function getChilds(Group $g) {
        $depth  = self::getDepth($g, true);

        $groups = GroupsList::search('childs', 
                                    array('gid' => $g->id, 'depth' => $depth, 'limit' => 4));

        return $groups;
    }

    /*
        @return GroupsList
    */
    public static function getSiblings(Group $g) {
        $parent = self::getClosestParent($g, false);
        if ($parent->id === null)
            return GroupsList::getEmpty();
        
        return self::getChilds($parent);
    }

};
