<?php

class SicAddons {

    private $f3 = null;
    private string $addonsDir = '';
    private array $addonNames = [];

    function __construct($f3) {
        $this->f3 = $f3;

        $this->addonsDir = dirname(__FILE__) . '/../../addons/';
        if (!is_dir($this->addonsDir)) return;

        foreach (scandir($this->addonsDir) as $folder) {
            if ($folder === '.' || $folder === '..') continue;
            if (is_dir($this->addonsDir . $folder . '/')) {
                $this->addonNames[] = $folder;
            }
        }
    }

    public function loadControllers() {
        foreach ($this->addonNames as $addonName) {
            $controllerFile = $this->addonsDir . $addonName . '/' . $addonName . 'Controller.php';
            if (!file_exists($controllerFile)) continue;
            require_once($controllerFile);
        }
    }

    public function registerRoutes() {
        foreach ($this->addonNames as $addonName) {
            $routesFile = $this->addonsDir . $addonName . '/' . $addonName . 'Routes.php';
            if (!file_exists($routesFile)) return;

            $routes = require($routesFile);

            // create f3 routes definition based on the returned array from the routes file
            // echo array entry looks like this: 'GET /helloworld' => 'index', which means [Addonname]Controller->index
            foreach ($routes as $route => $method) {

                // create second route() parameter for the controller method, e.g. HelloworldController->index
                $controllerParam = "{$addonName}Controller->{$method}";

                // register route in f3
                $this->f3->route($route, $controllerParam);

                $this->f3->route('GET /update2','SicUpdate->updateInfoRouteGet');
            }

        }
    }

    public function getF3() {
        return $this->f3;
    }
}