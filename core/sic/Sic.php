<?php
class Sic {

    private $f3;
    protected $rootPath;
    protected $rootUrl;
    protected $hostname;
    protected $sicDB;
    private $users = array();
    private $summaryfile = '_summary-latest.csv';
    private $summary = array();
    private $allSites = array();
    private $activeSites = array();
    private $inactiveSites = array();
    private $userLoggedIn = false;
    private $userIsAdmin = false;
    private $user = array();
    private $satelliteResponse = false;

    /**
     * @param Base $f3
     */
    public function __construct(Base $f3){
        $this->f3 = $f3;
        $this->rootPath = realpath(__DIR__).'/../..'; // NOTE: We not use F3's "ROOT" by indent to make SIC work from within subfolders
        $this->hostname = $this->f3->get('HOST');
        $this->rootUrl= $this->f3->get('BASE').'/';
        $this->bootstrapStorageDir();
        $this->sicDB = new DB\SQL('sqlite:'.$this->rootPath.'/storage/db/sic.sqlite');

        // read summary file (has to be called before bootstapSite())
        $this->summary = $this->getSummaryFromFile();

        $this->bootstrapUsers();
        $this->bootstrapSites();
        $this->bootstrapSettings();
    }

    /**
     * creates storage dir if not exists and places .htaccess file in it
     * @return void
     */
    private function bootstrapStorageDir(){
        // create storage dir if not exists
        if(!is_dir($this->rootPath.'/storage')){
            mkdir($this->rootPath.'/storage');
        }

        // create storage/db dir if not exists
        if(!is_dir($this->rootPath.'/storage/db')){
            mkdir($this->rootPath.'/storage/db');
        }

        // create storage/history dir if not exists
        if(!is_dir($this->rootPath.'/storage/history')){
            mkdir($this->rootPath.'/storage/history');
        }

        // check if .htaccess exists in storage dir, create if not (preventing access to storage dir)
        if(!file_exists($this->rootPath.'/storage/.htaccess')){
            file_put_contents($this->rootPath.'/storage/.htaccess', 'deny from all');
        }
    }

