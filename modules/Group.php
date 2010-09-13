<?php
abstract class GroupException extends Exception {};
class GroupInitializeException extends GroupException {};
class GroupDataException extends GroupException {};

/*
    All things related to groups (channels)
*/
class Group extends BaseModel {
    /*
        A list of tags, on what current group belongs

        @instance Tags
    */
    private $taglist = null;

    /*
        array with some useful group data
    */
    private $data = array();

    private $gid = null;

    /*
        gid =
            null for new group creating
            int to get info from existing group
            array to auto-initialize group
    */
    public function __construct($gid = null) {
        parent::__construct();
    
        if (is_array($gid)) {
            $this->data = $gid;
            $this->gid = intval($gid['gid']);
        } else {
            $this->gid = $gid;
        }
    }

    /*
        Fancy interface for groups, you should set or
        group id, before fetching any info

        $object->info    = returns group info
               ->members = returns all group members
               ->tags    = returns group tags
    */
    public function __get($key) {
        if (array_key_exists($key, $this->data)) {
            return $this->data[$key];
        } else if (method_exists($this, 'get'.ucfirst($key))) {
            if ($this->gid === null)
                throw new GroupInitializeException('You should set group id or create new group before fetching data');

            $name = 'get'.ucfirst($key);
            $this->data[$key] = $this->$name();

            return $this->data[$key];
        } else if ($key == 'tags') {
            if ($this->taglist === null) {
                $tagId = null;
                if (!empty($this->data['info']['tag_group_id']))
                    $tagId = intval($this->data['info']['tag_group_id']);
                $this->taglist = new Tags($tagId);
            }

            return $this->taglist;
        } else if ($key == 'gid') {
            return $this->gid;
        }

        throw new GroupDataException("Can not find any group info related to '$key'");
    }

    /*
        Kinda save changes
    */
    public function commit() {
        if ($this->taglist !== null) {
           $this->taglist->commit();
           $tgid = $this->taglist->getGroupId();
           $this->updateTagId($tgid);
        }
    }

    private function updateTagId($tgid) {
        $query = "
            UPDATE groups
               SET tag_group_id = #tgid#
             WHERE gid = #gid#
             LIMIT 1";
        $this->db->query($query, 
            array('gid' => $this->gid, 'tgid' => $tgid));

        return $this->db->affected_rows == 1;
    }

    /*
        Creates new group from group symbol
    */
    public static function fromSymbol($symbol) {
        $db = DB::getInstance()->Start_Connection('mysql');

        $query = "
            SELECT gid
              FROM groups
             WHERE symbol = #symbol#
             LIMIT 1";

        $result = $db->query($query, array('symbol' => $symbol));
        if ($result->num_rows) {
            $result = $result->fetch_assoc();
            $gid = intval($result['gid']);

            return new Group($gid);
        }

        return null;
    }

