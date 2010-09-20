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
                $matched_keywords[] = $info['symbol'];
                $matched_keywords[] = $info['gname'];
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
                    $matched_keywords[] = $res['gname'];
                }
            }
        $matched_keywords = array_unique($matched_keywords);
        $groups = new GroupsList($groups);

        return array($groups, $matched_keywords);
    }

    /*
        Fetch groups of specified user, it's kinda contructor


        @params $user User user, groups belongs to him we fetching
        @params $options int options to customize returned info

        allowed options:
        G_ONLINE_COUNT, G_TAPS_COUNT, G_USERS_COUNT, G_EXTENDED

        @returns array (Group | int)
    */
    public static function byUser(User $user, $options = 0, $cached = true) {
        if (isset(self::$cache['users'][$user->uid][$options]))
            return self::$cache['users'][$user->uid][$options];

        $db = DB::getInstance()->Start_Connection('mysql');

        $fields = $join = $groups = '';

        if ($options & G_EXTENDED) {
            $fields .= ', g.descr AS topic, g.connected, g.pic_36, g.favicon, gm.admin';
        }

        //online count option
        if ($options & G_ONLINE_COUNT) {
           $fields .= ', go.count';
           $join   .= "LEFT 
                       JOIN GROUP_ONLINE AS go
                         ON go.gid = gm.gid\n";
        }

        //taps count would ALWAYS join first
        //IMPORTANT! check conflicts before adding new
        if ($options & G_TAPS_COUNT) {
            $fields .= ', COUNT(g.gid) AS taps_count';
            $join   .= "LEFT
                        JOIN special_chat_meta scm
                          ON scm.gid = gm.gid\n";
            $groups = 'GROUP BY g.gid';
        }

        //we can freely join and group by table if there
        //would be only one join
        if (($options & G_USERS_COUNT) && !($options & G_TAPS_COUNT)) {
            $fields .= ', COUNT(g.gid) AS members_count';
            $join   .= "INNER
                         JOIN group_members gm2
                           ON gm2.gid = gm.gid\n";
            $groups = 'GROUP BY g.gid';
        }

        $query = "
            SELECT g.gid, g.symbol, g.gname, g.tag_group_id {$fields}
              FROM groups g
             INNER
              JOIN group_members gm
                ON g.gid = gm.gid
               {$join}
             WHERE gm.uid = #uid#
               {$groups}";
        $groups = array();
        $result = $db->query($query, array('uid' => $user->uid));
        if ($result->num_rows)
            while ($res = $result->fetch_assoc()) {
                $data = array('info' => $res, 'gid' => intval($res['gid']));
                $groups[] = new Group($data);
            }


        $groups = new GroupsList($groups);

        //if joins conflict, resolve it
        if (($options & G_USERS_COUNT) && ($options & G_TAPS_COUNT)) {
            self::getUsersCount($groups);
        }

        self::$cache['users'][$user->uid][$options] = $groups;
        return $groups;
    }

    /*
        Updates provided group list, adding to each group
        info about members count
    */
    public static function getUsersCount(GroupsList &$groups) {
        if (empty($groups))
            return;

        $db = DB::getInstance()->Start_Connection('mysql');
        $gids = $groups->filter('gid');

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

        //update corresponding tables
        foreach ($groups as &$group) {
            $gid = $group->gid;
            if (isset($counts[$gid]))
                $group->set('members_count', $counts[$gid]);
            else
                $group->set('members_count', 0);
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
                foreach ($result['info'] as $tag) {
                    $tag = trim($tag);
                    if (strlen($tag) < 128)
                        $tags[] = $tag;
                }
                
                $tags = array_unique($tags);
                return $tags;
            }
        }

        if (empty($fgids))
            return new GroupsList(array());

        $fb = new Facebook();

        $groups = array();
        foreach ($fgids as $fgid) {
            $info = $fb->getGroupInfo($fgid);
            $descr = Taps::makePreview(strip_tags($info['description']), 250);
            $symbol = Taps::makePreview($info['name'], 250);
            $gname = makeGName($info['name']);
            $tags = extractTags($info['name'], $info['description'], $info['category']);
            $picture = isset($info['picture']) ? Images::fetchAndMake(D_GROUP_PIC_PATH, $info['picture'], "$fgid.jpg") : array();
            $favicon = isset($info['link']) ? Images::getFavicon($info['link'], D_GROUP_PIC_PATH."/fav_$fgid.ico") : null;

            $group = false;
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
