<?php

/*
    Stuff for logging user actions
    (group view, profile view, tap response, etc)
*/
class Action {

    /*
        Types:
            group, user, tap

        Actions:
            view

        Info:
            gid, cid, uid
            array (type => value)
    */
    public static function log(User $user, $type, $action, array $info) {
        $types = array('group' => 1, 'user' => 2, 'tap' => 3);
        $actions = array('view' => 1);
        $infos = array('gid', 'mid', 'uid');

        if (count(array_intersect($infos, array_keys($info)))==0)
            throw new ActionDataException('You should specify at least one parameter');

        if (!array_key_exists($type, $types))
            throw new ActionTypeException("Wrong action type '$type'");

        if (!array_key_exists($action, $actions))
            throw new ActionTypeException("Wrong action '$action'");

        $db = DB::getInstance()->Start_Connection('mysql');

        $data = array('type' => $types[$type], 'action' => $actions[$action],
            'gid' => null, 'mid' => null, 'uid' => null, 'user' => $user->uid);
        $data = array_merge($data, $info);
        
        $query = '
            INSERT
              INTO actions (user, type, action, gid, mid, uid)
            VALUES (#user#, #type#, #action#, #gid#, #mid#, #uid#)';
        $db->query($query, $data);

        return $db->affected_rows == 1;
    }
};
