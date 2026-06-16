<?php

class VersioncheckController extends SicAddon {

    /**
     * Mandatory method to provide basic information about the addon, such as name, description, version and author.
     * @return string[]
     */
    public static function getAddonInfo() {
        return array(
            'name' => 'Versioncheck',
            'description' => 'Checks the version of a CMS agains the latest version available.',
            'version' => '1.1.0',
            'author' => 'Slugger',
            'license' => 'MIT',
            'adminOnly' => false,
            'routes' => array(
                'GET /' => 'overviewRouteGet',
                'GET /api/vc/fetch' => 'apiFetchGet',
                'GET /systems' => 'systemsRouteGet',
                'GET /systems/add' => 'systemsAddRouteGet',
                'POST /systems/add' => 'systemsAddRoutePost',
                'GET /systems/edit/@id' => 'systemsEditRouteGet',
                'POST /systems/edit/@id' => 'systemsEditRoutePost',
                'POST /systems/delete/@id' => 'systemsDeleteRoutePost'
            ),
            'menuEntry' => array(               // optional: if provided, the addon will be listed in the sidebar menu
                'title' => 'Version Check',       // title of the menu entry
                'icon' => 'tag',                   // name of a UIkit Icon (https://getuikit.com/docs/icon)
                'href' => 'addon/versioncheck'   // href for the menu entry (addon/[addonname][route]) or external url
            )
        );
    }


    protected $f3;
    protected $sic;
    private $db;
    private $cacheTTL = 3600;
    public function __construct($f3)
    {
        parent::__construct($f3);

        $dbPath   = $this->getAndCreateStoragePath() . '/versioncheck.sqlite';
        $this->db = new DB\SQL('sqlite:' . $dbPath);

        $this->bootstrapTables();
    }




    // -------------------------------------------------------
    // BOOTSTRAP
    // -------------------------------------------------------

    private function bootstrapTables() {
        $found = $this->db->exec(
            'SELECT name FROM sqlite_master WHERE type="table" AND name="vc_systems"'
        );
        if (count($found) === 0) {
            $this->db->exec('CREATE TABLE IF NOT EXISTS vc_systems (
                id           INTEGER PRIMARY KEY AUTOINCREMENT,
                sys_key      TEXT NOT NULL,
                label        TEXT NOT NULL,
                source       TEXT NOT NULL,
                endpoint     TEXT NOT NULL,
                filter       TEXT DEFAULT "stable",
                download_url TEXT DEFAULT "",
                is_active    INTEGER DEFAULT 1
            )');
            $this->seedDefaultSystems();
        } else {
            // Migration: download_url Spalte nachrüsten falls nicht vorhanden
            $cols = $this->db->exec('PRAGMA table_info(vc_systems)');
            $hasDownloadUrl = false;
            foreach ($cols as $col) {
                if ($col['name'] === 'download_url') { $hasDownloadUrl = true; break; }
            }
            if (!$hasDownloadUrl) {
                $this->db->exec('ALTER TABLE vc_systems ADD COLUMN download_url TEXT DEFAULT ""');
                // Standard-URLs für bestehende Einträge setzen
                foreach ($this->getDefaultDownloadUrls() as $key => $url) {
                    $this->db->exec(
                        'UPDATE vc_systems SET download_url=? WHERE sys_key=? AND (download_url IS NULL OR download_url="")',
                        [$url, $key]
                    );
                }
            }
        }

        $found = $this->db->exec(
            'SELECT name FROM sqlite_master WHERE type="table" AND name="vc_cache"'
        );
        if (count($found) === 0) {
            $this->db->exec('CREATE TABLE IF NOT EXISTS vc_cache (
                sys_key    TEXT PRIMARY KEY,
                version    TEXT,
                fetched_at INTEGER
            )');
        }
    }

