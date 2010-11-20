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

        $member = $group->userPermissions($this->user) != Group::$permissions['not_in'];

        $inside = $member ? 0 : T_OUTSIDE;

        $this->set(
            TapsList::search('group', array('gid' => $group->id, 'uid' => $this->user->id),
                             T_USER_INFO | $inside | T_MEDIA | T_NEW_REPLEIS)
                    ->lastResponses()
                    ->inject('group', $group)
                    ->format()
                    ->asArrayAll(),
            'feed');

        $this->set($member, 'state');

        $this->set(!$this->user->firstTap($group), 'first_tap');

        $this->set(
            $group->isPermitted($this->user),
            'moderator');

        if (!$this->user->guest)
            $this->set(TapsList::fetchEvents($this->user, $page)->format()->asArrayAll(), 'events');

        $this->set('circle', 'page');
	}
};
