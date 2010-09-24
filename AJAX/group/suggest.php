<?php
/*
    CALLS:
        modal_windows.js
*/

require('../../config.php');
require('../../api.php');

$action = $_POST['action'];

$obj = new suggest_functions();
switch ($action) {
    case 'get':
        $result = $obj->get();
        break;
}

echo json_encode($result);

class suggest_functions {
    private $fb = null;
    private $user = null;

    //cuz we haven't clousures
    private $matched = array();

    public function __construct() {
        $this->fb = new Facebook();
        $this->user = new User(intval($_SESSION['uid']));
    }

    public function get() {
        //here we create aggregate list of all possible suggestions
        //according following order:
        //Work Places + University + Location + Hometown + Birthday  + Books + Movies + Groups
        $info = $this->fb->info;

        $work = $this->getWork();
        $univers = $this->getUnivers();

        $locations = $location_names = array();
        if (!empty($info['location'])) {
            $locations = array_merge($locations, explode(', ', $info['location']['name']), array($info['location']['name']));
            $location_names[ intval($info['location']['id']) ] = $info['location']['name'];
        }

        if (!empty($info['hometown'])) {
            $locations = array_merge($locations, explode(', ', $info['hometown']['name']), array($info['hometown']['name']));
            $location_names[ intval($info['hometown']['id']) ] = $info['hometown']['name'];
        }

        //personal should have more priority
        $personal_keywords = array_merge(array_values($work), array_values($univers), $locations);

        //make array with keywords
        $interests = array();
        foreach(array_merge($this->fb->books, $this->fb->movies, $this->fb->groups, $this->fb->likes) as $i)
            $interests[ intval($i['id']) ] = trim($i['name']);
        
        list($groups_pers, $matched_personal) = GroupsList::byKeywords($personal_keywords, $this->user);
        list($groups_int, $matched_interest) = GroupsList::byKeywords(array_values($interests), $this->user);

        //get a list of keywords (names), not matched by current groups
        $this->matched = array_udiff($personal_keywords, $matched_personal, 'strcasecmp');

        //get a list of id's of work places for whom we sould create new groups
        $createWork = array_keys(array_filter($work, array($this, 'byKeywords')));

        //get a list of id's of schools we want to create
        $createUnivers = array_keys(array_filter($univers, array($this, 'byKeywords')));

        //get a list of id's for locations [hometown, current location]
        $createLocations = array_keys(array_filter($location_names, array($this, 'byKeywords')));

        $this->matched = array_udiff(array_values($interests), $matched_interest, 'strcasecmp');
        $createLikes = array_filter($interests, array($this, 'byKeywords'));
        if (count($createLikes) > 0)
            $createLikes = array_rand($createLikes, count($createLikes) > 10 ? 10 : count($createLikes));

        //create groups, and get array of it's objects
        $created = GroupsList::merge(
            GroupsList::bulkCreateFacebook($this->user, $createWork ? $createWork : array()),
            GroupsList::bulkCreateFacebook($this->user, $createUnivers ? $createUnivers : array()),
            GroupsList::bulkCreateFacebook($this->user, $createLocations ? $createLocations : array()),
            GroupsList::bulkCreateFacebook($this->user, $createLikes ? $createLikes : array()));

        $suggest = GroupsList::merge($created, $groups_pers, $groups_int)->filter('info');

        return array('success' => 1, 'data' => $suggest);
    }

    private function byKeywords($val) {
        return in_array($val, $this->matched);
    }

    /*
        Returns a list of user workplaces
    */
    private function getWork()  {
        $info = $this->fb->info;
        $work = array();
        if (!empty($info['work']))
            foreach ($info['work'] as $w)
                $work[intval($w['employer']['id'])] = $w['employer']['name'];

        return $work;        
    }

    /*
        Returns a list of user educational schools
    */
    private function getUnivers() {
        $info = $this->fb->info; 
        $univers = array();
        if (!empty($info['education']))
            foreach ($info['education'] as $e)
                $univers[ intval($e['school']['id']) ] = $e['school']['name'];

        return $univers;
    }

    /*
        extracts all relevant info from group objects
    */
    private function extractInfo(Group $item) {
        return $item->info;
    }
};