    private function getDefaultDownloadUrls() {
        return [
            'wbce'       => 'https://github.com/WBCE/WBCE_CMS/releases/latest',
            'wordpress'  => 'https://wordpress.org/download/',
            'joomla'     => 'https://downloads.joomla.org/',
            'nextcloud'  => 'https://nextcloud.com/install/',
            'typo3'      => 'https://get.typo3.org/',
            'drupal'     => 'https://www.drupal.org/project/drupal/releases',
            'matomo'     => 'https://matomo.org/download/',
            'roundcube'  => 'https://roundcube.net/download/',
            'phpbb'      => 'https://www.phpbb.com/downloads/',
            'moodle'     => 'https://moodle.org/downloads/',
            'owncloud'   => 'https://owncloud.com/download-server/',
            'processwire' => 'https://processwire.com/download/core/',
        ];
    }

    private function seedDefaultSystems() {
        $downloadUrls = $this->getDefaultDownloadUrls();
        $defaults = [
            // [sys_key, label, source, endpoint, filter]
            ['wbce',      'WBCE CMS',   'github', 'WBCE/WBCE_CMS',                                          'stable'],
            ['wordpress', 'WordPress',  'wp_api', 'https://api.wordpress.org/core/version-check/1.7/',       'stable'],
            ['joomla',    'Joomla',     'github', 'joomla/joomla-cms',                                       'stable'],
            ['nextcloud', 'Nextcloud',  'github', 'nextcloud/server',                                        'stable'],
            ['typo3',     'TYPO3',      'json',   'https://get.typo3.org/json',                              'stable'],
            ['drupal',    'Drupal',     'github', 'drupal/drupal',                                           'stable'],
            ['matomo',    'Matomo',     'github', 'matomo-org/matomo',                                       'stable'],
            ['roundcube', 'Roundcube',  'github', 'roundcube/roundcube',                                     'stable'],
            ['phpbb',     'phpBB',      'github', 'phpbb/phpbb',                                             'stable'],
            ['moodle',    'Moodle',     'github', 'moodle/moodle',                                           'stable'],
            ['owncloud',  'ownCloud',   'github', 'owncloud/core',                                           'stable'],
            // demo für extension-based endpoint (siehe extensions/processwire.php)
            ['processwire',  'ProcessWire CMS',   'extension', 'x_processwire',                              'stable'],
        ];
        foreach ($defaults as $d) {
            $dlUrl = $downloadUrls[$d[0]] ?? '';
            $this->db->exec(
                'INSERT INTO vc_systems (sys_key,label,source,endpoint,filter,download_url,is_active)
                 VALUES (?,?,?,?,?,?,1)',
                [$d[0], $d[1], $d[2], $d[3], $d[4], $dlUrl]
            );
        }
    }



    // -------------------------------------------------------
    // ROUTEN
    // -------------------------------------------------------

    public function overviewRouteGet() {
        $this->sic->checkLogin(true);
        $data = [
            'tplPagetitle' => 'Version Check',
            'tplHeadline' => 'Version Check',
            'tplVcRows' => $this->buildOverviewRows(),
            'tplCSRF' => $this->sic->getNewCsrfToken(),
        ];
        echo $this->renderView('vc-overview.html', $data);
    }

    public function apiFetchGet() {
        if (!$this->sic->checkLogin(false)) {
            http_response_code(401);
            echo json_encode(['error' => 'Not logged in']);
            return;
        }
        header('Content-Type: application/json');
        echo json_encode($this->buildOverviewRows(true));
    }

    public function systemsRouteGet() {
        $this->sic->checkLogin(true, true);
        $data = [
            'tplPagetitle' => 'VC: CMS-Definitionen',
            'tplHeadline' => 'CMS-Definitionen',
            'tplVcSystems' => $this->getAllSystems(),
            'tplCSRF' => $this->sic->getNewCsrfToken(),
        ];
        echo $this->renderView('vc-systems.html', $data);
    }