    /**
     * creates user table if not exists and inserts default admin user if there is no user in the database
     * also sets $this->users
     * @return void
     */
    private function bootstrapUsers(){
        // check if sites table exists, create if not
        $tableFound = $this->sicDB->exec('SELECT name FROM sqlite_master WHERE type="table" AND name="users"');
        if(count($tableFound) == 0){
            // create users table if not exists
            $this->sicDB->exec('CREATE TABLE IF NOT EXISTS users 
                (
                    id INTEGER PRIMARY KEY AUTOINCREMENT, 
                    username TEXT, 
                    email TEXT, 
                    pwhash TEXT, 
                    is_admin INTEGER DEFAULT 0,
                    is_active INTEGER DEFAULT 0,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    created_by INTEGER
                )'
            );

            // add default admin user (admin/admin) if there is no user in the database
            $defaultAdminUsername = $this->f3->get('defaultAdminUsername');
            $defaultAdminPassword = $this->f3->get('defaultAdminPassword');
            $this->sicDB->exec('INSERT INTO users (username,email,pwhash,is_admin,is_active,created_by) 
                VALUES ("'.$defaultAdminUsername.'","mail@example.com","'.password_hash($defaultAdminPassword, PASSWORD_DEFAULT).'",1,1,0)');
        }

        $users = $this->sicDB->exec('SELECT * FROM users');

        // create new array from $users with $userId as key
        foreach ($users as $user){
            $this->users[$user['id']] = $user;
        }

        if(!$this->f3->get('SESSION.userId') || $this->f3->get('SESSION.userId') == 0){
            $this->logoutUser();
        } else {
            $this->loginUser($this->f3->get('SESSION.userId'));
        }
    }


    /**
     * creates sites table if not exists
     * also merges data from $this->summary
     * and sets $this->allSites, $this->activeSites, $this->inactiveSites
     * @return void
     */
    private function bootstrapSites(){
        // check if sites table exists, create if not
        $tableFound = $this->sicDB->exec('SELECT name FROM sqlite_master WHERE type="table" AND name="sites"');
        if(count($tableFound) == 0){
            // bootstrapping sites table
            $this->sicDB->exec('CREATE TABLE IF NOT EXISTS sites 
                (
                    id INTEGER PRIMARY KEY AUTOINCREMENT, 
                    name TEXT, 
                    url TEXT, 
                    sys TEXT, 
                    secret TEXT, 
                    is_active INTEGER
                    site_url TEXT,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    created_by INTEGER,
                    link TEXT
                )'
            );
        }

        $allSitesRaw = $this->sicDB->exec('SELECT * FROM sites ORDER BY name ASC');

        // loop through all sites and add additional fields with default values
        foreach ($allSitesRaw as $site){
            // -- set defaults
            $site['sys_ver'] = 'n/a';
            $site['php_ver'] = 'n/a';
            $site['sat_ver'] = 'n/a';
            $site['time'] = "";
            $site['date'] = "";
            $site['history'] = false;
            $site['satPhpinfo'] = false;

            // store site in $allSites array
            $this->allSites[$site['id']] = $site;
        }

        // loop through all sites and sort them into active and inactive
        // also checks if history file exists, sets the url to the history file
        // and adds latest data to the site (last line from history file)
        foreach ($this->allSites as $id=>$site) {
            $historyFile = $this->getCsvSavePath($id);
            $history = false;
            if(file_exists($historyFile)){
                $history = $this->getHistoryRenderUrl($id);
            }
            $site['history'] = $history;

            $latestData = $this->getLatestResult(intval($site['id']));
            if($latestData){
                if(array_key_exists('sys_ver', $latestData)){
                    $site['sys_ver'] = $latestData['sys_ver'];
                }
                if(array_key_exists('php_ver', $latestData)){
                    $site['php_ver'] = $latestData['php_ver'];
                }
                if(array_key_exists('sat_ver', $latestData)){
                    $site['sat_ver'] = $latestData['sat_ver'];
                    // check satellite version and set satPhpinfo url if >= 1.0.0
                    if(version_compare($latestData['sat_ver'], '1.0.0', '>=')){
                        $site['satPhpinfo'] = $this->getPhpInfoUrl($id);
                    }
                }
                if(array_key_exists('time', $latestData)){
                    $site['time'] = $latestData['time'];
                }
                if(array_key_exists('date', $latestData)){
                    $site['date'] = $latestData['date'];
                }
            }


            // demo mode? get random site name from demo hostnames array
            // (used for taking screenshots, demo mode is set in core/init.php)
            if($this->f3->get('sicDemoMode')){
                $demoHostnames = $this->f3->get('sicDemoModeHostnames');
                $randomKey = mt_rand(0,count($demoHostnames)-1);
                $randomSubdomain = $this->randomWords(1, 6);
                $site['name'] = $randomSubdomain.".".$demoHostnames[$randomKey];
            }

            // update current sub-array in allSites
            $this->allSites[$id] = $site;

            // sort into active and inactive
            if ($site['is_active'] == 1) {
                $this->activeSites[$id] = $site;
            } else {
                $this->inactiveSites[$id] = $site;
            }
        }
    }


    /**
     * Creates settings table if not exists
     * and sets  default settings
     * @return void
     */
    private function bootstrapSettings(){
        // check if settings table exists, return if it does
        $tableFound = $this->sicDB->exec('SELECT name FROM sqlite_master WHERE type="table" AND name="settings"');
        if(count($tableFound) > 0){ return; }

        // bootstrapping settings table
        $this->sicDB->exec('CREATE TABLE IF NOT EXISTS settings 
                ( 
                    name TEXT, 
                    value TEXT
                )'
        );

        // create (blank) setting for sat_contact (if not exists
        $sat_contact = $this->sicDB->exec('SELECT value FROM settings WHERE name = "sat_contact"');
        if(count($sat_contact) == 0){
            $this->sicDB->exec('INSERT INTO settings (name,value) VALUES ("sat_contact","")');
        }
    }


    /**
     * migrates sites-config.php to database
     * @return bool|int number of imported sites or false if file not found
     */
    public function migrateSitesConfig(){
        $migration_type = null;
        $filepath_php = $this->rootPath.'/sites-config.php';
        $filepath_json = $this->rootPath.'/storage/sites-config.json';

        // check if sites-config.php or sites-config.json exists
        if(file_exists($filepath_php) && is_readable($filepath_php)){
            $migration_type = 'php';
        } elseif (file_exists($filepath_json) && is_readable($filepath_json)){
            $migration_type = 'json';
        } else {
            return false;
        }

        // get current user id
        $userId = 0;
        if(array_key_exists('id', $this->user)){
            $userId = $this->user['id'];
        }


        // PHP migration
        if($migration_type == 'php'){

            // load sites-config.php, providing $sites variable
            require_once $filepath_php;

            // sort array
            // @var $sites array loaded from included config file
            ksort($sites);

            $dbCommands = array();
            $imported = 0;
            foreach($sites as $key => $value){
                // prepare key 'inact' for db field 'is_active' by converting boolean to integer
                $is_active = ($value['inact'] == true) ? 0 : 1;
                $dbCommands[] = 'INSERT INTO sites (name,url,sys,secret,is_active,created_by) 
                VALUES ("'.$key.'","'.$value['url'].'","'.$value['sys'].'","'.$value['secret'].'",'.$is_active.','.$userId.')';
                $imported++;
            }

            $commit = $this->sicDB->exec($dbCommands);
            if($commit==1){
                // delete sites-config.php
                unlink($filepath_php);
                return $imported;
            }
            return false;
        }


        // JSON migration
        if($migration_type == 'json'){
            $json = file_get_contents($filepath_json);
            $sites = json_decode($json, true);
            $dbCommands = array();
            $imported = 0;
            foreach($sites as $key => $value){
                $dbCommands[] = 'INSERT INTO sites (name,url,sys,secret,is_active,created_by) 
                VALUES ("'.$value['name'].'","'.$value['url'].'","'.$value['sys'].'","'.$value['secret'].'",'.$value['is_active'].','.$userId.')';
                $imported++;
            }

            $commit = $this->sicDB->exec($dbCommands);
            if($commit==1){
                // delete sites-config.json
                unlink($filepath_json);
                return $imported;
            }
            return false;
        }


        return false;






    }


    /**
     * Checks if user is logged in and reroutes to login page if not (optional).
     * Can also check if user is admin and reroute to login page if not (optional).
     * @param bool $reroute
     * @param bool $admins_only
     * @return bool
     */
    public function checkLogin(bool $reroute = true, $admins_only = false){
        if(!$this->userLoggedIn){
            if($reroute){
                $this->f3->reroute('/login');
            }
            return false;
        };

        if($admins_only){
            if(!$this->userIsAdmin && $reroute){
                $this->f3->reroute('/login');
            } elseif(!$this->userIsAdmin && !$reroute){
                return false;
            }
        }
        return true;
    }


    /**
     * checks given credentials against database
     * @param string $username
     * @param string $password
     * @return bool
     */
    public function  checkCredentials(string $username, string $password){
        $user = $this->sicDB->exec('SELECT * FROM users WHERE username="'.$username.'"');
        if(count($user) == 1){
            if(password_verify($password, $user[0]['pwhash'])){
                if($user[0]['is_active'] == 0){
                    return false;
                }
                $this->loginUser($user[0]['id']);
                return true;
            }
        }
        $this->logoutUser();
        return false;
    }


    /**
     * sets user, session variable and SIC property for logged in user
     * @return void
     */
    private function loginUser(int $userId){
        $user = $this->sicDB->exec('SELECT * FROM users WHERE id="'.$userId.'"');
        if(count($user) == 1){
            $this->user = $user[0];
            $this->f3->set('SESSION.userId',$user[0]['id']);
            $this->userLoggedIn = true;
            $this->f3->set('userLoggedIn',$this->userLoggedIn);

            // check if user is admin
            if($user[0]['is_admin'] == 1){
                $this->userIsAdmin = true;
            }
            $this->f3->set('userIsAdmin',$this->userIsAdmin);
            return;
        }
        // else: userId not found, logout user
        $this->logoutUser();
    }


    /**
     * sets session variable and SIC property for logged out user
     * @return void
     */
    public function logoutUser(){
        $this->f3->set('SESSION.userId',false);
        $this->f3->set('SESSION.skipMigrate',false);
        $this->userLoggedIn = false;
        $this->f3->set('userLoggedIn',false);
        $this->f3->set('userIsAdmin',false);
    }


    /**
     * returns true if user is logged in
     * @return bool
     */
    public function isUserLoggedIn(): bool
    {
        return $this->userLoggedIn;
    }


    /**
     * returns currently logged in user
     * @return array
     */
    public function getCurrentUser(): array
    {
        return $this->user;
    }


    /**
     * returns all users
     * @return array
     */
    public function getUsers(): array
    {
        return $this->users;
    }


    /**
     * Returns data for a given user (id)
     *
     * @param int $id
     * @return false|array
     */
    public function getUser(int $id){
        if(array_key_exists($id,$this->users)){
            return $this->users[$id];
        } else {
            return false;
        }
    }


    /**
     * returns all sites
     * @return array
     */
    public function getAllSites(){
        return $this->allSites;
    }


    /**
     * returns username of the logged in user IF NO $userId is given
     * or returns username of the user with the given $userId
     * @param int|null $userId
     * @return string
     */
    public function getUserName(int $userId = null){
        // no userId given, return username of logged in user
        if(!$userId && array_key_exists('username',$this->user)){
            return $this->user['username'];
        }

        // userId given, query db and return username of user with given userId
        if($userId){
            $userName = $this->sicDB->exec('SELECT * FROM users WHERE id="'.$userId.'"');
            if($userName && count($userName) == 1){
                return $userName[0]['username'];
            }
            return 'unknown';
        }

        if($userId==0){
            return 'system';
        }
        return 'unknown';
    }


    /**
     * returns active sites only
     * @return array
     */
    public function getActiveSites($removeSecrets = false){
        if($removeSecrets){
            return $this->removeSitesSecrets($this->activeSites);
        }
        return $this->activeSites;
    }


    /**
     * returns inactive sites only
     * @return array
     */
    public function getInactiveSites($removeSecrets = false){
        if($removeSecrets){
            return $this->removeSitesSecrets($this->inactiveSites);
        }
        return $this->inactiveSites;
    }


    /**
     * returns all systems
     * with system name as key and number of sites as value
     * @return array
     */
    public function getActiveSitesSystems(){
        $systems = array();
        foreach($this->activeSites as $site){
            $sys = $site['sys'];
            if(!array_key_exists($sys,$systems)){
                $systems[$sys] = 1;
            } else {
                $systems[$sys]++;
            }
        }
        ksort($systems);
        return $systems;
    }


    /**
     * checks if summary file exists and returns the URL
     * @return false|string
     */
    public function checkSummaryFileAndGetUrl(){
        $targetFile = $this->getSummaryFilePath();
        $fileurl = $this->getCsvDownloadUrl(0);

        // check if summary file exists
        if(file_exists($targetFile)){
            return $fileurl;
        }

        return false;
    }


    /**
     * getSiteName
     *
     * @param  string $id
     * @return string|false site name or false
     */
    public function getSiteName(int $id){
        if(array_key_exists($id,$this->allSites)){
            return $this->allSites[$id]['name'];
        } else {
            return false;
        }
    }


    /**
     * Returns data for a given site (id)
     *
     * @param int $id
     * @return false|array
     */
    public function getSite(int $id){
        if(array_key_exists($id,$this->allSites)){
            return $this->allSites[$id];
        } else {
            return false;
        }
    }


    /**
     * writeSummaryAndGetUrl
     *
     * @param  bool $json JSON (true) output or plain text (false)
     * @return mixed JSON or plain text or bool (false)
     */
    public function writeSummaryAndGetUrl(){
        $sites = $this->getActiveSites();
        $targetFile = $this->getSummaryFilePath();
        $paths = array();

        // get array of all existing history files for active sites
        foreach($sites as $id=>$value){
            $path = $this->getCsvSavePath($id);
            if(file_exists($path)){
                $paths[$id] = $path;
            }
        }

        // read last lines from each history file
        $lines = "";
        $index = 1;
        foreach($paths as $hash=>$file){
            $name = $this->getSiteName($hash);
            // replace csv critical chars from $name
            // (we don't need to care about $data, because this data was already from CSV)
            $name = str_replace('"','',$name);
            $name = str_replace(',',' ',$name);
            $name = '"'.$name.'"';

            // get last line from site history file
            $data = $this->tailCustom($file);

            // store line in $lines, add line break (PHP_EOL) before each line
            // but not before the first line
            if($index!=1){
                $prefix = PHP_EOL;
            } else {
                $prefix = "";
            }
            $lines.= $prefix.$name.','.$data;

            $index++;
        };

        // create summary file
        if($lines!=""){

            // delete old summary file
            if (file_exists($targetFile)) {
                unlink($targetFile);
            }

            // open summary file
            $fp = fopen($targetFile, 'w');

            // create table header in CSV
            fputcsv($fp,array('Site','System','Sys Ver','PHP Ver','Sat Ver', 'Date', 'Time'));

            // write lines
            fwrite($fp, $lines);
            fclose($fp);
        }

        // check if summary file exists and get url
        return $this->checkSummaryFileAndGetUrl();
    }


    /**
     * @return string
     */
    public function getSummaryFilePath(){
        return $this->rootPath."/storage/history/".$this->summaryfile;
    }


    /**
     * getCSVFileName
     *
     * @source https://stackoverflow.com/questions/2021624/string-sanitizer-for-filename
     * @param  string $filename
     * @return string sanitized filename
     */
    private function getCsvFileName(string $filename){
        return mb_ereg_replace("([^\w\s\d\-_~,;\[\]\(\).])", '', $filename).".csv";
    }


    /**
     * reads summary file and returns array of its content
     * @return array
     */
    public function getSummaryFromFile(){
        $targetFile = $this->rootPath."storage/history/".$this->summaryfile;
        $csvArray = array();
        if(file_exists($targetFile) && is_readable($targetFile)){
            $handle = fopen($targetFile, "r");
            // skip first line (moving pointer forward)
            fgetcsv($handle);
            while (! feof($handle)) {
                $csvArray[] = fgetcsv($handle, 1000, ',');
            }
        }

        // create a new array with site name as array key
        $newArray = array();
        foreach($csvArray as $row){
            $newArray[$row[0]] = array(
                'sys' => $row[1],
                'sys_ver' => $row[2],
                'php_ver' => $row[3],
                'sat_ver' => $row[4],
                'date' => $row[5],
                'time' => $row[6]
            );
        }

        return $newArray;
    }


    /**
     * getSatelliteResponse
     *
     * @param  int $id site id
     * @return string|array|false array, false if failed
     */
    public function getSatelliteResponse(int $id){
        if(array_key_exists($id,$this->activeSites)){
            // set payload and url for request
            $payload = array(
                'sys' => $this->activeSites[$id]['sys'],
                'secret' => $this->activeSites[$id]['secret']
            );
            $url = $this->activeSites[$id]['url'];
            $sitename = $this->activeSites[$id]['name'];

            $response = $this->sendPostRequest($url,$payload);
            if($response AND array_key_exists('statuscode', $response)){

                // HTTP statuscode 200 (ok)
                if($response['statuscode']==200){
                    $response['message'] = "OK";
                    $response['time'] = date('H:i:s');
                    $response['date'] = date('d.m.Y');
                    $response['id'] = $id;
                    $response['name'] = $sitename;

                    // check satellite version in response and set satPhpinfo url if >= 1.0.0
                    $response['satPhpinfo'] = false;
                    $resp_array = json_decode($response['response'], true);
                    if(array_key_exists('sat_ver', $resp_array) && version_compare($resp_array['sat_ver'], '1.0.0', '>=')){
                        $response['satPhpinfo'] = $this->getPhpInfoUrl($id);
                    }

                    // store response for later use
                    $this->satelliteResponse = array(
                        'id' => $id,
                        'response' => $response['response'],
                        'date' => $response['date'],
                        'time' => $response['time']
                    );
                    // HTTP statuscode 403 (forbidden)
                } elseif($response['statuscode']==403){
                    $response['message'] = "Authorisatzion failed";
                    $response['id'] = $id;
                    $response['name'] = $sitename;
                }
                // HTTP statuscode 404 (not found)
                elseif($response['statuscode']==404){
                    $response['message'] = "Satellite not found";
                    $response['id'] = $id;
                    $response['name'] = $sitename;
                }
                // other HTTP response codes
                else{
                    $response['message'] = "Server answered with HTTP status code {$response['statuscode']}";
                    $response['id'] = $id;
                    $response['name'] = $sitename;
                }
                return $response;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }


    /**
     * sendPostRequest
     *
     * Sends a POST request to a given URI
     *
     * @param string $url The destination URI
     * @param array $data Array of POST data (key => value)
     *
     * @return array|false Response from given URL and statuscode or false
     */
    private function sendPostRequest($url,$data){
        if(function_exists(('curl_version'))){
            $curl = curl_init();
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1); //causes no output without echo
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false); // disable verfication of authenticity of the peer's certificate
            curl_setopt($curl, CURLOPT_URL, $url);
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);

            $response['response'] = curl_exec($curl);
            $response['statuscode'] = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);

            return $response;
        } else {
            return false;
        }
    }


    /**
     * saveToCSV
     *
     * @return boolean
     */
    public function saveToCSV(){
        if($this->satelliteResponse AND is_array($this->satelliteResponse)){
            // set target file
            $targetFile = $this->getCsvSavePath($this->satelliteResponse['id']);

            // get latest satellite response stored in object
            $satdata = json_decode($this->satelliteResponse['response'], true);

            // open / create & open file
            if (file_exists($targetFile)) {
                // open file if already exisits
                $fh = fopen($targetFile, 'a');
            } else {
                // create file and open if not already in place
                $fh = fopen($targetFile, 'w');
                // create table header in CSV
                fputcsv($fh,array('System','Sys Ver','PHP Ver','Sat Ver', 'Date', 'Time'));
            }

            // prepare data for csv
            // - fallbacks
            $sys_ver = "n/a";
            $php_ver = "n/a";
            $sat_ver = "n/a";
            $sys = "n/a";

            // - overwrite fallbacks if there is data
            if(isset($satdata['sys_ver']) AND $satdata['sys_ver']!=''){
                $sys_ver = $satdata['sys_ver'];
            };
            if(isset($satdata['php_ver']) AND $satdata['php_ver']!=''){
                $php_ver = $satdata['php_ver'];
            };
            if(isset($satdata['sat_ver']) AND $satdata['sat_ver']!=''){
                $sat_ver = $satdata['sat_ver'];
            };
            $system = $this->allSites[$this->satelliteResponse['id']]['sys'];
            if($system AND $system!=''){
                $sys = $system;
            };

            // write data to CSV;
            fputcsv($fh,array($sys ,$sys_ver, $php_ver, $sat_ver, date('d.m.Y'), date('H:i:s')));

            // close file handle
            fclose($fh);

            return true;
        }
        return false;
    }


    /**
     * getHistoryRenderUrl
     *
     * @param  int $id
     * @return string|void relative URL of the history renderer
     */
    public function getHistoryRenderUrl(int $id){
        return $this->rootUrl."api/v1/history/render/".$id;
    }

    /**
     * getPhpInfoUrl
     *
     * @param  int $id
     * @return string|void relative URL of the phpinfo route
     */
    public function getPhpInfoUrl(int $id){
        return $this->rootUrl."api/v1/phpinfo/".$id;
    }


    /**
     * getCsvSavePath
     *
     * @param  int $id
     * @return string|void server save path of CSV file
     */
    public function getCsvSavePath(int $id){
        if(array_key_exists($id, $this->allSites)){
            $sitename = $this->allSites[$id]['name'];;
            $filename = $this->getCsvFileName($sitename);
            return $this->rootPath.'/storage/history/'.$filename;
        }
    }


    /**
     * returns the url of the csv downloader
     * NOTE: the special case with $id = 0 is handled
     * in SicViews.php for downloading the summary file.
     *
     * @param int $id
     * @return string
     */
    public function getCsvDownloadUrl(int $id = 0){
        return $this->rootUrl."api/v1/history/download/".$id;
    }


    /**
     * getLatestResult
     *
     * @param  string $sitename
     * @return mixed array or bool (false)
     *
     * NOTE: We're using sitename innstead hash here because
     * hash is not yet available when this method is called
     * in the class constructor.
     */
    public function getLatestResult(int $id){
        // get path to history file
        $file = $this->getCsvSavePath($id);

        if(file_exists($file)){
            // read last line from history file
            $data_csv = $this->tailCustom($file);
            $data_arr = str_getcsv($data_csv);

            // check if expected columns exists (skip if not)
            if(!array_key_exists(0,$data_arr) OR
                !array_key_exists(1,$data_arr) OR
                !array_key_exists(2,$data_arr) OR
                !array_key_exists(3,$data_arr) OR
                !array_key_exists(4,$data_arr)
            ){
                return false;
            }

            // create array
            $array=array(
                'sys' => $data_arr[0],
                'sys_ver' => $data_arr[1],
                'php_ver' => $data_arr[2],
                'sat_ver' => $data_arr[3],
                'date' => $data_arr[4],
                'time' => $data_arr[5],
            );

            return $array;
        }
        return false;
    }


    /**
     * @param int $id 0 for new site
     * @param string $name
     * @param string $link
     * @param string $sys
     * @param string $url
     * @param string $secret
     * @param int $active
     * @return false|int id of new/updated record or false
     */
    public function saveSite(
        int $id,
        string $name,
        string $link,
        string $sys,
        string $url,
        string $secret,
        int $active)
    {
        // check if site with id already exists
        $site = $this->sicDB->exec('SELECT * FROM sites WHERE id = '.$id);

        // site does not exist, create new
        if(count($site)==0){
            $userId = $this->user['id'];
            $newSite = $this->sicDB->exec(
                'INSERT INTO sites (name,url,sys,secret,is_active,created_by,link) 
                VALUES ("'.$name.'","'.$url.'","'.$sys.'","'.$secret.'",'.$active.','.$userId.',"'.$link.'")'
            );

            // check if record was created
            if(!$newSite){return false;}
            // return id of new record
            return $this->sicDB->lastInsertId();

        }

        // if site exists, update
        $updatedSite = $this->sicDB->exec(
            'UPDATE sites SET 
                    name="'.$name.'", 
                    url="'.$url.'",
                    sys="'.$sys.'",
                    secret="'.$secret.'",
                    is_active='.$active.',
                    updated_at="'.date('Y-m-d H:i:s').'", 
                    link="'.$link.'"
                    WHERE id='.$id
        );
        if(!$updatedSite or $updatedSite==0){return false;}
        // NOTE: here we return the $id given to the function
        // because on update the DB returns the number of affected rows (which should be 1)
        // and there is no way to get the id of the updated record (as far as I know)
        return $id;
    }


    /**
     * @param int $id
     * @return false|int ID of deleted record or false
     */
    public function deleteSite(int $id){
        $deletedSite = $this->sicDB->exec('DELETE FROM sites WHERE id = '.$id);
        var_dump($deletedSite);
        if(!$deletedSite or $deletedSite==0){return false;}
        return $id;
    }


    /**
     * @param int $id 0 for new user
     * @param string $username
     * @param string $email
     * @param string $password
     * @param int $admin
     * @param int $active
     * @return false|int
     */
    public function saveUser(
        int $id,
        string $username,
        string $email,
        string $password,
        int $admin,
        int $active)
    {
        // check if user with id already exists
        $user = $this->sicDB->exec('SELECT * FROM users WHERE id = '.$id);

        // user does not exist, create new
        if(count($user)==0){
            // check if password is set and long enough
            if($password=='' || strlen($password)<8){return false;}
            $userId = $this->user['id']; // creator of entry
            $newUser = $this->sicDB->exec(
                'INSERT INTO users (username,email,pwhash,is_admin,is_active,created_by) 
                VALUES ("'.$username.'","'.$email.'","'.password_hash($password, PASSWORD_DEFAULT).'",'.$admin.','.$active.','.$userId.')'
            );

            // check if record was created
            if(!$newUser){return false;}
            // return id of new record
            return $this->sicDB->lastInsertId();

        }

        // if username or email is not unique, return false
        if(!$this->checkUsernameAndEmailUnique($username,$email,$id)){
            return false;
        }

        // if not returned yet, update existing user
        // check if password is set and long enough
        if($password!='' && strlen($password)>=8){
            // if password is set and long enough we update the record WITH password
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $updatedUser = $this->sicDB->exec(
                'UPDATE users SET 
                    username="'.$username.'", 
                    email="'.$email.'",
                    pwhash="'.$passwordHash.'",
                    is_admin='.$admin.',
                    is_active='.$active.',
                    updated_at="'.date('Y-m-d H:i:s').'"
                    WHERE id='.$id
            );
        }else{
            // if password is not set or not long enough we update the record WITHOUT password
            $updatedUser = $this->sicDB->exec(
                'UPDATE users SET 
                    username="'.$username.'", 
                    email="'.$email.'",
                    is_admin='.$admin.',
                    is_active='.$active.',
                    updated_at="'.date('Y-m-d H:i:s').'" 
                    WHERE id='.$id
            );
        }

        if(!$updatedUser or $updatedUser==0){return false;}
        // NOTE: here we return the $id given to the function
        // because on update the DB returns the number of affected rows (which should be 1)
        // and there is no way to get the id of the updated record (as far as I know)
        return $id;
    }


    /**
     * @param int $id
     * @return false|int ID of deleted record or false
     */
    public function deleteUser(int $id){
        // prevent deleting own user
        $currentUser = $this->getCurrentUser();
        if(!$currentUser){return false;}
        if($currentUser['id']==$id){return false;}

        $deletedUser = $this->sicDB->exec('DELETE FROM users WHERE id = '.$id);
        if(!$deletedUser or $deletedUser==0){return false;}
        return $id;
    }


    /**
     * Checks if username and email are unique (not present in DB yet).
     * If excludeId is set, the record with that id is excluded from the check.
     * This is useful when updating a record, because the record itself will be found in the DB.
     *
     * @param string $username
     * @param string $email
     * @param int $excludeId
     * @return bool
     */
    function checkUsernameAndEmailUnique(string $username, string $email, int $excludeId=0){
        // Query string something like:
        // SELECT * FROM users WHERE (username = "lorem" OR email = "mail@example.de") AND NOT id = 1
        $user = $this->sicDB->exec('SELECT * FROM users WHERE (username = "'.$username.'" OR email = "'.$email.'") AND id != '.$excludeId);
        if(count($user)>0){return false;}
        return true;
    }


    /**
     * tailCustom
     *
     * Gets the last line of a given file
     * Slightly modified version of http://www.geekality.net/2011/05/28/php-tail-tackling-large-files/
     * @author Torleif Berger, Lorenzo Stanco
     * @link http://stackoverflow.com/a/15025877/995958
     * @license http://creativecommons.org/licenses/by/3.0/
     * @source https://gist.github.com/lorenzos/1711e81a9162320fde20
     *
     * @param string $filepath
     * @param int $lines Number of lines to get (default = 1)
     * @param bool $adaptive Set memory adaptive mode (default = true)
     */
    private function tailCustom(string $filepath, int $lines = 1, bool $adaptive = true){
        // Open file
        $f = @fopen($filepath, "rb");
        if ($f === false) return false;

        // Sets buffer size, according to the number of lines to retrieve.
        // This gives a performance boost when reading a few lines from the file.
        if (!$adaptive) $buffer = 4096;
        else $buffer = ($lines < 2 ? 64 : ($lines < 10 ? 512 : 4096));

        // Jump to last character
        fseek($f, -1, SEEK_END);

        // Read it and adjust line number if necessary
        // (Otherwise the result would be wrong if file doesn't end with a blank line)
        if (fread($f, 1) != "\n") $lines -= 1;

        // Start reading
        $output = '';
        $chunk = '';

        // While we would like more
        while (ftell($f) > 0 && $lines >= 0) {

            // Figure out how far back we should jump
            $seek = min(ftell($f), $buffer);

            // Do the jump (backwards, relative to where we are)
            fseek($f, -$seek, SEEK_CUR);

            // Read a chunk and prepend it to our output
            $output = ($chunk = fread($f, $seek)) . $output;

            // Jump back to where we started reading
            fseek($f, -mb_strlen($chunk, '8bit'), SEEK_CUR);

            // Decrease our line counter
            $lines -= substr_count($chunk, "\n");

        }

        // While we have too many lines
        // (Because of buffer size we might have read too many)
        while ($lines++ < 0) {

            // Find first newline and remove all text before that
            $output = substr($output, strpos($output, "\n") + 1);

        }

        // Close file and return
        fclose($f);
        return trim($output);

    }


    /**
     * Creates a new CSRF token and stores it in the session
     * (using F3 built-in CSRF token generator)
     *
     * @return string
     */
    public function getNewCsrfToken(){
        $token = $this->f3->CSRF;
        $this->f3->set('SESSION.csrf',$token);
        return $token;
    }


    /**
     * Returns the last stored CSRF token
     * (read from session)
     *
     * @return string
     */
    public function getLastStoredCsrfToken(){
        return $this->f3->get('SESSION.csrf');
    }


    /**
     * Checks if the given token matches the last stored token
     * (read from session)
     *
     * @param $token
     * @return bool
     */
    public function checkCsrfToken($token){
        $storedToken = $this->getLastStoredCsrfToken();
        if($token==$storedToken){
            return true;
        }
        return false;
    }


    /**
     * Queries the DB for all settings and returns them as associative array
     * @return array|false
     */
    public function getSettings(){
        $settingsDb = $this->sicDB->exec('SELECT * FROM settings');
        if(count($settingsDb)==0){return false;}
        // transform settings array to associative array
        $settings = [];
        foreach ($settingsDb as $settingsPair){
            $settings[$settingsPair["name"]] = $settingsPair["value"];
        }
        return $settings;
    }


    /**
     * Saves a single setting to the DB
     * @param $name
     * @param $value
     * @return true
     */
    public function saveSetting($name, $value){
        $setting = $this->sicDB->exec('SELECT * FROM settings WHERE name = "'.$name.'"');
        if(count($setting)==0){
            $this->sicDB->exec('INSERT INTO settings (name, value) VALUES ("'.$name.'", "'.$value.'")');
        }else{
            $this->sicDB->exec('UPDATE settings SET value = "'.$value.'" WHERE name = "'.$name.'"');
        }
        return true;
    }

    
    /**
     * Removes site secret and user data from sites array
     * Used on REST API
     * @param array $sites
     * @return array
     */
    private function removeSitesSecrets(array $sites){
        foreach ($sites as $key => $site){
            unset($sites[$key]['secret']);
            unset($sites[$key]['created_at']);
            unset($sites[$key]['updated_at']);
            unset($sites[$key]['created_by']);
        }
        return $sites;
    }

    /**
     * Returns all sites configuration as JSON
     * @return string
     */
    public function getSitesAsJson(){
        $allSitesRaw = $this->sicDB->exec('SELECT name, url, sys, secret, is_active FROM sites ORDER BY name ASC');
        return json_encode($allSitesRaw, JSON_PRETTY_PRINT);
    }

    /**
     * Returns one or more random words taht are human readable
     * @source https://gist.github.com/sepehr/3371339?permalink_comment_id=3665706#gistcomment-3665706
     *
     * @param $words
     * @param $length
     * @return string
     */
    private function randomWords($words = 1, $length = 6)
    {
        $string = '';
        for ($o=1; $o <= $words; $o++)
        {
            $vowels = array("a","e","i","o","u");
            $consonants = array(
                'b', 'c', 'd', 'f', 'g', 'h', 'j', 'k', 'l', 'm',
                'n', 'p', 'r', 's', 't', 'v', 'w', 'x', 'y', 'z'
            );

            $word = '';
            for ($i = 1; $i <= $length; $i++)
            {
                $word .= $consonants[rand(0,19)];
                $word .= $vowels[rand(0,4)];
            }
            $string .= mb_substr($word, 0, $length);
            $string .= "-";
        }
        return mb_substr($string, 0, -1);
    }
}