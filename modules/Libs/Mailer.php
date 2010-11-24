<?php

/*
    Some basic mail-notification workaround,
    collections of functions actually
*/
class Mailer {
    private function __construct() {}

    private static function formEmail($_template, array $_vars) {
        extract($_vars);
        ob_start();
        include(BASE_PATH.'/views/mails/'.$_template.'.phtml');
        return ob_get_clean();
    }

    private static function send($email, $subject, $body) {
        if (DEBUG)
            FirePHP::getInstance(true)->log(array('email' => $email, 'subject' => $subject, 'body' => $body), 'Mail');

        $status = mail($email, $subject, $body, "From: Circlefy Robot <robot@circlefy.com>\r\n");

        if (!$status && DEBUG)
            FirePHP::getInstance(true)->error("Mail sending error to `$email`");

        return $status;
    }

    public static function joinConfirm(User $u, $email, Group $g, $link) {
        self::send($email, 'Confirmation of Email for Circle',
            self::formEmail('join_confirm', 
                array('your_name' => $u->fname, 'group_name' => $g->name, 'link' => $link)));
    }

    public static function newFollower(User $u, User $follower) {
        if ($u->email === null)
            $u = User::init($u->id);

        if ($follower->uname === null)
            $follower = User::init($follower->id);

        self::send($u->email, $follower->uname.' now following you',
            self::formEmail('new_follower',
                array('your_name' => $u->fname, 'fname' => $follower->fname, 'lname' => $follower->lname)));
    }

    public static function welcome(User $u) {
        self::send($u->email, 'Welcome to circlefy!',
            self::formEmail('welcome', array('your_name' => $u->fname)));
    }

    public static function newMessage(User $u, Tap $m) {
        if ($u->email === null)
            $u = User::init($u->id);

        self::send($u->email, $m->sender->uname.' left new message at '.$m->group->name,
            self::formEmail('new_message', 
                array('your_name' => $u->fname, 'fname' => $m->sender->fname, 'lname' => $m->sender->lname,
                      'circle' => $m->group->name, 'private' => $m->private, 'text' => $m->text,
                      'mid' => $m->id)));
    }

    public static function newPersonal(User $u, Tap $m) {
        if ($u->email === null)
            $u = User::init($u->id);

        self::send($u->email, $m->sender->uname.' sent you a private message',
            self::formEmail('new_personal',
                array('your_name' => $u->fname, 'fname' => $m->sender->fname, 'lname' => $m->sender->lname,
                      'text' => $m->text, 'mid' => $m->id)));
    }

    public static function digest(User $u, TapsList $messages, TapsList $replies, TapsList $followers) {
        if (count($messages) == 0 && count($replies) == 0 && count($followers) == 0)
            return;

        self::send($u->email, 'Circlefy digest for '.date('m.d.Y'),
            self::formEmail('digest',
                array('your_name' => $u->fname, 'messages' => $messages->asArrayAll(), 
                      'responses' => $replies->asArrayAll(), 'followers' => $followers->asArrayAll())));
    }
};
