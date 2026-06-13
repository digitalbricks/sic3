<?php

class SicAddon {

    protected $f3;
    public function __construct($f3) {
        // Initialize plugin
        $this->f3 = $f3;

    }


    public function renderView($viewName, $data = []){
        // set defaults
        $defaults = [
            'tplPagetitle' => 'tplPagetitle',
            'tplHeadline' => 'tplHeadline',
        ];

        // combine defaults with given data
        $data = array_merge($defaults, $data);

        // iterate over the combined data and set it in F3
        foreach ($data as $key => $value) {
            $this->f3->set($key, $value);
        }

        $this->f3->set('tplPartial','core/views/overview.html');
        var_dump('rendering view: ' . $viewName);
        return \Template::instance()->render('core/views/_base.html');
    }


}