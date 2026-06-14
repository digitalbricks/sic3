<?php

/**
 *
 */
class SicAddon {

    protected $f3;
    public function __construct($f3) {
        // Initialize plugin
        $this->f3 = $f3;

    }


    /**
     * Renders an addon view file inside as partial in base layout (core/views/_base.html).
     * The view file has to be located in the addon views folder, e.g. addons/Helloworld/views/test.html
     * for the Helloworld addon.
     *
     * @param string $viewName name of the view file
     * @param array $data associative array of f3 variables to set for the view (optional, but often needed)
     * @return string
     * @throws Exception
     */
    public function renderView($viewName, $data = []){
        // set defaults
        $defaults = [
            'tplPagetitle' => '',
            'tplHeadline' => '',
            'tplPartial' => '',
            'tplDarkmodeClass' => '',
        ];

        // combine defaults with given data
        $data = array_merge($defaults, $data);

        // iterate over the combined data and set it in F3
        foreach ($data as $key => $value) {
            $this->f3->set($key, $value);
        }

        // set mandatory template vars for base layout (see SicUiViewsController)
        // – 1. darkmode class for body tag
        if($this->f3->get('COOKIE.darkmode') === 'true'){
            $this->f3->set('tplDarkmodeClass','darkmode');
        };

        // - 2. enqueued message (blank on addon pages)
        $this->f3->set('tplEnqueuedMessages',$this->getEnqueuedMessages());



        // set the file to render as a partial in base in layout
        $partialRelativePath = 'addons/'.$this->getAddonDirname().'/views/'.trim($viewName);
        $partialAbsolutePath = $this->getDirectory() . '/views/' . trim($viewName);
        if(!file_exists($partialAbsolutePath)){
            throw new \Exception("View file not found: " . $partialRelativePath);
        }

        $this->f3->set('tplPartial',$partialRelativePath);
        return \Template::instance()->render('core/views/_base.html');
    }



    /**
     * Enqueue a message to be displayed on the next page load.
     * This is useful for displaying messages after a redirect.
     * Message are stored in PHP session.
     *
     *  !! NOTE: This method is a duplicate of the enqueueMessage() method in SicUiViewsController,
     *  but we need it here as well, because addons should be able to use the enqueueMessage() and getEnqueuedMessages()
     *  methods without depending on the SicUiViewsController.
     *  Maybe this will be refactored in the future to avoid code duplication, but for now we keep it simple.
     *
     * @param $message
     * @param $type string primary|success|warning|danger
     * @return false|void
     */
    function enqueueMessage($message, $type = 'primary'){
        if(!$this->f3->get('sic')->isUserLoggedIn()){
            return false;
        }
        // init session msgQueue if not exists
        $msgQueue = $this->f3->get('SESSION.msgQueue');
        if(!$msgQueue|| !is_array($msgQueue)){
            $msgQueue = array();
        }
        $msgQueue[] = array(
            'message' => htmlspecialchars($message),
            'type' => $type
        );
        $this->f3->set('SESSION.msgQueue',$msgQueue);
    }

    /**
     * Get enqueued messages from session and clear session.
     * (Flush on read, so messages are only displayed once)
     *
     * !! NOTE: This method is a duplicate of the getEnqueuedMessages() method in SicUiViewsController,
     * but we need it here as well, because addons should be able to use the enqueueMessage() and getEnqueuedMessages()
     * methods without depending on the SicUiViewsController.
     * Maybe this will be refactored in the future to avoid code duplication, but for now we keep it simple.
     *
     * @return false|array
     */
    function getEnqueuedMessages(){
        if(!$this->f3->get('sic')->isUserLoggedIn()){
            return false;
        }
        $msgQueue = $this->f3->get('SESSION.msgQueue');
        $this->f3->clear('SESSION.msgQueue');
        return $msgQueue;
    }


    /**
     * Returns absolute file path of the addon controller file.
     * @return string
     */
    public function getFilePath(): string {
        return (new \ReflectionClass(static::class))->getFileName();
    }

    /**
     * Returns absolute directory path of the addon controller file.
     * @return string
     */
    public function getDirectory(): string {
        $reflection = new \ReflectionClass(static::class);
        return dirname($reflection->getFileName());
    }

    /**
     * Returns the class name of the addon controller, e.g. HelloworldController.
     * @return string
     */
    public function getClassName(): string {
        return static::class;
    }

    /**
     * Returns the addon directory name, which is the class name without "Controller" suffix (if exists).
     * Example: addons/Helloworld/ (with HelloworldController) -> Helloworld
     * @return string
     */
    public function getAddonDirname(): string {
        $className = $this->getClassName();
        if(strpos($className, 'Controller') !== false){
            return str_replace('Controller', '', $className);
        }
        return $className;
    }


}