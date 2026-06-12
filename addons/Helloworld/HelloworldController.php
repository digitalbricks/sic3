<?php

class HelloworldController extends SicAddon {

    /**
     * This is the method called when the route /helloworld is called, as defined in HelloworldRoutes.php
     */
    public function index() {
        echo "<h1>Hello World!</h1>";
    }

    /**
     * This is the method called when the route /helloworld/hello/@name is called, as defined in HelloworldRoutes.php
     * Try it out by going to /helloworld/hello/YourName, and it should print "Hello World! YourName"
     */
    public function hello() {
        $name = htmlentities(urlencode($this->f3->get('PARAMS.name')));
        echo "<h1>Hello World! {$name}</h1>";
    }
}