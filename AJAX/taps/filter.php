<?php
/* CALLS:
   feed.js 
*/

class ajax_filter extends Base {
    protected $view_output = 'JSON';

    public function __invoke() {
        $type    = $_POST['type'];
        $id      = intval($_POST['id']);
        $search  = '%'.str_replace(' ', '%', $_POST['search']).'%';
        $more    = intval($_POST['more']);
        $inside  = intval($_POST['inside']);

        if (!in_array($type, array('public', 'feed', 'aggr_groups', 'aggr_friends', 'aggr_convos', 'group'))) {
            $this->data = array('success' => false, 'data' => array());
            return;
        }

        $params = array(
            'start_from' => $more,
            'search'     => $search
        );
        $options = T_GROUP_INFO | T_USER_INFO;
        if ($inside == 1)
            $options |= T_INSIDE;
        elseif ($inside == 2)
            $options |= T_OUTSIDE;

        if (in_array($type, array('feed', 'aggr_groups', 'aggr_friends', 'aggr_convos')))
            $params['uid'] = $this->user->id;
        elseif ($type == 'group')
            $params['gid'] = $id;

        if (in_array($type, array('feed', 'aggr_friends', 'aggr_convos')))
            $options |= T_USER_RECV;

        if (trim($search))
            $options |= T_SEARCH;

        if ($more)
            $options |= T_LIMIT;

        $data = TapsList::search($type, $params, $options)
                        ->lastResponses()
                        ->format()
                        ->asArrayAll();

        $results = !empty($data);
        $this->data = array('success' => $results, 'data' => $data);
    }
};
