<?php

class SicAddons {

    private $f3 = null;

    private $userIsAdmin = false;
    private string $addonsDir = '';
    private array $addons = [];

    function __construct($f3) {
        $this->f3 = $f3;
        $this->userIsAdmin = $this->f3->get('userIsAdmin');

        $this->addonsDir = dirname(__FILE__) . '/../../addons/';
        if (!is_dir($this->addonsDir)) return;

        foreach (scandir($this->addonsDir) as $folder) {
            if ($folder === '.' || $folder === '..') continue;

            // skip directories that start with a dot (assuming disabled addons, e.g. .Helloworld)
            if(strpos($folder, '.') === 0) continue;

            if (is_dir($this->addonsDir . $folder . '/')) {
                $this->addons[] = $folder;
            }
        }
    }

    public function initialize(){
        // intialize addons only of user is logged in
        if(!$this->f3->get('sic')->checkLogin(false)){return;}
        foreach ($this->addons as $addonName) {

            // 1. load addon controller
            $controllerName = $addonName . 'Controller';
            $controllerFile = $this->addonsDir . $addonName . '/' . $controllerName . '.php';
            if (!file_exists($controllerFile)) continue;
            require_once($controllerFile);

            // get module info
            if(class_exists($controllerName)){
                $moduleinfo = $controllerName::getAddonInfo();
                if(array_key_exists('adminOnly', $moduleinfo) && $moduleinfo['adminOnly'] && !$this->userIsAdmin){
                    continue; // addon is only for admins, but user is no admin, skip addon
                }
            } else {
                continue; // controller class not found, skip addon
            }

            // store module info in $this->addonNames
            $this->addons[$addonName] = $moduleinfo;



            // 2. register addon routes
            $routes = array();
            if(array_key_exists('routes', $moduleinfo) && $moduleinfo['routes']){
                $routes = $moduleinfo['routes'];
            }

            // create f3 routes definition based on the returned array from the routes file
            // echo array entry looks like this: 'GET /helloworld' => 'index',
            // which translates to $f3->route('GET /helloworld', '[Addonname]Controller->index')
            foreach ($routes as $route => $method) {

                // create second route() parameter for the controller method, e.g. HelloworldController->index
                $controllerParam = "{$addonName}Controller->{$method}";

                /*
                 * In order to prevent addons to override core routes, we need to prefix each route with /addon/addonname.
                 * Therefore we have to split the route defintion (e.g. 'GET /helloworld') into method and path, and then
                 * add the prefix to the path.
                 */
                $routeParts = explode(' ', $route);
                if (count($routeParts) != 2) continue; // invalid route definition, skip
                $addonSegment = "/addon/".strtolower($addonName);
                $httpMethod = $routeParts[0];
                $routePath = $routeParts[1];
                if($routePath == "/"){
                    // if the route is just '/', we don't want to add another '/' in between,
                    // so we skip the slash in the addon segment – leading to /addon/addonname
                    $routePath = $addonSegment;
                    $routeParam = $httpMethod." ".$routePath;
                } else {
                    $routePath = $addonSegment.$routePath;
                    $routeParam = $httpMethod." ".$routePath;
                }

                // register route in f3
                $this->f3->route($httpMethod." ".$routePath, $controllerParam);
            }
        }
    }

    public function getAddons(): array {
        return $this->addons;
    }

}