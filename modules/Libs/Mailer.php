<?php

/*
    Some basic mail-notification workaround,
    collections of functions actually
*/
class Mailer {
    private static $types = array('welcome' => 1, 'join_confirm' => 2, 'new_personal' => 3,
        'new_follower' => 4, 'new_message' => 5, 'new_reply' => 6, 'digest' => 7, 'new_member' => 8,
        'mention' => 9, 'new_registred' => 10);

    private function __construct() {}


    private static function formEmail($_template, array $_vars) {
        extract($_vars);
        ob_start();
        include(BASE_PATH.'/views/mails/'.$_template.'.phtml');
        return ob_get_clean();
    }

    private static function send($email, $subject, $text) {
        $status = mail($email, $subject, $text, "From: Circlefy <robot@circlefy.com>\r\n");
        if (!$status && DEBUG)
            FirePHP::getInstance(true)->error("Mail sending error to `$email`");
    }

    private static function queue(User $u, $type, $email = null, $user_id = null, 
                                  $message_id = null, $group_id = null, $reply_id = null) {
        DB::getInstance()->insert('email_queue', 
            array('reciever_id' => $u->id, 'alt_email' => $email,
                  'type' => self::$types[$type], 'user_id' => $user_id,
                  'message_id' => $message_id, 'group_id' => $group_id, 'reply_id' => $reply_id));
    }

    public static function sendQueue() {
        //WARNING: it's a piece of shit, but it works, so, you better don't touch it

        $db = DB::getInstance();

        $db->startTransaction();

        $data = $users = $taps = $groups = $replies = array();
        try {
            $query = 'SELECT id, type, reciever_id, alt_email, user_id, message_id, group_id, reply_id
                        FROM email_queue
                       ORDER BY type ASC, id ASC
                       LIMIT 10';

            $result = $db->query($query, array());
            if ($result->num_rows == 0)
                return;
            
            $result = $db->query($query, array());
            while ($res = $result->fetch_assoc()) {
                $data[ intval($res['id']) ] = $res;
                $users[] = intval($res['reciever_id']);
                $users[] = intval($res['user_id']);
                $taps[]  = intval($res['message_id']);
                $groups[]= intval($res['group_id']);
                $replies[]=intval($res['reply_id']);
            }

            $query = 'DELETE FROM email_queue WHERE id IN (#ids#)';
            $db->query($query, array('ids' => array_keys($data)));

            $db->commit();
        } catch (SQLException $e) {
            $db->rollback();
            throw $e;
        }

        $users = UsersList::search('byIds', array('ids' => array_unique($users)))->asArray();
        $taps  = TapsList::search('byIds', array('ids' => array_unique($taps)))->asArray();
        $groups = GroupsList::search('byIds', array('ids' => array_unique($groups)))->asArray();
        $replies = TapsList::repliesById(array_unique($replies));

        $types = array_flip(self::$types);
        foreach($data as $mail) {
            if ($mail['reciever_id'] == $mail['user_id'])
                continue;

            $type = $types[intval($mail['type'])];
            $u = $users[ intval($mail['reciever_id']) ];

            switch ($type) {
                case 'welcome':
                    self::send($u->email, 'Welcome to circlefy!',
                        self::formEmail('welcome', array('your_name' => $u->fname)));
                    break;
                
                case 'new_follower':
                    $f = $users[ intval($mail['user_id']) ];
                    self::send($u->email, $f->uname.' now following you',
                        self::formEmail('new_follower', array('your_name' => $u->fname,
                            'fname' => $f->fname, 'lname' => $f->lname, 'uname' => $f->uname)));
                    break;

                case 'new_message':
                    $s = $users[ intval($mail['user_id']) ];
                    $m = $taps[ intval($mail['message_id']) ];
                    $g = $groups[ intval($mail['group_id']) ];
                    self::send($u->email, $s->uname.' left new message at '.$g->name,
                        self::formEmail('new_message', array('your_name' => $u->fname,
                            'fname' => $s->fname, 'lname' => $s->lname, 'private' => $m->private,
                            'circle' => $g->name, 'text' => $m->text, 'mid' => $m->id)));
                    break;

                case 'new_personal':
                    $s = $users[ intval($mail['user_id']) ];
                    $m = $taps[ intval($mail['message_id']) ];
                    self::send($u->email, $s->uname.' sent you a private message',
                        self::formEmail('new_personal', array('your_name' => $u->fname,
                            'fname' => $s->fname, 'lname' => $s->lname, 'private' => $m->private,
                            'text' => $m->text, 'mid' => $m->id)));
                    break;

                case 'new_reply':
                    $s = $users[ intval($mail['user_id']) ];
                    $r = $replies[ intval($mail['reply_id']) ];
                    
                    self::send($u->email, $s->uname.' left new reply',
                        self::formEmail('new_reply', array('your_name' => $u->fname,
                            'fname' => $s->fname, 'lname' => $s->lname, 'text' => $r['text'],
                            'mid' => $r['message_id'])));
                    break;

                case 'new_member': 
                    $s = $users[ intval($mail['user_id']) ];
                    $g = $groups[ intval($mail['group_id']) ];
                    self::send($u->email, $s->uname.' joins '.$g->name,
                        self::formEmail('new_member', array('your_name' => $u->fname,
                            'fname' => $s->fname, 'lname' => $s->lname, 'circle' => $g->name)));
                    break;

                case 'mention':
                    $s = $users[ intval($mail['user_id']) ];
                    $m = $taps[ intval($mail['message_id']) ];
                    self::send($u->email, $s->uname.' mentioned you in conversation',
                        self::formEmail('mention', array('your_name' => $u->fname,
                            'fname' => $s->fname, 'lname' => $s->lname, 'text' => $m->text, 'mid' => $m->id)));
                    break;
            }
        }
    }

    public static function joinConfirm(User $u, $email, Group $g, $link) {
        //self::queue($u, 'join_confirm', $email, null, $g->id);
        //hehe, hacky
        self::send($email, 'Confirmation of Email for Circle',
            self::formEmail('join_confirm', array('your_name' => $u->fname,
                'group_name' => $g->name, 'link' => $link)));
    }

    public static function newFollower(User $u, User $follower) {
        self::queue($u, 'new_follower', null, $follower->id);
    }

    public static function welcome(User $u) {
        self::queue($u, 'welcome');
    }

    public static function newMessage(User $u, Tap $m) {
        self::queue($u, 'new_message', null, $m->sender_id, $m->id, $m->group_id);
    }

    public static function newPersonal(User $u, Tap $m) {
        self::queue($u, 'new_personal', null, $m->sender_id, $m->id);
    }

    public static function newReply(User $u, array $m) {
        self::queue($u, 'new_reply', null, $m['user_id'], $m['message_id'], null, $m['id']);
    }
    
    public static function newRegistred(User $u, User $new) {
        self::queue($u, 'new_registred', null, $new->id);
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
