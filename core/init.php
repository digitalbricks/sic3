<?php
// bootstrap
// - load F3 and configure autoloading
$f3 = require_once(dirname(__FILE__).'/f3/base.php');
$f3->set('AUTOLOAD',dirname(__FILE__).'/sic/');

// - set the SIC version
$f3->set('tplSicVersion','3.3.2');

// – load demo mode settings
require_once(dirname(__FILE__).'/extras/demomode-settings.php');

// - set default admin username & password for new installations
//   (can be changed in settings and will be checked on login)
$f3->set('defaultAdminUsername','admin');
$f3->set('defaultAdminPassword','admin');

// – activate caching, which is needed for the session
$f3->set('CACHE','TRUE');

// - create session with CSRF token before any session usage
//   (CSRF token can be accessed using $f3->CSRF or via $session->csrf())
$session = new Session(NULL,'CSRF');

// - load SIC and set it in F3
$f3->set('sic', new Sic($f3));

