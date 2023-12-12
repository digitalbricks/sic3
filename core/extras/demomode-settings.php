<?php
/*
 * This file is included in core/init.php
 * and is used for demo mode only.
 *
 *
 * */

if(!isset($f3)) die();

// - set SIC demo mode (used for taking screenshots)
$f3->set('sicDemoMode',false);

// - define demo hostnames used in SIC demo mode
$f3->set('sicDemoModeHostnames', array(
    'demo-server1.de',
    'testapp-demo.net',
    'sample-host-123.org',
    'sandbox-website.io',
    'dev-environment2.com',
    'play-demo-app.tech',
    'showcase-server1.biz',
    'hosting-instance.info',
    'dummy-cloud-service.online',
    'prototypewebapp.dev',
    'stagingenvironment.store',
    'temphost-demo.space',
    'virtual-demo-machine.de',
    'trial-lorem.com',
    'example.com',
    'example-microservice-demo.mobi',
    'demo-database-instance.press',
    'showcase-iot-device.gallery',
    'testing-framework-demo.club',
    'temporary-demo-host.name',
    'mockup-server-instance.app',
    'trial-environment-demo.zone',
    'sample-backend-service.page',
    'virtual-demo-hosting.land',
    'showcase-frontend-demo.tv',
    'prototypegateway.de',
    'temp-cloud-instance-demo.press',
    'testing-automation-demo.services',

));