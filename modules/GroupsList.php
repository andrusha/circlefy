<?php
/*
    Groups collection, all methods to create group
    collection or traverse it we should place here

    It also chaches different results
*/
class GroupsList extends Collection {
    public function __construct(array $data) {
        parent::__construct($data);
    }

    /*
        Uses temporary caches user groups,
        may be handy in a lot types of queries

        Use with caution
    */
    private static $cache = array();

    public static function void() {
        return new GroupsList(array());
    }

    public static function fromIds(array $ids) {
        return new GroupsList(array_map(
            function ($id) { return new Group($id); },
            $ids));
    }

    /*
        Make user member of list of groups

        TODO!!!!!!!!!!!!!
    */
    public function bulkJoin(User $user, $perm = 'moderator') {
        if (empty($this->data))
            return $this;

        $db = DB::getInstance();

        //prepare insert arrays from groups and user
        foreach ($this->data as $group)
            if ($group->id)
                $list[] = array($group->id, $user->id, Group::$permissions[$perm]);

        $query = "
            INSERT
              INTO group_members (group_id, user_id, permission)
            VALUES  #values#
                ON DUPLICATE KEY
            UPDATE permission = ".Group::$permissions[$perm];
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
        
        $tagGroups = Tags::filterGroupsByTags($keywords, KEYWORDS_TRASHOLD);
       
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
            byUser   - all groups joned by user
            byGroup  - group by ID
            bySymbol - fetch group by symbol
            byFbIDs  - groups by their Facebook ID
            like     - search on group name by LIKE
            byUserAndLike - performs like search within followed groups
            childs   - returns all child groups
            parent   - returns closest parent group
            byIds    - return a groups by ids

        @param array $params
            uid    - user id
            gid    - group id
            symbol - group symbol
            fbids  - a list of facebook group (likes, etc) ids
            limit  - how many lines fetch from db
            search - search string for group
            depth  - used in group_relations queries
            ids    - list of ids

        @param int   $options
            G_TAPS_COUNT      - fetch number of messages in group
            G_USERS_COUNT     - joined users
            G_RESPONSES_COUNT - total responses count
            G_JUST_ID         - fetch only group id

        @return GroupsList
    */
    public static function search($type, array $params, $options = 0)  {
        $db = DB::getInstance();

        $join   = $group = array();
        $where  = $limit = '';
        if ($options & G_JUST_ID)
            $fields = array('g.id');
        else
            $fields = FuncLib::addPrefix('g.', Group::$fields);

        $joins = array(
            'members'   => 'INNER JOIN group_members gm  ON g.id = gm.group_id',
            'members2'  => 'LEFT  JOIN group_members gm2 ON g.id = gm2.group_id',
            'messages'  => 'LEFT  JOIN message m         ON m.group_id = g.id',
            'relations' => 'INNER JOIN group_relations gr ON g.id = gr.descendant',
            'relationsA'=> 'INNER JOIN group_relations gr ON g.id = gr.ancestor');

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

            case 'byFbIDs':
                $where = 'g.fb_id IN (#fbids#)';
                break;

            case 'like':
                $where = 'g.name LIKE #search#';
                break;

            case 'byUserAndLike':
                $fields[] = 'gm.permission';
                $join[]   = 'members';
                $where    = 'gm.user_id = #uid# AND g.name LIKE #search#';
                break;

            case 'childs':
                $join[] = 'relations';
                $where  = 'gr.ancestor = #gid# AND gr.ancestor <> gr.descendant AND gr.depth = #depth#';
                break;

            case 'parent':
                $join[] = 'relationsA';
                $where  = 'gr.descendant = #gid# AND gr.ancestor <> gr.descendant AND gr.depth = #depth#';
                $limit  = 'LIMIT 1';
                break;

            case 'byIds':
                $where = 'g.id IN (#ids#)';
                break;
        }

        //we can freely join and group by table if there
        //would be only one join
        //IMPORTANT! check conflicts before adding new option
        if ($options & G_USERS_COUNT) {
            $fields[] = 'COUNT(g.id) AS members_count';
            $join[]   = 'members2';
            $group[] = 'g.id';
        }

        //taps count would ALWAYS join first
        if (($options & G_TAPS_COUNT) && !($options & G_USERS_COUNT)) {
            $fields[] = 'COUNT(g.id) AS messages_count';
            $join[]   = 'messages';
            $group[]  = 'g.id';
        }

        if (isset($params['limit']))
            $limit = 'LIMIT 0, #limit#';

        $fields = implode(', ', array_unique($fields));
        $join   = implode("\n", array_intersect_key($joins, array_flip(array_unique($join))));

        $group  = array_unique($group);
        if (count($group) > 1)
            throw new SQLException('Combinator dont know how to handle multiple GROUP BY');
        else if (count($group) == 1)
            $group = 'GROUP BY '.$group[0];
        else
            $group = '';

        $query = "
            SELECT {$fields}
              FROM `group` g
               {$join}
             WHERE {$where}
             {$group}
             {$limit}";
        $groups = array();
        $result = $db->query($query, $params);
        if ($result->num_rows)
            while ($res = $result->fetch_assoc()) {
                if ($res['id'] === null)
                    continue;

                $groups[] = new Group($res);
            }

        $groups = new GroupsList($groups);

        //if joins conflict, resolve it
        if (($options & G_USERS_COUNT) && ($options & G_TAPS_COUNT))
            $groups->getTapsCount();

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
        
        self::$cache['users'][$user->id][$options] = $groups;
        return $groups;
    }

