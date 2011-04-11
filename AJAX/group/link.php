<?php

class ajax_link extends Base {
    protected $view_output = 'JSON';

    public function __invoke() {
        $child = new Group(intval($_POST['child']));
        $parent = new Group(intval($_POST['parent']));

        if (!$child->isPermitted($this->user)) {
            $this->set(false, 'success');
            return;
        }

        GroupRelations::link($child, $parent);
        $this->set(Group::byId($parent->id)->asArray(), 'group');
        $this->set(true, 'success');
    }
};
