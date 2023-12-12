<?php
class SicTests extends Sic{
    private $f3;
    private $sic;
    public function __construct($f3){
        parent::__construct($f3);
        $this->f3 = $f3;
        $this->sic = $f3->get('sic');
        // check login and only allow admins
        $this->sic->checkLogin(true,$admins_only = true);

        // set path of this file (used for truncating paths in error messages)
        $this->f3->set('tplTestsPath',realpath(__DIR__));

        // set default values for template variables
        $this->f3->set('tplHeadline','');
        $this->f3->set('tplPagetitle','');
        $this->f3->set('tplPartial','');
        $this->f3->set('tplDarkmodeClass','');
        $this->f3->set('firstUrlSegment','');
        $this->f3->set('tplEnqueuedMessages','');

    }

    public function doTests(){
        $sicDB = $this->sicDB;
        $allSites = $sicDB->exec('SELECT * FROM sites');
        $activeSites = $sicDB->exec('SELECT * FROM sites WHERE is_active = 1');
        $inactiveSites = $sicDB->exec('SELECT * FROM sites WHERE is_active = 0');
        $users = $sicDB->exec('SELECT * FROM users');

        $test=new Test;

        // SIC TESTS
        $test->expect(
            count($this->sic->getAllSites()) == count($allSites),
            'SIC getAllSites() returns as many sites as queried from database'
        );
        $test->expect(
            count($this->sic->getActiveSites()) == count($activeSites),
            'SIC getActiveSites() returns as many sites as queried from database'
        );
        $test->expect(
            count($this->sic->getInactiveSites()) == count($inactiveSites),
            'SIC getInactiveSites() returns as many sites as queried from database'
        );
        $test->expect(
            count($this->sic->getUsers()) == count($users),
            'SIC getUsers() returns as many users as queried from database'
        );
        $test->expect(
            $this->sic->migrateSitesConfig() === false,
            'SIC migrateSitesConfig() returns false if no site-config.php is found (test assumes there is no such file)'
        );
        $test->expect(
            is_array($this->sic->getCurrentUser()) === true,
            'SIC getCurrentUser() returns array'
        );

        // SATGEN TESTS
        // trigger satellite download from GitHub and get available system identifiers
        $satgen = new SicSatGen($this->f3, 0);
        $availableSys = $satgen->getAvailableSystemIdentifiers();
        $cacheFileInfo = $satgen->getCachedFileInfo();
        $test->expect(
            is_array($availableSys) && count($availableSys)>0 === true,
            'SATGEN getAvailableSystemIdentifiers() returns array with at least one element'
        );
        $test->expect(
            is_array($availableSys) && array_key_exists('PROCESSWIRE', $availableSys) === true,
            'SATGEN getAvailableSystemIdentifiers() returned array contains key "PROCESSWIRE"'
        );
        $test->expect(
            is_array($cacheFileInfo) && count($cacheFileInfo)>0 === true,
            'SATGEN getCachedFileInfo() returns array with at least one element'
        );
        $test->expect(
            is_array($cacheFileInfo) && array_key_exists('filesize',$cacheFileInfo) === true,
            'SATGEN getCachedFileInfo() returned array contains key "filesize" and filesize is '. $cacheFileInfo['filesize']
        );





        $this->f3->set('tplTestResults',$test->results());
        $this->f3->set('tplPagetitle','Tests');
        $this->f3->set('tplHeadline','SIC Tests');
        $this->f3->set('tplPartial','core/views/tests.html');
        echo \Template::instance()->render('core/views/_base.html');

    }
}