    /*
        Updates provided group list, adding to each group
        info about members count

        //TODO: pack taps & responses count in _1_ query
    */
    public function getTapsCount() {
        if (empty($this->data))
            return $this;

        $db = DB::getInstance();
        $gids = $this->filter('id');

        $query = '
            SELECT group_id, COUNT(group_id) AS messages_count
              FROM message
             WHERE group_id IN (#gids#)
             GROUP
                BY group_id';

        $counts = array();
        $result = $db->query($query, array('gids' => $gids));
        if ($result->num_rows)
            while($res = $result->fetch_assoc())
                $counts[ intval($res['group_id']) ] = intval($res['messages_count']);

        $this->joinDataById($counts, 'messages_count', 0);

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
        Create a lots of groups from info with Facebook ID

        @return GroupsList
    */
    public static function bulkCreate(array $list) {
        if (empty($list))
            return new GroupsList(array());

        $db = DB::getInstance();
        $db->startTransaction();
        try {
            $query = "INSERT INTO `group` (fb_id, symbol, name, descr, type) VALUES #values#";
            $db->listInsert($query, $list);
            $fbids = array_map(function ($x) { return $x[0]; }, $list);
            $groups = GroupsList::search('byFbIDs', array('fbids' => $fbids));

            GroupRelations::init($groups); 

            $db->commit();
        } catch (SQLException $e) {
            $db->rollback();
            throw $e;
        }

        return $groups;
    }

    /*
        Creates a bunch of groups from facebook information about them
        Returns array of newly created group objects

        @returns array(Group, ...)
    */
    public static function bulkCreateFacebook(User $creator, array $fgids) {
        if (empty($fgids))
            return new GroupsList(array());

        $typeRouter = function ($info) {
            $map = array('school'   => array('school', 'university', 'institute'),
                         'company'  => array('company', 'organization'),
                         'location' => array('city'));

            if ($info['type'] == 'event')
                return Group::$types['event'];

            $cat = strtolower($info['category']);
            foreach ($map as $type => $names)
                if (in_array($cat, $names))
                    return Group::$types[$type];

            return Group::$types['group'];
        };

        $groupFilter = function ($info) {
            $name = $info['name'];

            if (strlen($name) > 35)
                return false;
   
            // filter spam-groups
            $banned = array('i', 'me', 'my', 'you', 'should', 'what', 'could');
            $words  = explode(' ', strtolower($name));
            if (count(array_intersect($words, $banned)) > 1)
                return false;

            return true;
        };

        $fb = new Facebook();

        // I. Fetch info from FB, prepare it for bulk insert
        $FBinfo = $fb->bulkInfo($fgids);
        $insert = array();
        foreach ($FBinfo as $fgid => $info) {
            if (!$groupFilter($info))
                continue;

            if (!empty($info['mission']))
                $info['description'] = $info['mission'];
            $descr   = FuncLib::makePreview(strip_tags($info['description']), 250);
            $symbol  = FuncLib::makeSymbol($info['name'], 64);
            $gname   = FuncLib::makePreview($info['name'], 80);
            $type    = $typeRouter($info); 

            $insert[] = array($fgid, $symbol, $gname, $descr, $type);
        }

        // II. Insert formatted info about group into DB
        $groups = GroupsList::bulkCreate($insert);

        // III. Download group images & favicons, create tags for each group
        foreach ($groups as $g) {
            $info    = $FBinfo[$g->fb_id];

            $tags    = FuncLib::extractTags($info['name'], $info['description'], $info['category'], $info['mission']);

            try {
                $pic_url = 'http://graph.facebook.com/'.$g->fb_id.'/picture?type=large';
                $picture = Images::fetchAndMake(GROUP_PIC_PATH, $pic_url, $g->id.'.jpg');
            } catch (NetworkException $e) {
                $g->setDefaultAvatar();
            } catch (ImagickException $e) {
                if (DEBUG)
                    FirePHP::getInstance(true)->trace($e);

                $g->setDefaultAvatar();
            }

            //TODO: make bulk tags addition
            $g->tags->addTags($tags);
            $g->commit();
        }

        // IV. Make creator a groups admin
        // FIXIT: it actually makes no sense at all
        //$groups->bulkJoin($creator, 'admin');

        return $groups;
    }

};
