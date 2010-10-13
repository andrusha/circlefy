<?php
/*
    CALLS:
        modal_windows.js
*/

class ajax_suggest extends Base {
    protected $view_output = 'JSON';
    private $fb = null;

    public function __invoke() {
        $this->fb = new Facebook();

        $action = $_POST['action'];

        if ($action == 'get')
            $result = $this->suggest();

        $this->data = $result ?: array('fail');
    }

    private function suggest() {
        // I. Fetch FB-info
        $info = $this->fb->info;

        // II. Make keywords
        // in following order (work -> education -> location -> hometown -> 10 random likes)
        $keywords = array();
        foreach (array('work' => 'employer','education' => 'school', 
                       'location' => 'location', 'hometown' => 'hometown') as $first => $second)
            if (!empty($info[$first]) && $first == $second)
                $keywords[ intval($info[$first]['id']) ] = $info[$first]['name'];
            elseif (!empty($info[$first]) && $first != $second)
                foreach ($info[$first] as $i)
                    $keywords[ intval($i[$second]['id']) ] = $i[$second]['name'];

        // Select 10 or less random likes
        $interests = array();
        foreach(array_merge($this->fb->books, $this->fb->movies, $this->fb->groups, $this->fb->likes) as $i)
            $interests[ intval($i['id']) ] = trim($i['name']);

        if (!empty($interests))
            $interests = array_intersect_key(
                                $interests,
                                array_flip(
                                    array_rand($interests,
                                               count($interests) >= GROUPS_FROM_LIKES ? GROUPS_FROM_LIKES : count($interests))));
       
        // because array_merge rewrites numeric keys
        $keywords = $keywords + $interests;

        // III. Search groups by FB ID
        $foundByFBID = GroupsList::search('byFbIDs', array('fbids' => array_keys($keywords)));

        // IV. Search by keywords (unmatched by FB ID)
        $keywords = array_diff_key($keywords, array_flip($foundByFBID->filter('fb_id')));
        list($found, $match) = GroupsList::byKeywords($keywords, $this->user);

        // V. Create new groups from unamtched keywords
        $match  = array_udiff($keywords, $match, 'strcasecmp');
        $create = array_filter($keywords,
                      function ($x) use ($match) {
                          return in_array($x, $match);
                      });

        if (DEBUG) {
            $this->debug->log($keywords, 'keywords');
            $this->debug->log($found,    'founded');
            $this->debug->log($match,    'matched');
            $this->debug->log($create,   'create');
        }

        $created = GroupsList::bulkCreateFacebook($this->user, array_keys($create));

        // VI. Return aggregated result
        $suggest = GroupsList::merge($created, $found, $foundByFBID)->unique('id')->asArrayAll();

        return array('success' => 1, 'data' => $suggest);
    }
};

