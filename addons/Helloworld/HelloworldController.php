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
                'GET /' => 'index',             // means: Route /helloworld | Controller: HelloworldController->index
                'GET /test' => 'test',         // means: Route /helloworld/test | Controller: HelloworldController->index
                'GET /hello/@name' => 'hello',  // means: Route /helloworld/hello/[variable] | Controller: HelloworldController->hello
            )
        );
    }

    /**
     * This is the method called when the route /addon/helloworld is called,
     * as defined in HelloworldRoutes.php
     */
    public function index() {
        echo "<h1>Hello World!</h1>";
    }

    /**
     * This is the method called when the route /addon/helloworld is called,
     * as defined in HelloworldRoutes.php
     */
    public function test() {
        echo "<h1>This is a test</h1>";
        /*TODO: Make rendering views working*/
        var_dump($this->renderView('helloworld/views/test.html'));
        echo $this->renderView('helloworld/views/test.html');
    }

    /**
     * This is the method called when the route /addon/helloworld/hello/@name is called,
     * as defined in HelloworldRoutes.php
     * Try it out by going to /helloworld/hello/YourName, and it should print "Hello World! YourName"
     */
    public function hello() {
        $name = htmlentities(urlencode($this->f3->get('PARAMS.name')));
        echo "<h1>Hello World! {$name}</h1>";
    }
}