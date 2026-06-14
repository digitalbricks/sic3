<?php

class HelloworldController extends SicAddon {

    /**
     * Mandatory method to provide basic information about the addon, such as name, description, version and author.
     * @return string[]
     */
    public static function getAddonInfo() {
        return array(
            'name' => 'Helloworld',
            'description' => 'A simple addon to demonstrate how to create addons for Site Information Center.',
            'version' => '1.0',
            'author' => 'Your Name',
            'license' => 'MIT',
            'adminOnly' => false,
            'routes' => array(
                'GET /' => 'index',             // means: Route /addon/helloworld | Controller: HelloworldController->index
                'GET /test' => 'test',          // means: Route /addon/helloworld/test | Controller: HelloworldController->index
                'GET /hello/@name' => 'hello',  // means: Route /addon/helloworld/hello/[variable] | Controller: HelloworldController->hello
            ),
            'menuEntry' => array(               // optional: if provided, the addon will be listed in the sidebar menu
                'title' => 'Hello World',       // title of the menu entry
                'icon' => 'happy',              // name of a UIkit Icon (https://getuikit.com/docs/icon)
                'href' => 'addon/helloworld/test'   // href for the menu entry (addon/[addonname][route]) or external url
            )
        );
    }

    /**
     * This is the method called when the route /addons/helloworld is called,
     * as defined in HelloworldController.php
     */
    public function index() {
        echo "<h1>Hello World!</h1>";
    }

    /**
     * This is the method called when the route /addon/helloworld is called,
     * as defined in HelloworldController.php
     */
    public function test() {
        $data = array(
            'tplPagetitle' => 'Test page Helloworld Addon',                 // base layout variable
            'tplHeadline' => 'A simple test page for the Helloworld addon', // base layout variable
            'hwVar1' => 'Demo variable 1',                                  // addon specific variable for view
            'hwVar2' => date('Y-m-d H:i:s')                          // addon specific variable for view
        );
        echo $this->renderView('test.html', $data);
    }

    /**
     * This is the method called when the route /addon/helloworld/hello/@name is called,
     * as defined in HelloworldController.php
     * Try it out by going to /helloworld/hello/YourName, and it should print "Hello World! YourName"
     */
    public function hello() {
        $name = htmlentities(urlencode($this->f3->get('PARAMS.name')));
        echo "<h1>Hello World! {$name}</h1>";
    }
}