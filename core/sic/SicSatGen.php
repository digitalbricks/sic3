<?php
class SicSatGen{

    private $f3;
    private $sic;
    private $githubUrl = "https://raw.githubusercontent.com/digitalbricks/sic-satellite/master/satellite.php";
    private $cacheFilePath;
    private $cacheDuration = 86400; // in seconds
    private array $site;

    public function __construct(Base $f3, int $siteId)
    {
        $this->f3 = $f3;
        $this->sic = $f3->get('sic');
        if($siteId != 0){ // 0 = new, not-yet-existing site
            $this->site = $this->sic->getSite($siteId);
        }
        $this->cacheFilePath = realpath(__DIR__).'/../../'.$f3->get('TEMP').'satellite.php.txt';
        $this->downloadFromGithub();
    }

    
    /**
     * Downloads the satellite file from github and stores it in /temp
     * until cacheDuration is reached.
     * @return void
     */
    private function downloadFromGithub(){
        $cacheLimit = time() - $this->cacheDuration;
        if(!file_exists($this->cacheFilePath) || filemtime($this->cacheFilePath) < $cacheLimit){
            $ch = curl_init($this->githubUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $data = curl_exec($ch);
            curl_close($ch);

            $file = fopen($this->cacheFilePath, "w+");
            fputs($file, $data);
            fclose($file);
        }
    }

    
    /**
     * Loads settings for sat_contact and replaces the
     * placeholders in that file with the data configured for the current site
     * @return void
     */
    private function replacePlaceholder(){
        $secret = $this->site['secret'];
        $sat_contact = $this->f3->get('sic')->getSettings()['sat_contact'];
        // prepend all lines with * for comment
        $sat_contact_prepared = preg_replace('/^/m', " * ", $sat_contact);

        $satelliteContent = file_get_contents($this->cacheFilePath);
        $replacedContent = str_replace('[YOUR_SECRET]',$secret,$satelliteContent);
        // NOTE: we seach vor " * [YOUR_CONTACT_INFORMATION]" because we prepended all lines with * for comment
        $replacedContent = str_replace(' * [YOUR_CONTACT_INFORMATION]',$sat_contact_prepared,$replacedContent);
        return $replacedContent;
    }

    
    /**
     * Returns code of satellite file with replace placeholders
     * @return string
     */
    public function getSatelliteContent(){
        $code = $this->replacePlaceholder();
        return $code;
    }

    
    /**
     * Returns some information about the cached file
     * @return array
     */
    public function getCachedFileInfo(): array
    {
        return [
            'filemtime' => filemtime($this->cacheFilePath),
            'filesize' => filesize($this->cacheFilePath),
            'cacheDuration' => $this->cacheDuration,
            'cacheExpires' => filemtime($this->cacheFilePath) + $this->cacheDuration
        ];
    }

    /**
     * Reads available system identifiers from the cached satellite file.
     * The identifiers are defined in the comments of the satellite file.
     * @return array
     */
    public function getAvailableSystemIdentifiers(){
        $fileContent = file_get_contents($this->cacheFilePath);
        $tokens = token_get_all($fileContent);
        $keyValuePairs = [];
        foreach ($tokens as $token) {
            if ($token[0] == T_COMMENT || $token[0] == T_DOC_COMMENT) {
                // Check if the comment contains the desired key-value pairs
                $lines = explode("\n", $token[1]);
                foreach ($lines as $line) {
                    if (preg_match('/\*\s*sys=([A-Za-z0-9_]+)\s*\|\s*(.+)/', $line, $matches)) {
                        $identifier = $matches[1];
                        $description = $matches[2];
                        $keyValuePairs[$identifier] = $description;
                    }
                }
            }
        }
        return $keyValuePairs;
    }
}