    /*
        Returns a list of groups, sorted by relevancy,
        after keyword search

        If user is set, then filter by current user groups

        @returns array(groups, matched_keywords)
    */
    public static function listByKeywords(array $keywords, User $user = null) {
        if (empty($keywords))
            return array();

        $db = DB::getInstance()->Start_Connection('mysql');
        
        $tagGroups = Tags::filterGroupsByTags($keywords, 2);
       
        //even if tag groups empty, try to fetch groups
        //by symbol matching
        if (empty($tagGroups)) {
            $where = 'symbol IN (#keywords#)';
            $params = array('keywords' => $keywords);
        } else {
            $where = '(tag_group_id IN (#tagidlist#)
                       OR symbol IN (#keywords#))';
            $params = array('tagidlist' => array_keys($tagGroups), 'keywords' => $keywords);
        }

        $matched_keywords = array();

        //filter current user groups &
        //add his tags+symbols to matched_keywords
        if ($user !== null) {
            $gids = $tagGroupIDs = array();
            foreach(Group::listByUser($user) as $group) {
                $gids[] = $group->gid;
                $info = $group->info;
                $tagGroupIDs[] = intval($info['tag_group_id']);
                $matched_keywords[] = $info['symbol'];
            }

            if (!empty($gids)) {
                $where .= ' AND gid NOT IN (#gids#) ';
                $params = array_merge(array('gids' => $gids), $params);
            }

            //FIXIT: I'm not sure about it, better not to do
            //cuz 'python' tag may have not only python group
            //$matched_keywords = array_merge(
            //    $matched_keywords, Tags::getTagsByGroups($tagGroupIDs));
        }

        $query = "
            SELECT gid, gname, symbol, tag_group_id
              FROM groups
             WHERE {$where}
               AND private = 0";

        $groups = array();
        $result = $db->query($query, $params);
        if ($result->num_rows)
            while ($res = $result->fetch_assoc()) {
                $data = array('info' => $res, 'gid' => intval($res['gid']));
                $groups[] = new Group($data);

                $tgid = intval($res['tag_group_id']);
                //if matched by keyword, then add them to matched_keywords array
                //else, add symbol
                if (array_key_exists($tgid, $tagGroups)) {
                    $matched_keywords = array_merge($matched_keywords,
                        explode(', ', $tagGroups[$tgid][1]));
                } else {
                    $matched_keywords[] = $res['symbol'];
                }
            }
        $matched_keywords = array_unique($matched_keywords);

        return array($groups, $matched_keywords);
    }

    /*
        Fetch groups of specified user

        @params $user User user, groups belongs to him we fetching
        @params $extended bool fetch or not additional parameters
        @params $onlyGid bool return only group ids, or group objects

        @returns array (Group | int)
    */
    public static function listByUser(User $user, $onlyGid = false, $extended = false) {
        $db = DB::getInstance()->Start_Connection('mysql');

        if ($extended) {
           $fields = ', go.count, g.descr AS topic, g.connected, g.pic_36, g.favicon, gm.admin';
           $join = 'LEFT 
                    JOIN GROUP_ONLINE AS go
                      ON go.gid = gm.gid';
        } else {
            $fields = $join = '';
        }

        $query = "
            SELECT g.gid, g.symbol, g.gname, g.tag_group_id {$fields}
              FROM groups g
             INNER
              JOIN group_members gm
                ON g.gid = gm.gid
               {$join}
             WHERE gm.uid = #uid#";
        $groups = array();
        $result = $db->query($query, array('uid' => $user->uid));
        if ($result->num_rows)
            while ($res = $result->fetch_assoc()) {
                if ($onlyGid) {
                    $groups[] = intval($res['gid']);
                } else {
                    $data = array('info' => $res, 'gid' => intval($res['gid']));
                    $groups[] = new Group($data);
                }
            }

        return $groups;
    }

    /*
        Creates a bunch of groups from facebook information about them
        Returns array of newly created group objects

        @returns array(Group, ...)
    */
    public static function bulkCreateFacebook(User $creator, array $fgids) {
        if (!function_exists('makeGName')) {
            function makeGName($gname, $limit = 64) {
                //make words in Camel Case, if there is words
                if (strpos($gname, ' ') !== false) {
                    $gname = ucwords(strtolower($gname));
                    $gname = str_replace(' ', '-', trim($gname));
                }

                //delete all garbage symbols
                $gname = preg_replace('/[^a-z0-9\-)]*/i', '', $gname);
                
                //if gname length is greater, than DB limit
                //try to make abbreviation of our words,
                //if words at least two
                if (strpos($gname, '-'))
                    while(strlen($gname) > $limit) {
                        $gname = preg_replace('/([A-Z])[a-z0-9]+/', '$1', $gname);
                    }
                
                //if even after abbreveation name is greater
                //our limit, then just cut it
                $gname = substr($gname, 0, $limit);

                return $gname;
            }
        }

        if (!function_exists('extractTags')) {
            function extractTags($gname, $descr, $category) {
                $tags = array();

                //category is tag anyway
                $tags[] = $category;

                //now, let us extract all stuff <b>, <i> & <a> tags
                //this are usually somewhat realted to
                $result = array();
                preg_match_all('/<(?P<tagname>[abi])(?:\s[^>]*?)?>(?P<info>.*?)<\/(?P=tagname)>/i', $descr, $result, PREG_PATTERN_ORDER);
                foreach ($result['info'] as $tag)
                    $tags[] = trim($tag);
                
                $tags = array_unique($tags);
                return $tags;
            }
        }

        if (empty($fgids))
            return array();

        $fb = new Facebook();

        $groups = array();
        foreach ($fgids as $fgid) {
            $info = $fb->getGroupInfo($fgid);
            $descr = Taps::makePreview(strip_tags($info['description']), 250);
            $symbol = Taps::makePreview($info['name'], 250);
            $gname = makeGName($info['name']);
            $tags = extractTags($info['name'], $info['description'], $info['category']);

            //TODO: picture
            
            $group = false;
            $group = Group::create($creator, $gname, $symbol, $descr, $tags);
            if ($group !== false)
                $groups[] = $group;
        }

        return $groups;
    }

    /*
        Yeah, right, it simply creates a new group
        
        @returns Group | bool
    */
    public static function create(User $creator, $gname, $symbol, $descr, array $tags) {
        $db = DB::getInstance()->Start_Connection('mysql');

        $db->startTransaction();
        $ok = true;

        //insert group info into groups
        $query = "
            INSERT
              INTO groups (gname, symbol, gadmin, descr, created)
            VALUES (#gname#, #symbol#, #uid#, #descr#, NOW())";
        $ok = $ok && $db->query($query,
            array('gname' => $gname, 'symbol' => $symbol, 'descr' => $descr,
                  'uid' => $creator->uid));

        $gid = $db->insert_id;

        //make group online
        $query = "
            INSERT
              INTO GROUP_ONLINE (gid)
             VALUES (#gid#)";
        $ok = $ok && $db->query($query, array('gid' => $gid));

        //make creator a group member & admin
        $query = "
            INSERT
              INTO group_members (uid, gid, admin, status)
            VALUES (#uid#, #gid#, 1, 1)";
        $ok = $ok && $db->query($query, array('uid' => $creator->uid, 'gid' => $gid));

        if (!$ok) {
            $db->rollback();
            return false;
        }

        $db->commit();

        //create Group object & add tags to it
        $data = array('info' => array('gid' => $gid, 'gname' => $gname,
            'symbol' => $symbol, 'descr' => $descr),
            'gid' => $gid);
        $group = new Group($data);
        $group->tags->addTags($tags);
        $group->commit();

        return $group;
    }

    /*
        Returns necessary information about group
    */
    public function getInfo() {
        $query = "
            SELECT gname, symbol
              FROM groups
             WHERE gid = #gid#
             LIMIT 1";
        $info = array();
        $result = $this->db->query($query, array('gid' => $this->gid));
        if ($result->num_rows)
            $info = $result->fetch_assoc();

        return $info;
    }

    /*
        Returns group members
        $online = true - online, false - offline, null - whatever
    */
    public function getMembers($online = null) {
        if ($online !== null) {
            $join = "
                INNER JOIN TEMP_ONLINE tmo
                        ON tmo.uid = g.uid";
            $where = " AND tmo.online = ".($online ? 1 : 0)." ";
        }

        $query = "
            SELECT g.uid
              FROM group_members g
                {$join}
             WHERE g.gid = #gid#
                {$where}";

        $users = array();
        $result = $this->db->query($query, array('gid' => $this->gid));
        if ($result->num_rows)
            while($res = $result->fetch_assoc())
                $users[] = intval($res['uid']);

        return $users;
    }

    /*
        Make specified user a group member
    */
    public function join(User $user) {
        $query = "
            INSERT
              INTO group_members (gid, uid, admin, status)
            SELECT #gid# AS gid, #uid# AS uid, (gadmin = #uid#) AS admin, 1 AS status
              FROM groups g
             WHERE g.gid = #gid#
                ON DUPLICATE KEY
            UPDATE status = 1,
                   admin = VALUES(admin)";
        $this->db->query($query, array('gid' => $this->gid, 'uid' => $user->uid));
        $status = $this->db->affected_rows == 1;

        return $status;
    }

    /*
        Make user member of list of groups

        array(Group, Group, ...)
    */
    public static function bulkJoin(User $user, array $groups) {
        $db = DB::getInstance()->Start_Connection('mysql');

        $list = array();
        foreach($groups as $g)
            $list[] = array($g->gid, $user->uid, 1, 0);

        $query = "
            INSERT
              INTO group_members (gid, uid, status, admin)
            VALUES  #values#
                ON DUPLICATE KEY
            UPDATE status = 1";
        $status = $db->listInsert($query, $list);

        return $status;
    }

    /*
        Make every group online

        @param $groups array (Group)
        @param $online bool
    */
    public static function bulkOnline(array $groups, $online = true) {
        if (empty($groups))
            return false;

        $db = DB::getInstance()->Start_Connection('mysql');

        $gids = array();
        foreach ($groups as $g)
            $gids[] = $g->gid;
        $gids = array_unique($gids);

        $status = $online ? ' + 1 ' : ' - 1 ';
        $query = "
            UPDATE GROUP_ONLINE go
               SET go.count = go.count {$status}
             WHERE go.gid IN (#gids#)";

        $db->query($query, array('gids' => $gids));

        return $db->affected_rows == count($gids);
    }

};
