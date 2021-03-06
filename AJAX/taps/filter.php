<?php
/* CALLS:
   feed.js 
*/

class ajax_filter extends Base {
    protected $view_output = 'JSON';

    public function __invoke() {
        $type    = $_POST['type'];
        $id      = intval($_POST['id']);
        $search  = $_POST['search'];
        $more    = intval($_POST['more']);
        $inside  = intval($_POST['inside']);
        $anon    = intval($_POST['anon']);

        if (!in_array($type, array('public', 'feed', 'aggr_groups', 'aggr_friends', 'aggr_convos', 'group', 'friend', 'private'))) {
            $this->data = array('success' => false, 'data' => array());
            return;
        }

        $params = array('start_from' => $more);
        $options = T_GROUP_INFO | T_USER_INFO | T_MEDIA;
        if ($inside == 1)
            $options |= T_INSIDE;
        elseif ($inside == 2)
            $options |= T_OUTSIDE;

        if ($anon)
            $options |= T_ANON;

        if (in_array($type, array('feed', 'aggr_groups', 'aggr_friends', 'aggr_convos'))) {
            $params['uid'] = $this->user->id;
            $options |= T_NEW_REPLIES;
        } elseif ($type == 'group') {
            $params['gid'] = $id;
            $options |= T_NEW_REPLIES;
        } elseif ($type == 'friend') {
            $params['uid'] = $id;
        } elseif ($type == 'private') {
            $params['from'] = $id;
            $params['to'] = $this->user->id;
        } elseif ($type == 'public') {
            $options &= ~T_INSIDE;
            $options |= T_OUTSIDE;
        }

        if (in_array($type, array('friend', 'aggr_friends')) && $this->user->id)
            $params['you'] = $this->user->id;

        if (in_array($type, array('feed', 'aggr_friends', 'aggr_convos', 'private')))
            $options |= T_USER_RECV;

        $search = trim(strip_tags($search));
        if (!empty($search)) {
            $options |= T_SEARCH;
            $params['search'] = '%'.str_replace(' ', '%', preg_replace('/\s{2,}/', ' ', $search)).'%';
        }

        if ($more)
            $options |= T_LIMIT;

        $data = TapsList::search($type, $params, $options)
                        ->lastResponses()
                        ->format()
                        ->asArrayAll();

        $results = !empty($data);
        $this->data = array('success' => $results, 'more' => count($data) >= 10, 'data' => array_values($data));
    }
};
