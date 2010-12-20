<?php

class page_circle extends Base {
    protected $need_login = true;

	function __invoke() {
        $symbol = $_GET['symbol'];

        //TODO: do something if group doesn't exists
        $group = Group::init($symbol);
        if (empty($group)) {
            header('Location: /');
            exit();
        }
        $group->descr = FuncLib::linkify($group->descr);
        $this->set($group->asArray(), 'circle');

        // Email confirmation
        $auth_token = $_GET['confirm'];
        if (!empty($auth_token))
            $r = Group::confirmEmail($auth_token);

        $this->set(
            UsersList::search('members', array('gid' => $group->id, 'limit' => 14))
                     ->asArrayAll(),
            'members');

        $this->set(
            UsersList::search('members', array('gid' => $group->id), U_PENDING)
                     ->asArrayAll(),
            'pending');

        $member = Group::$permissions[$group->userPermissions($this->user)] >= Group::$permissions['user'];
        $pending = $group->userPermissions($this->user) == 'pending';

        $inside = $member ? 0 : T_OUTSIDE;

        $this->set(
            TapsList::search('group', array('gid' => $group->id, 'uid' => $this->user->id),
                             T_USER_INFO | $inside | T_MEDIA | T_NEW_REPLIES)
                    ->lastResponses()
                    ->inject('group', $group)
                    ->format()
                    ->asArrayAll(),
            'feed');

        $childs = GroupRelations::getChilds($group);
        $tree = $childs->formTree('ancestor', $group->id);
        if (DEBUG) {
            FirePHP::getInstance(true)->log($childs, 'childs');
            FirePHP::getInstance(true)->log($tree,   'tree');
        }
        $this->set($tree->asArrayAll(), 'childs');

        $this->set($member, 'state');
        $this->set($pending, 'user_pending');

        $this->set(!$this->user->firstTap($group), 'first_tap');

        $this->set(
            $group->isPermitted($this->user),
            'moderator');


        $this->set('circle', 'page');
	}

};
