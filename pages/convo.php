<?php

class page_convo extends Base {
    protected $need_login = true;

	function __invoke() {
        $id = intval($_GET['id']);

        $tap = TapsList::search('byId', array('id' => $id), T_USER_INFO | T_GROUP_INFO | T_MEDIA)
                       ->replies()
                       ->involved()
                       ->format()
                       ->getFirst();

        if (!($tap instanceof Tap && $this->isAllowed($tap))) {
            header('Location: /');
            exit();
        }

        if (!$this->user->guest) {
            $tap->deleteEvent($this->user);
            $this->set(TapsList::fetchEvents($this->user, $page)->format()->asArrayAll(), 'events');
        }

        $this->set($tap->asArray(), 'convo'); 
        $this->set($tap->getStatus($this->user), 'state');
	}

    private function isAllowed(Tap $tap) {
        if ($tap->id === null)
            return false;

        $personal = isset($tap->reciever_id);
        if ($this->user->guest && ($personal || $tap->private))
            return false;

        if ($personal)
            return $this->user->id == $tap->sender_id || $this->user->id == $tap->reciever_id;
        elseif ($tap->private)
            return $this->user->inGroup(new Group($tap->group_id));

        return true;
    }
};
