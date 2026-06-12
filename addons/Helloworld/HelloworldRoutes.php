<?php
return array(
    'GET /' => 'index',             // means: Route /helloworld | Controller: HelloworldController->index
    'GET /test' => 'test',         // means: Route /helloworld/test | Controller: HelloworldController->index
    'GET /hello/@name' => 'hello',  // means: Route /helloworld/hello/[variable] | Controller: HelloworldController->hello
);