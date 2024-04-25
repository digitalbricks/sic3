<?php
class SicUpdate extends SicUiViews{
    private $f3;
    private $githubUrl = "https://raw.githubusercontent.com/digitalbricks/sic3/main/core/init.php";
    private $cacheFilePath;
    private $cacheDuration = 86400; // in seconds

    private $latestVersion = null;
    public function __construct($f3) {
        $this->f3 = $f3;
        $this->cacheFilePath = realpath(__DIR__).'/../../'.$f3->get('TEMP').'/update-init.php.txt';
        $this->latestVersion = $this->getVersionNumberFromGithub();
        parent::__construct($f3);
    }

    /**
     * Renders JSON for route /updates/check
     * @return string
     */
    public function updateCheckRouteGet(){
        $installedVersion = $this->f3->get('tplSicVersion');
        $latestVersion = $this->latestVersion;
        $updateUrl = '';

        // if admin, populate $updateUrl
        if($this->f3->get('userLoggedIn') && $this->f3->get('userIsAdmin')){
            $updateUrl = '/update';
        }

        $json = array(
            'installedVersion' => $installedVersion,
            'latestVersion' => $latestVersion,
            'updateAvailable' => version_compare($installedVersion, $latestVersion, '<'),
            'updateUrl' => $updateUrl,
        );
        header('Content-Type: application/json');
        echo json_encode($json);
        return true;
    }

    public function updateInfoRouteGet(){
        $this->f3->get('sic')->checkLogin(true);
        $installedVersion = $this->f3->get('tplSicVersion');
        $latestVersion = $this->latestVersion;
        $updateAvailable = version_compare($installedVersion, $latestVersion, '<');

        $this->f3->set('tplInstalledVersion', $installedVersion);
        $this->f3->set('tplLatestVersion', $latestVersion);
        $this->f3->set('tplUpdateAvailable', $updateAvailable);

        $this->f3->set('tplPagetitle','SIC Update');
        $this->f3->set('tplPartial','core/views/update.html');
        echo $this->f3->get('BASE');
        echo \Template::instance()->render('core/views/_base.html');

    }


    /**
     * Returns the version number of the latest SIC version from github
     * by downloading the file and extracting the version number.
     *
     * @return string
     */
    private function getVersionNumberFromGithub(){
        $cacheLimit = time() - $this->cacheDuration;

        // download the file if it doesn't exist or is older than cacheDuration
        if(!file_exists($this->cacheFilePath) || filemtime($this->cacheFilePath) < $cacheLimit){
            $ch = curl_init($this->githubUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $data = curl_exec($ch);
            curl_close($ch);

            $file = fopen($this->cacheFilePath, "w+");
            fputs($file, $data);
            fclose($file);
        }

        // get the version number from the downloaded file
        $fileContent = file_get_contents($this->cacheFilePath);

        // Regular expression to match the version number
        $pattern = "/'tplSicVersion',\s*'([\d\.]+)'/";

        // Use preg_match to find the version number
        preg_match($pattern, $fileContent, $matches);


        $version = null;
        if (count($matches) > 1) {
            // The version number is in the second element of the $matches array
            $version = $matches[1];
        }

        return $version;
    }
}