    public function systemsAddRouteGet() {
        $this->sic->checkLogin(true, true);
        $data = [
            'tplPagetitle' => 'VC: CMS hinzufügen',
            'tplHeadline' => 'CMS hinzufügen',
            'tplVcSystem' => $this->emptySystem(),
            'tplCSRF' => $this->sic->getNewCsrfToken(),
        ];
        echo $this->renderView('vc-systems-edit.html', $data);
    }


    public function systemsAddRoutePost() {
        $this->sic->checkLogin(true, true);
        if (!$this->sic->checkCsrfToken($this->f3->get('POST.csrf'))) {
            $this->enqueueMessage('CSRF-Fehler.', 'danger');
            $this->f3->reroute('/versioncheck/systems/add');
            return;
        }
        $data = $this->collectFormData();
        if (!$data['sys_key'] || !$data['label'] || !$data['endpoint']) {
            $this->enqueueMessage('Pflichtfelder fehlen.', 'danger');
            $this->f3->reroute('/versioncheck/systems/add');
            return;
        }
        $this->db->exec(
            'INSERT INTO vc_systems (sys_key,label,source,endpoint,filter,download_url,is_active)
             VALUES (?,?,?,?,?,?,?)',
            [$data['sys_key'],$data['label'],$data['source'],
                $data['endpoint'],$data['filter'],$data['download_url'],$data['is_active']]
        );
        $this->enqueueMessage('CMS-Definition angelegt.', 'success');
        $this->f3->reroute('/addon/versioncheck/systems');
    }


    public function systemsEditRouteGet() {
        $this->sic->checkLogin(true, true);
        $id     = (int) $this->f3->get('PARAMS.id');
        $system = $this->getSystem($id);
        if (!$system) { $this->f3->reroute('/versioncheck/systems'); return; }
        $data = [
            'tplPagetitle' => 'VC: CMS bearbeiten',
            'tplHeadline' => 'CMS bearbeiten',
            'tplVcSystem' => $system,
            'tplCSRF' => $this->sic->getNewCsrfToken(),
        ];
        echo $this->renderView('vc-systems-edit.html', $data);
    }


    public function systemsEditRoutePost() {
        $this->sic->checkLogin(true, true);
        $id = (int) $this->f3->get('PARAMS.id');
        if (!$this->sic->checkCsrfToken($this->f3->get('POST.csrf'))) {
            $this->enqueueMessage('CSRF-Fehler.', 'danger');
            $this->f3->reroute('/addon/versioncheck/systems/edit/' . $id);
            return;
        }
        $data = $this->collectFormData();
        $this->db->exec(
            'UPDATE vc_systems SET sys_key=?,label=?,source=?,endpoint=?,
             filter=?,download_url=?,is_active=? WHERE id=?',
            [$data['sys_key'],$data['label'],$data['source'],$data['endpoint'],
                $data['filter'],$data['download_url'],$data['is_active'],$id]
        );
        $this->db->exec('DELETE FROM vc_cache WHERE sys_key=?', [$data['sys_key']]);
        $this->enqueueMessage('Gespeichert.', 'success');
        $this->f3->reroute('/addon/versioncheck/systems');
    }

    public function systemsDeleteRoutePost() {
        $this->sic->checkLogin(true, true);
        if (!$this->sic->checkCsrfToken($this->f3->get('POST.csrf'))) {
            $this->enqueueMessage('CSRF-Fehler.', 'danger');
            $this->f3->reroute('/addon/versioncheck/systems');
            return;
        }
        $id     = (int) $this->f3->get('PARAMS.id');
        $system = $this->getSystem($id);
        if ($system) {
            $this->db->exec('DELETE FROM vc_cache WHERE sys_key=?', [$system['sys_key']]);
        }
        $this->db->exec('DELETE FROM vc_systems WHERE id=?', [$id]);
        $this->enqueueMessage('Gelöscht.', 'success');
        $this->f3->reroute('/addon/versioncheck/systems');
    }




