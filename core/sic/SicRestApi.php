<?php
class SicRestApi{
    private $f3;
    public function __construct($f3){
        $this->f3 = $f3;
    }

    public function apiV1SitesRouteGet(){
        if(!$this->f3->get('sic')->checkLogin(false)){
            // not logged in
            http_response_code(401);
            echo "Not logged in";
            return;
        }
        $endpoint = $this->f3->get('PARAMS.endpoint');
        if(!$endpoint || $endpoint == ''){
            // no endpoint given
            http_response_code(405);
            echo "Route not found";
            return;
        }

        switch ($endpoint) {
            // route: /api/v1/sites/getActiveSites
            case 'getActiveSites':
                $activeSites = $this->f3->get('sic')->getActiveSites($removeSecrets = true);
                echo json_encode($activeSites);
                return;

            // route: /api/v1/sites/getInactiveSites
            case 'getInactiveSites':
                $inactiveSites = $this->f3->get('sic')->getInactiveSites($removeSecrets = true);
                echo json_encode($inactiveSites);
                return;

            // route: /api/v1/sites/getActiveSitesSystems
            case 'getActiveSitesSystems':
                $systems = $this->f3->get('sic')->getActiveSitesSystems();
                echo json_encode($systems);
                return;

            // route: /api/v1/sites/checkSummaryFileAndGetUrl
            case 'checkSummaryFileAndGetUrl':
                $url = $this->f3->get('sic')->checkSummaryFileAndGetUrl();
                echo json_encode($url);
                return;

            // route: /api/v1/sites/writeSummaryAndGetUrl
            case 'writeSummaryAndGetUrl':
                $url = $this->f3->get('sic')->writeSummaryAndGetUrl();
                echo json_encode($url);
                return;

            // none of the defined endpoints found
            default:
                http_response_code(405);
                echo "Route not found";
                return;
        }
    }


    public function apiV1SitesRoutePost(){
        if(!$this->f3->get('sic')->checkLogin(false)){
            // not logged in
            http_response_code(401);
            echo "Not logged in";
            return;
        }
        $endpoint = $this->f3->get('PARAMS.endpoint');
        if(!$endpoint || $endpoint == ''){
            // no endpoint given
            http_response_code(405);
            echo "Route not found";
            return;
        }

        // getting data sent via json POST to this file
        $data = json_decode(file_get_contents('php://input'), true);
        switch ($endpoint) {
            // route: /api/v1/sites/getSatelliteResponse
            case 'getSatelliteResponse':
                $siteId =  $data['id'];
                $response = $this->f3->get('sic')->getSatelliteResponse($siteId);
                // save result to file
                if($this->f3->get('sic')->saveToCSV()){
                    $response['history'] = $this->f3->get('sic')->getHistoryRenderUrl($siteId);
                }
                echo json_encode($response);
                return;

            // none of the defined endpoints found
            default:
                http_response_code(405);
                echo "Route not found";
                return;
        }
    }


    public function apiV1HistoryRenderRouteGet(){
        if(!$this->f3->get('sic')->checkLogin(false)){
            // not logged in
            http_response_code(401);
            echo "Not logged in";
            return;
        }
        $siteId = $this->f3->get('PARAMS.siteId');
        if(!$siteId || $siteId == '' || !is_numeric($siteId)){
            // no endpoint given
            http_response_code(405);
            echo "Route not found";
            return;
        }
        $siteName = $this->f3->get('sic')->getAllSites()[$siteId]['name'];
        $targetFile = $this->f3->get('sic')->getCsvSavePath($siteId);
        $downloadUrl = $this->f3->get('sic')->getCsvDownloadUrl($siteId);
        $this->f3->set('tplPagetitle','History');
        $this->f3->set('tplHistorySiteName',$siteName);
        $this->f3->set('tplHistoryDownloadUrl',$downloadUrl);
        $this->f3->set('tplHistoryData',array());
        if (file_exists($targetFile) && is_readable($targetFile)){
            $f = fopen($targetFile, "r");
            $historyData = array();
            while (($line = fgetcsv($f)) !== false) {
                $lineCells = array();
                foreach ($line as $cell) {
                    $lineCells[] = $cell;
                }
                array_push($historyData,$lineCells);
            }
            fclose($f);
            $this->f3->set('tplHistoryData',$historyData);
        }
        echo \Template::instance()->render('core/views/history.html');
        return;
    }

    
    public function apiV1HistoryDownloadRouteGet(){
        if(!$this->f3->get('sic')->checkLogin(false)){
            // not logged in
            http_response_code(401);
            echo "Not logged in";
            return;
        }
        $siteId = $this->f3->get('PARAMS.siteId');
        // NOTE: siteId can be 0, which means the summary file
        // therefore we omit the !$siteId check (wich would be true for siteId = 0)
        if($siteId == '' || !is_numeric($siteId)){
            // no endpoint given
            http_response_code(405);
            echo "Route not found";
            return;
        }
        $targetFile = $this->f3->get('sic')->getCsvSavePath($siteId);
        if($siteId == 0){
            // if "0" provided as site id, we provide the summary file
            $targetFile = $this->f3->get('sic')->getSummaryFilePath();
        }

        if (file_exists($targetFile) && is_readable($targetFile)){
            header('Content-Type: application/csv');
            header('Content-Disposition: attachment; filename="'.basename($targetFile).'"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            readfile($targetFile);
            return;
        }
        http_response_code(404);
        echo "File not found";
        return;
    }

    public function apiV1PhpInfoRouteGet(){
        if(!$this->f3->get('sic')->checkLogin(false)){
            // not logged in
            http_response_code(401);
            echo "Not logged in";
            return;
        }
        $siteId = $this->f3->get('PARAMS.siteId');
        if(!$siteId || $siteId == '' || !is_numeric($siteId)){
            // no endpoint given
            http_response_code(405);
            echo "Route not found";
            return;
        }
        $url = $this->f3->get('sic')->getAllSites()[$siteId]['url'];

        // set payload and url for request
        $payload = array(
            'sys' => $this->f3->get('sic')->getAllSites()[$siteId]['sys'],
            'secret' => $this->f3->get('sic')->getAllSites()[$siteId]['secret'],
            'action' => 'PHPINFO'
        );

        $response = $this->f3->get('sic')->sendPostRequest($url,$payload);
        if(isset($response['statuscode']) && $response['statuscode'] == 200){
            echo $response['response'];
        }

        return;
    }

}