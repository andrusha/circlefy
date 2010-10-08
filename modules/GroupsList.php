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
        Make user member of list of groups
    */
    public function bulkJoin(User $user) {
        $db = DB::getInstance();

        //prepare insert arrays from groups and user
        $list = array_map(function ($group) use ($user) {
                return array($group->id, $user->id, Group::$permissions['user']);
            }, $this->data);

        $query = "
            INSERT
              INTO group_members (group_id, user_id, permission)
            VALUES  #values#
                ON DUPLICATE KEY
            UPDATE permission = VALUES(permission)";
        $db->listInsert($query, $list);

        return $this;
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

        $db = DB::getInstance();
        
        $tagGroups = Tags::filterGroupsByTags($keywords, 2);
       
        //even if tag groups empty, try to fetch groups
        //by symbol matching
        if (empty($tagGroups)) {
            $where = '(symbol IN (#keywords#) OR name IN (#keywords#))';
            $params = array('keywords' => $keywords);
        } else {
            $where = '(tags_group_id IN (#tagidlist#)
                    OR symbol        IN (#keywords#)
                    OR name          IN (#keywords#))';
            $params = array('tagidlist' => array_keys($tagGroups), 'keywords' => $keywords);
        }

        $matched_keywords = array();

        //filter current user groups &
        //add his tags+symbols to matched_keywords
        if ($user !== null) {
            $gids = $tagGroupIDs = array();
            foreach(GroupsList::byUser($user) as $group) {
                $gids[] = $group->id;
                $tagGroupIDs[] = $group->tags_group_id;
                $matched_keywords[] = trim($group->symbol);
                $matched_keywords[] = trim($group->name);
            }

            if (!empty($gids)) {
                $where .= ' AND id NOT IN (#gids#) ';
                $params = array_merge(array('gids' => $gids), $params);
            }

            //FIXIT: I'm not sure about it, better not to do
            //cuz 'python' tag may have not only python group
            //$matched_keywords = array_merge(
            //    $matched_keywords, Tags::getTagsByGroups($tagGroupIDs));
        }

        $query = "
            SELECT ".implode(', ', Group::$fields)."
              FROM `group`
             WHERE {$where}
               AND secret = 0";

        $groups = array();
        $result = $db->query($query, $params);
        if ($result->num_rows)
            while ($res = $result->fetch_assoc()) {
                $groups[] = new Group($res);

                $tgid = intval($res['tags_group_id']);
                //if matched by keyword, then add them to matched_keywords array
                //else, add symbol
                if (array_key_exists($tgid, $tagGroups)) {
                    $matched_keywords = array_merge($matched_keywords,
                        explode(', ', $tagGroups[$tgid][1]));
                } else {
                    $matched_keywords[] = trim($res['symbol']);
                    $matched_keywords[] = trim($res['name']);
                }
            }
        $matched_keywords = array_unique($matched_keywords);
        $groups = new GroupsList($groups);

        return array($groups, $matched_keywords);
    }

    /*
        Search groups by params

        @param str   $type
            byUser | byGroup | bySymbol

        @param array $params
            #uid# | #gid# | #symbol#

        @param int   $options
            G_TAPS_COUNT | G_USERS_COUNT | G_RESPONSES_COUNT | G_JUST_ID

        @return GroupsList
    */
    public static function search($type, array $params, $options = 0)  {
        $db = DB::getInstance();

        $join   = $group = array();
        $where  = '';
        if ($options & G_JUST_ID)
            $fields = array('g.id');
        else
            $fields = FuncLib::addPrefix('g.', Group::$fields);

        $joins = array(
            'members'  => 'INNER JOIN group_members gm  ON g.id = gm.group_id',
            'members2' => 'INNER JOIN group_members gm2 ON g.id = gm2.group_id',
            'messages' => 'LEFT  JOIN message m         ON m.group_id = g.id');

        switch ($type) {
            case 'byUser':
                $fields[] = 'gm.permission';
                $join[]   = 'members';
                $where    = 'gm.user_id = #uid#';
                break;

            case 'byGroup':
                $where = 'g.id = #gid#';
                break;

            case 'bySymbol':
                $where = 'g.symbol = #symbol#';
                break;
        }

        //taps count would ALWAYS join first
        //IMPORTANT! check conflicts before adding new
        if ($options & G_TAPS_COUNT) {
            $fields[] = 'COUNT(g.id) AS taps_count';
            $join[]   = 'messages';
            $group[]  = 'g.id';
        }

        //we can freely join and group by table if there
        //would be only one join
        if (($options & G_USERS_COUNT) && !($options & G_TAPS_COUNT)) {
            $fields[] = 'COUNT(g.id) AS members_count';
            $join[]   = 'members2';
            $groups[] = 'g.id';
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
              FROM `group` g
               {$join}
             WHERE {$where}
             {$group}";

        $groups = array();
        $result = $db->query($query, $params);
        if ($result->num_rows)
            while ($res = $result->fetch_assoc())
                $groups[] = new Group($res);

        $groups = new GroupsList($groups);

        //if joins conflict, resolve it
        if (($options & G_USERS_COUNT) && ($options & G_TAPS_COUNT))
            $groups->getUsersCount();

        if ($options & G_RESPONSES_COUNT) 
            $groups->getResponsesCount();

        self::$cache['users'][$user->id][$options] = $groups;
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
        if (isset(self::$cache['users'][$user->id][$options]) && $cached)
            return self::$cache['users'][$user->id][$options];

        $groups = self::search('byUser', array('uid' => $user->id), $options);
        
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

        $db = DB::getInstance();
        $gids = $this->filter('id');

        $query = '
            SELECT group_id, COUNT(group_id) AS members_count
              FROM group_members
             WHERE group_id IN (#gids#)
             GROUP
                BY group_id';

        $counts = array();
        $result = $db->query($query, array('gids' => $gids));
        if ($result->num_rows)
            while($res = $result->fetch_assoc())
                $counts[ intval($res['group_id']) ] = intval($res['members_count']);

        $this->joinDataById($counts, 'members_count', 0);

        return $this;
    }

    public function getResponsesCount() {
        if (empty($this->data))
            return $this;

        $db = DB::getInstance();
        $gids = $this->filter('id');

        $query = "
            SELECT m.group_id, COUNT(m.id) AS responses_count
              FROM message AS m
             INNER
              JOIN reply AS r
                ON r.message_id = m.id
             WHERE m.group_id IN (#gids#)
             GROUP
                BY m.group_id";

        $counts = array();
        $result = $db->query($query, array('gids' => $gids));
        if ($result->num_rows)
            while($res = $result->fetch_assoc())
                $counts[ intval($res['group_id']) ] = intval($res['responses_count']);

        $this->joinDataById($counts, 'responses_count', 0);

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
            $symbol  = FuncLib::makeSymbol($info['name'], 64);
            $gname   = FuncLib::makePreview($info['name'], 128);
            $tags    = FuncLib::extractTags($info['name'], $info['description'], $info['category']);

            $group    = Group::create($creator, new Group(), $gname, $symbol, $descr, $tags);
            $groups[] = $group;

            $id = $group->id;
            $pic_url = 'http://graph.facebook.com/'.$id.'/picture?type=large';
            $picture = Images::fetchAndMake(D_GROUP_PIC_PATH, $pic_url, "$id.jpg");
            $links   = isset($info['link']) ? explode('\n', $info['link']) : array();
            $favicon = !empty($links) ? Images::getFavicon($links[0], D_GROUP_PIC_PATH."/fav_$id.ico") : null;
        }

        $groups = new GroupsList($groups);
        return $groups;
    }
};