    // -------------------------------------------------------
    // KERN-LOGIK
    // -------------------------------------------------------

    private function buildOverviewRows($force = false) {
        $activeSites = $this->sic->getActiveSites(false);
        $systems     = $this->getActiveSystemsIndexed();
        $rows        = [];

        foreach ($activeSites as $site) {
            $sysKey = strtolower(trim($site['sys']));
            $row = [
                'site_name'    => $site['name'],
                'site_link'    => $site['link'] ?? '',
                'sys_key'      => $sysKey,
                'sys_label'    => isset($systems[$sysKey]) ? $systems[$sysKey]['label'] : $sysKey,
                'inst_ver'     => $site['sys_ver'] ?? 'n/a',
                'latest_ver'   => '…',
                'download_url' => isset($systems[$sysKey]) ? ($systems[$sysKey]['download_url'] ?? '') : '',
                'status'       => 'unknown',
                'known'        => isset($systems[$sysKey]),
            ];

            if (!$row['known'] || $row['inst_ver'] === 'n/a') {
                $rows[] = $row;
                continue;
            }

            $latestVer = $this->getLatestVersion($systems[$sysKey], $force);
            if ($latestVer === null) {
                $row['status']     = 'error';
                $row['latest_ver'] = 'Fehler';
                $rows[]            = $row;
                continue;
            }

            $row['latest_ver'] = $latestVer;
            $instClean         = ltrim($row['inst_ver'], 'vV');
            $latestClean       = ltrim($latestVer, 'vV');
            $row['status']     = version_compare($instClean, $latestClean, '>=') ? 'ok' : 'outdated';
            $rows[]            = $row;
        }
        return $rows;
    }


    private function getLatestVersion(array $system, bool $force = false) {
        $key = $system['sys_key'];
        $now = time();

        if (!$force) {
            $cached = $this->db->exec('SELECT version,fetched_at FROM vc_cache WHERE sys_key=?', [$key]);
            if ($cached && isset($cached[0]) && ($now - (int)$cached[0]['fetched_at']) < $this->cacheTTL) {
                return $cached[0]['version'];
            }
        }

        $version = null;
        switch ($system['source']) {
            case 'github': $version = $this->fetchGithub($system['endpoint'], $system['filter']); break;
            case 'wp_api': $version = $this->fetchWpApi($system['endpoint']); break;
            case 'xml':    $version = $this->fetchXml($system['endpoint']); break;
            case 'json':   $version = $this->fetchJson($system['endpoint'], $system['sys_key']); break;
            case 'extension':   $version = $this->getVersionFromExtension($system['endpoint']); break;
        }

        if ($version !== null) {
            $this->db->exec(
                'INSERT OR REPLACE INTO vc_cache (sys_key,version,fetched_at) VALUES (?,?,?)',
                [$key, $version, $now]
            );
        }
        return $version;
    }


    // -------------------------------------------------------
    // FETCH-METHODEN
    // -------------------------------------------------------

    private function fetchGithub(string $repo, string $filter = 'stable') {
        $body = $this->httpGet(
            "https://api.github.com/repos/{$repo}/releases/latest",
            ['User-Agent: SIC3-VersionCheck/1.0']
        );
        if (!$body) { return null; }
        $data = json_decode($body, true);
        if (!$data || !isset($data['tag_name'])) { return null; }
        if ($filter === 'stable' && !empty($data['prerelease'])) { return null; }
        return ltrim($data['tag_name'], 'vV');
    }

    private function fetchWpApi(string $url) {
        $body = $this->httpGet($url);
        if (!$body) { return null; }
        $data = json_decode($body, true);
        return $data['offers'][0]['version'] ?? null;
    }

