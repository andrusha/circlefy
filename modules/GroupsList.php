<?php
/*
    Groups collection, all methods to create group
    collection or traverse it we should place here

    It also chaches different results
*/
class GroupsList extends Collection {
    /*
        Uses temporary caches user groups,
        may be handy in a lot types of queries

        Use with caution
    */
    private static $cache = array();

    /*
        You should use static initializers instead of
        constructions by yourself
    */
    protected function __construct(array $groups) {
        parent::__construct($groups, 'GroupsList');
    }

    /*
        @return Group
    */
    public function lastOne() {
        end($this->data);
        return current($this->data);
    }

    /*
        Returns a list of groups, sorted by relevancy,
        after keyword search

        If user is set, then filter by current user groups

        @returns array(groups, matched_keywords)
    */
    public static function byKeywords(array $keywords, User $user = null) {
        if (empty($keywords))
            return array(new GroupsList(array()), array());

        $db = DB::getInstance()->Start_Connection('mysql');
        
        $tagGroups = Tags::filterGroupsByTags($keywords, 2);
       
        //even if tag groups empty, try to fetch groups
        //by symbol matching
        if (empty($tagGroups)) {
            $where = '(symbol IN (#keywords#) OR gname IN (#keywords#))';
            $params = array('keywords' => $keywords);
        } else {
            $where = '(tag_group_id IN (#tagidlist#)
                       OR symbol IN (#keywords#)
                       OR gname IN (#keywords#))';
            $params = array('tagidlist' => array_keys($tagGroups), 'keywords' => $keywords);
        }

        $matched_keywords = array();

        //filter current user groups &
        //add his tags+symbols to matched_keywords
        if ($user !== null) {
            $gids = $tagGroupIDs = array();
            foreach(GroupsList::byUser($user) as $group) {
                $gids[] = $group->gid;
                $info = $group->info;
                $tagGroupIDs[] = intval($info['tag_group_id']);
                $matched_keywords[] = trim($info['symbol']);
                $matched_keywords[] = trim($info['gname']);
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
            SELECT gid, gname, symbol, tag_group_id, pic_100 AS pic_big, pic_36 AS pic_small
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
                    $matched_keywords[] = trim($res['symbol']);
                    $matched_keywords[] = trim($res['gname']);
                }
            }
        $matched_keywords = array_unique($matched_keywords);
        $groups = new GroupsList($groups);

        return array($groups, $matched_keywords);
    }

    /*
        Search groups by params

        @param str $type
            byUser | byGroup | bySymbol

        @param array $params
            allowed keys:
                uid, gid, symbol

        @param int $options
            allowed options:
                G_ONLINE_COUNT | G_TAPS_COUNT | G_USERS_COUNT
                G_EXTENDED     | G_RESPONSES_COUNT

        @return GroupsList
    */
    public static function search($type, array $params, $options = 0)  {
        $db = DB::getInstance()->Start_Connection('mysql');

        $join   = $group = array();
        $where  = '';
        $fields = array('g.gid', 'g.symbol', 'g.gname', 'g.tag_group_id');

        $joins = array(
            'members'  => 'INNER JOIN group_members gm ON g.gid = gm.gid',
            'members2' => 'INNER JOIN group_members gm2 ON g.gid = gm2.gid',
            'meta'     => 'LEFT  JOIN special_chat_meta scm ON scm.gid = g.gid',
            'online'   => 'LEFT JOIN GROUP_ONLINE AS go ON go.gid = g.gid');

        switch ($type) {
            case 'byUser':
                $fields[] = 'gm.admin';
                $join[]   = 'members';
                $where    = 'gm.uid = #uid#';
                break;

            case 'byGroup':
                $where = 'g.gid = #gid#';
                break;

            case 'bySymbol':
                $where = 'g.symbol = #symbol#';
                break;
        }

        if ($options & G_EXTENDED) {
            $fields = array_merge($fields,
                array('g.descr AS topic', 'g.connected', 'g.pic_100',
                      'g.pic_36', 'g.favicon', 'g.private', 'g.invite_only',
                      'g.invite_priv'));
        }

        //online count option
        if ($options & G_ONLINE_COUNT) {
           $fields[] = 'go.count';
           $join[]   = 'online';
        }

        //taps count would ALWAYS join first
        //IMPORTANT! check conflicts before adding new
        if ($options & G_TAPS_COUNT) {
            $fields[] = 'COUNT(g.gid) AS taps_count';
            $join[]   = 'meta';
            $group[]  = 'g.gid';
        }

        //we can freely join and group by table if there
        //would be only one join
        if (($options & G_USERS_COUNT) && !($options & G_TAPS_COUNT)) {
            $fields[] = 'COUNT(g.gid) AS members_count';
            $join[]   = 'members2';
            $groups[] = 'g.gid';
        }

        $fields = implode(', ', array_unique($fields));
        $join   = implode("\n", array_intersect_key($joins, array_flip(array_unique($join))));
        $group  = array_unique($group);
        if (count($group) > 1)
            throw new SQLException('Combinator dont know how to handle multiple GROUP BY');
        elseif (count($group) == 1)
            $group = 'GROUP BY '.current($group);
        else
            $group = '';

        $query = "
            SELECT {$fields}
              FROM groups g
               {$join}
             WHERE {$where}
             {$group}";

        $groups = array();
        $result = $db->query($query, $params);
        if ($result->num_rows)
            while ($res = $result->fetch_assoc()) {
                $data = array('info' => $res, 'gid' => intval($res['gid']));
                $groups[] = new Group($data);
            }


        $groups = new GroupsList($groups);

        //if joins conflict, resolve it
        if (($options & G_USERS_COUNT) && ($options & G_TAPS_COUNT))
            $groups->getUsersCount();

        if ($options & G_RESPONSES_COUNT) 
            $groups->getResponsesCount();

        self::$cache['users'][$user->uid][$options] = $groups;
        return $groups;
    }

    /*
        Fetch groups of specified user

        @param User $user
        @param int  $options
        @param bool $cached 

        @return GroupsList 
    */
    public static function byUser(User $user, $options = 0, $cached = true) {
        if (isset(self::$cache['users'][$user->uid][$options]))
            return self::$cache['users'][$user->uid][$options];

        $groups = self::search('byUser', array('uid' => $user->uid), $options);
        
        self::$cache['users'][$user->uid][$options] = $groups;
        return $groups;
    }

    /*
        Updates provided group list, adding to each group
        info about members count
    */
    public function getUsersCount() {
        if (empty($this->data))
            return $this;

        $db = DB::getInstance()->Start_Connection('mysql');
        $gids = $this->filter('gid');

        $query = '
            SELECT gid, COUNT(gid) AS members_count
              FROM group_members
             WHERE gid IN (#gids#)
             GROUP
                BY gid';

        $counts = array();
        $result = $db->query($query, array('gids' => $gids));
        if ($result->num_rows)
            while($res = $result->fetch_assoc())
                $counts[ intval($res['gid']) ] = intval($res['members_count']);

        $this->joinDataById($counts, 'members_count', 0);

        return $this;
    }

    public function getResponsesCount() {
        if (empty($this->data))
            return $this;

        $db = DB::getInstance()->Start_Connection('mysql');
        $gids = $this->filter('gid');

        $query = "
            SELECT sm.gid, COUNT(c.mid) AS responses_count
              FROM special_chat_meta AS sm 
             INNER
              JOIN chat AS c
                ON c.cid = sm.mid
             WHERE sm.gid IN (#gids#)
             GROUP
                BY sm.gid";

        $counts = array();
        $result = $db->query($query, array('gids' => $gids));
        if ($result->num_rows)
            while($res = $result->fetch_assoc())
                $counts[ intval($res['gid']) ] = intval($res['responses_count']);

        $this->joinDataById($counts, 'responses_count', 0);

        return $this;
    }

    private function joinDataById(array $data, $name, $default = 0) {
        foreach ($this->data as &$group) {
            if (isset($data[$group->gid]))
                $group->set($name, $data[$group->gid]);
            else
                $group->set($name, $default);
        }

        return $this;
    }

    /*
        Creates a bunch of groups from facebook information about them
        Returns array of newly created group objects

        @returns array(Group, ...)
    */
    public static function bulkCreateFacebook(User $creator, array $fgids) {
        if (empty($fgids))
            return new GroupsList(array());

        $fb = new Facebook();

        $groups = array();
        foreach ($fb->bulkInfo($fgids) as $fgid => $info) {
            if (strlen($info['name']) > 35)
                continue;
            $descr   = FuncLib::makePreview(strip_tags($info['description']), 250);
            $symbol  = FuncLib::makeGName($info['name']);
            $gname   = FuncLib::makePreview($info['name'], 250);
            $tags    = FuncLib::extractTags($info['name'], $info['description'], $info['category']);
            $pic_url = 'http://graph.facebook.com/'.$fgid.'/picture?type=large';
            $picture = Images::fetchAndMake(D_GROUP_PIC_PATH, $pic_url, "$fgid.jpg");
            $links   = isset($info['link']) ? explode('\n', $info['link']) : array();
            $favicon = !empty($links) ? Images::getFavicon($links[0], D_GROUP_PIC_PATH."/fav_$fgid.ico") : null;

            $group = Group::create($creator, $gname, $symbol, $descr, $tags, $picture, $favicon);
            if ($group !== false)
                $groups[] = $group;
        }

        $groups = new GroupsList($groups);
        return $groups;
    }

    /*
        Make every group online
        (no need, python server do that)

        @param $groups array (Group)
        @param $online bool
    */
    public static function makeOnline(GroupsList $groups, $online = true) {
        if (empty($groups))
            return false;

        $db = DB::getInstance()->Start_Connection('mysql');

        $gids = $groups->filter('gid');

        $status = $online ? ' + 1 ' : ' - 1 ';
        $query = "
            UPDATE GROUP_ONLINE go
               SET go.count = go.count {$status}
             WHERE go.gid IN (#gids#)";

        $db->query($query, array('gids' => $gids));

        return $db->affected_rows == count($gids);
    }

};