    private function fetchXml(string $url) {
        $body = $this->httpGet($url);
        if (!$body) { return null; }
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body);
        return ($xml && isset($xml->version)) ? (string)$xml->version : null;
    }

    private function fetchJson(string $url, string $sysKey = '') {
        $body = $this->httpGet($url);
        if (!$body) { return null; }
        $data = json_decode($body, true);
        if (!$data) { return null; }

        // TYPO3: get.typo3.org/json gibt verschachteltes Array zurück
        // Struktur: { "13": { "latest": "13.4.x", ... }, "12": {...} }
        if ($sysKey === 'typo3' && is_array($data)) {
            $maxMajor = 0;
            $latest   = null;
            foreach ($data as $major => $info) {
                if (!is_numeric($major)) { continue; }
                if ((int)$major > $maxMajor && isset($info['latest'])) {
                    $maxMajor = (int)$major;
                    $latest   = $info['latest'];
                }
            }
            return $latest;
        }

        // Generisch: gängige Felder versuchen
        foreach (['version','stable_version','current_version','latest'] as $f) {
            if (isset($data[$f]) && is_string($data[$f])) { return $data[$f]; }
        }
        return null;
    }

    private function httpGet(string $url, array $headers = []) {
        if (!function_exists('curl_version')) { return false; }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        if ($headers) { curl_setopt($ch, CURLOPT_HTTPHEADER, $headers); }
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ($code === 200 && $body) ? $body : false;
    }

    // -------------------------------------------------------
    // DB-HILFSMETHODEN
    // -------------------------------------------------------

    private function getAllSystems() {
        return $this->db->exec('SELECT * FROM vc_systems ORDER BY label ASC') ?: [];
    }

    private function getActiveSystemsIndexed() {
        $rows    = $this->db->exec('SELECT * FROM vc_systems WHERE is_active=1') ?: [];
        $indexed = [];
        foreach ($rows as $row) { $indexed[strtolower($row['sys_key'])] = $row; }
        return $indexed;
    }

    private function getSystem(int $id) {
        $rows = $this->db->exec('SELECT * FROM vc_systems WHERE id=?', [$id]);
        return ($rows && isset($rows[0])) ? $rows[0] : null;
    }

    private function emptySystem() {
        return [
            'id'=>0,'sys_key'=>'','label'=>'','source'=>'github',
            'endpoint'=>'','filter'=>'stable','download_url'=>'','is_active'=>1
        ];
    }

    private function collectFormData() {
        return [
            'sys_key'      => trim($this->f3->get('POST.sys_key') ?? ''),
            'label'        => trim($this->f3->get('POST.label') ?? ''),
            'source'       => $this->f3->get('POST.source') ?? 'github',
            'endpoint'     => trim($this->f3->get('POST.endpoint') ?? ''),
            'filter'       => $this->f3->get('POST.filter') ?? 'stable',
            'download_url' => trim($this->f3->get('POST.download_url') ?? ''),
            'is_active'    => (int)($this->f3->get('POST.is_active') ?? 0),
        ];
    }



    // -------------------------------------------------------
    // METHODEN FÜR EXTENSIONS
    // -------------------------------------------------------
    public function getVersionFromExtension(string $endpoint) {
        $extensionName = substr($endpoint, 2); // remove "x_"
        $extensionsDirectory = $this->getAddonPath() . DIRECTORY_SEPARATOR . 'extensions' . DIRECTORY_SEPARATOR;

        // make first letter of $extensionName uppercase to match file naming convention
        $extensionName = ucfirst($extensionName);
        $extensionClassName = $extensionName . 'VcExtension';

        // expected extension file name
        $extensionFileName = $extensionsDirectory . $extensionClassName . '.php';

        // load file and get version
        if(file_exists($extensionFileName)) {
            require_once $extensionFileName;
            if(class_exists($extensionClassName) && method_exists($extensionClassName, 'getLatestVersion')) {
                $extension = new $extensionClassName();
                return $extension::getLatestVersion();
            }
        }
        return null;
    }

}