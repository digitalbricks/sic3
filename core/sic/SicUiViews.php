<?php
class SicUiViews{
    private $f3;
    public function __construct($f3){
        $this->f3 = $f3;

        // set default template variables to prevent errors if not set on every route
        $this->f3->set('tplHeadline','');
        $this->f3->set('tplPagetitle','');
        $this->f3->set('tplPartial','');
        $this->f3->set('tplDarkmodeClass','');

        // set darkmode class if enabled
        if($this->f3->get('COOKIE.darkmode') === 'true'){
            $this->f3->set('tplDarkmodeClass','darkmode');
        };

        // set first url segment (used for highlighting menu items)
        $requestpath = $this->f3->get('PARAMS.0');
        $segments = explode('/',$requestpath);
        $firstUrlSegment = '';
        if(array_key_exists(1,$segments)){
            $firstUrlSegment = $segments[1];
        }
        $this->f3->set('firstUrlSegment',$firstUrlSegment);

        // get enqueued messages from session and set them to template variable
        $this->f3->set('tplEnqueuedMessages',$this->getEnqueuedMessages());

        // set functions for use in template files
        $this->f3->set('getUserName',function($id){
            return $this->f3->get('sic')->getUserName($id);
        });
    }

    /**
     * Enqueue a message to be displayed on the next page load.
     * This is useful for displaying messages after a redirect.
     * Message are stored in PHP session.
     *
     * @param $message
     * @param $type string primary|success|warning|danger
     * @return false|void
     */
    function enqueueMessage($message, $type = 'primary'){
        if(!$this->f3->get('sic')->isUserLoggedIn()){
            return false;
        }
        // init session msgQueue if not exists
        $msgQueue = $this->f3->get('SESSION.msgQueue');
        if(!$msgQueue|| !is_array($msgQueue)){
            $msgQueue = array();
        }
        $msgQueue[] = array(
            'message' => htmlspecialchars($message),
            'type' => $type
        );
        $this->f3->set('SESSION.msgQueue',$msgQueue);
    }

    /**
     * Get enqueued messages from session and clear session.
     * (Flush on read, so messages are only displayed once)
     *
     * @return false|array
     */
    function getEnqueuedMessages(){
        if(!$this->f3->get('sic')->isUserLoggedIn()){
            return false;
        }
        $msgQueue = $this->f3->get('SESSION.msgQueue');
        $this->f3->clear('SESSION.msgQueue');
        return $msgQueue;
    }

    public function indexRouteGet(){
        $this->f3->get('sic')->checkLogin(true);

        // check there is a sites-config.php file (from SIClight) in the root folder
        // if so, redirect to /migrate BUT skip if SESSION.skipMigrate is set
        if(file_exists($this->f3->get('rootPath').'sites-config.php') && !$this->f3->get('SESSION.skipMigrate')){
            $this->f3->reroute('/migrate');
        }elseif(file_exists($this->f3->get('rootPath').'storage/sites-config.json') && !$this->f3->get('SESSION.skipMigrate')){
            $this->f3->reroute('/migrate');
        }

        $this->f3->set('tplPagetitle','Sites overview');
        $this->f3->set('tplHeadline','Sites overview');
        $this->f3->set('tplPartial','core/views/overview.html');
        echo \Template::instance()->render('core/views/_base.html');
    }

    public function loginRouteGet(){
        if($this->f3->get('userLoggedIn')){
            $this->f3->reroute('/');
        }
        $this->f3->set('tplHeadline','Login');
        $this->f3->set('tplPagetitle','Login');

        // check if we had a login error (after redirect)
        $this->f3->set('tplLoginError',false);
        if($this->f3->get('SESSION.loginError')){
            $this->f3->set('tplLoginError',true);
        }
        echo \Template::instance()->render('core/views/login.html');
        $this->f3->set('SESSION.loginError',false);
    }

    public function loginRoutePost(){
        $username = $this->f3->get('POST.username');
        $password = $this->f3->get('POST.password');

        // check if the user uses the default login credentials
        $defaultAdminUsername = $this->f3->get('defaultAdminUsername');
        $defaultAdminPassword = $this->f3->get('defaultAdminPassword');
        $this->f3->set('SESSION.defaultCredentialsUsed',false);
        if($username == $defaultAdminUsername && $password == $defaultAdminPassword){
            $this->f3->set('SESSION.defaultCredentialsUsed',true);
        }

        if($this->f3->get('sic')->checkCredentials($username, $password)){
            $this->f3->set('SESSION.loginError',false);
            $this->f3->reroute('/');
        }else{
            $this->f3->get('sic')->logoutUser();
            $this->f3->set('SESSION.loginError',true);
            $this->f3->reroute('/login');
        }
    }

    public function logoutRouteGet(){
        $this->f3->get('sic')->logoutUser();
        $this->f3->reroute('/login');
    }

    public function migrateRouteGet(){
        $this->f3->get('sic')->checkLogin(true,$admins_only = true);
        $this->f3->set('tplPagetitle','Sites Migration');
        $this->f3->set('tplPartial','core/views/migrate.html');
        $this->f3->set('configFileExists',false);
        $this->f3->set('configFileName','');
        $this->f3->set('configFileLocation','');
        if(file_exists($this->f3->get('rootPath').'sites-config.php')){
            $this->f3->set('configFileExists',true);
            $this->f3->set('configFileName','sites-config.php');
            $this->f3->set('configFileLocation','/ (root)');
        }elseif(file_exists($this->f3->get('rootPath').'storage/sites-config.json')){
            $this->f3->set('configFileExists',true);
            $this->f3->set('configFileName','sites-config.json');
            $this->f3->set('configFileLocation','/storage/');
        }

        echo \Template::instance()->render('core/views/_base.html');
    }

    public function migrateRoutePost(){
        $this->f3->get('sic')->checkLogin(true,$admins_only = true);
        $migrate = $this->f3->get('POST.migrate');
        if($migrate == 'yes'){
            $migrated = $this->f3->get('sic')->migrateSitesConfig();
            if($migrated && is_int($migrated)){
                $this->enqueueMessage("{$migrated} sites imported",'success');
            } else {
                $this->enqueueMessage("Import failed.",'danger');
            }
            $this->f3->reroute('/');
            return;
        }
        if($migrate == 'no'){
            $this->f3->set('SESSION.skipMigrate',true);
            $this->f3->reroute('/');
            return;
        }
    }

    public function sitesRouteGet(){
        $this->f3->get('sic')->checkLogin(true,$admins_only = true);

        // generate a new CSRF token and store in tplCSRF template variable
        /*
         * NOTE: all delete buttons on the page using the same CSRF token
         * which is not optimal, but should be sufficient for now.
         * We may generate a bunch of tokens and store them in session array
         * and do a check against that array on each delete request – some day.
         * */
        $this->f3->set('tplCSRF',$this->f3->get('sic')->getNewCsrfToken());

        // set other template variables
        $this->f3->set('tplPagetitle','Sites');
        $this->f3->set('tplHeadline','Manage Sites');
        $this->f3->set('tplPartial','core/views/sites.html');
        $allSites = $this->f3->get('sic')->getAllSites();
        $this->f3->set('tplAllSites',$allSites);
        $this->f3->set('tplAllSitesCount',count($allSites));
        echo \Template::instance()->render('core/views/_base.html');
    }

    public function sitesEditRouteGet(){
        $this->f3->get('sic')->checkLogin(true,$admins_only = true);
        $siteId = $this->f3->get('PARAMS.siteId');

        // if no siteId is given (or 0), redirect to /sites/add
        if(!$siteId || $siteId=="" || $siteId==0){
            $this->f3->reroute('/sites/add');
        }

        // get site data
        $siteData = $this->f3->get('sic')->getSite($siteId);
        if(!$siteData){
            $this->f3->error(404);
        }

        // trigger satellite download from GitHub and get available system identifiers
        $satgen = new SicSatGen($this->f3, 0);
        $siteData['availableSys'] = $satgen->getAvailableSystemIdentifiers();

        // generate a new CSRF token and store in tplCSRF template variable
        $this->f3->set('tplCSRF',$this->f3->get('sic')->getNewCsrfToken());

        // load help texts and convert to html
        $md = \Markdown::instance();
        $root = $this->f3->get('sic')->getRootPath();
        $docFile = F3::instance()->read($root.'/core/docs/satellite-setup.md');
        $satSetupHtml = $md->convert($docFile);

        // set other template variables
        $siteName = $siteData['name'];
        $this->f3->set('tplSiteData',$siteData);
        $this->f3->set('tplPagetitle','Edit Site: '.$siteName);
        $this->f3->set('tplHeadline','Edit Site: '.$siteName);
        $this->f3->set('tplSatSetupHtml',$satSetupHtml);
        $this->f3->set('tplPartial','core/views/sites-edit.html');
        echo \Template::instance()->render('core/views/_base.html');
    }

    public function sitesEditRoutePost(){
        $this->f3->get('sic')->checkLogin(true, $admins_only = true);
        $id = $this->f3->get('POST.siteId');
        $name = $this->f3->get('POST.siteName');
        $link = $this->f3->get('POST.siteLink');
        $sys = $this->f3->get('POST.siteSys');
        $url = $this->f3->get('POST.siteUrl');
        $secret = $this->f3->get('POST.siteSecret');
        $active = intval($this->f3->get('POST.siteActive'));
        $csrf = $this->f3->get('POST.csrf');

        $referrer = $this->f3->get('SERVER.HTTP_REFERER');

        // check CSRF token
        if(!$this->f3->get('sic')->checkCsrfToken($csrf)){
            $this->enqueueMessage("This request was aborted because it appears to be forged",'danger');
            $this->f3->reroute($referrer);
        }

        // check link & url for validity
        $urlsinvalid = false;
        if($link!="" && !filter_var($link, FILTER_VALIDATE_URL)){
            $this->enqueueMessage("The Site URL / Links seems to be invalid",'danger');
            $urlsinvalid = true;
        }
        if($url=="" || !filter_var($url, FILTER_VALIDATE_URL)){
            $this->enqueueMessage("The SIC Satellite URL seems to be invalid",'danger');
            $urlsinvalid = true;
        }
        if($urlsinvalid){
            // before we reroute, we store the form data in session
            $this->f3->set('SESSION.siteData',array(
                'id' => $id,
                'name' => $name,
                'link' => $link,
                'sys' => $sys,
                'url' => $url,
                'secret' => $secret,
                'active' => $active
            ));
            $this->f3->reroute($referrer);
        }

        // save site, if successful we will get the ID back
        $editedID = $this->f3->get('sic')->saveSite($id,$name,$link,$sys,$url,$secret,$active);
        if($editedID){
            $this->enqueueMessage("Site {$name} saved",'success');
            $this->f3->reroute('/sites/edit/'.$editedID);
        } else {
            $this->enqueueMessage("Site {$name} could not be saved",'danger');
            $this->f3->reroute($referrer);
        }

    }

    public function sitesAddRouteGet(){
        $this->f3->get('sic')->checkLogin(true, $admins_only = true);
        // empty site data for preventing template errors
        $siteData = array(
            'id' => 0, // 0 = new site
            'name' => '',
            'link' => '',
            'sys' => '',
            'url' => '',
            'secret' => '',
            'is_active' => 0,
            'created_at' => '',
            'created_by' => '',
            'updated_at' => '',
        );

        // trigger satellite download from GitHub and get available system identifiers
        $satgen = new SicSatGen($this->f3, 0);
        $siteData['availableSys'] = $satgen->getAvailableSystemIdentifiers();

        // check if we have form data in session SESSION.siteData
        // if so, we use this data instead of the empty array above
        $sessionSiteData = $this->f3->get('SESSION.siteData');
        if(is_array($sessionSiteData)){
            if(array_key_exists('name',$sessionSiteData)){
                $siteData['name'] = htmlspecialchars($sessionSiteData['name']);
            }
            if(array_key_exists('link',$sessionSiteData)){
                $siteData['link'] = htmlspecialchars($sessionSiteData['link']);
            }
            if(array_key_exists('sys',$sessionSiteData)){
                $siteData['sys'] = htmlspecialchars($sessionSiteData['sys']);
            }
            if(array_key_exists('url',$sessionSiteData)){
                $siteData['url'] = htmlspecialchars($sessionSiteData['url']);
            }
            if(array_key_exists('secret',$sessionSiteData)){
                $siteData['secret'] = htmlspecialchars($sessionSiteData['secret']);
            }

            // because we have saved data in session (which means the former submission was invalid),
            // we set is_active to 0 so that the user has to activate the site manually
            $siteData['is_active'] = 0;

            // clear session data
            $this->f3->clear('SESSION.siteData');
        }


        // generate a new CSRF token and store in tplCSRF template variable
        $this->f3->set('tplCSRF',$this->f3->get('sic')->getNewCsrfToken());

        // load help texts and convert to html
        $md = \Markdown::instance();
        $root = $this->f3->get('sic')->getRootPath();
        $docFile = F3::instance()->read($root.'/core/docs/satellite-setup.md');
        $satSetupHtml = $md->convert($docFile);

        $this->f3->set('tplSiteData',$siteData);
        $this->f3->set('tplPagetitle','Add Site');
        $this->f3->set('tplHeadline','Add New Site');
        $this->f3->set('tplSatSetupHtml',$satSetupHtml);
        $this->f3->set('tplPartial','core/views/sites-edit.html');
        echo \Template::instance()->render('core/views/_base.html');
    }

    public function sitesDeleteRoutePost(){
        /* NOTE: we don't use the f3->reroute() method here
         * because this causes the messageQueue to be emptied
         * in the background (because of the JS XHR request).
         *
         */
        $this->f3->get('sic')->checkLogin(true, $admins_only = true);
        $siteIdParam = $this->f3->get('PARAMS.siteId');
        $siteIdPost = $this->f3->get('POST.siteId');
        $csrf = $this->f3->get('POST.csrf');

        // check CSRF token
        if(!$this->f3->get('sic')->checkCsrfToken($csrf)){
            $this->enqueueMessage("This request was aborted because it appears to be forged",'danger');
            return;
        }

        if($siteIdParam != $siteIdPost){
            $this->f3->error(404);
        }


        // we also check if the user has confirmed the deletion
        // in the modal dialog (which adds ?confirmed=true to the url)
        $confirmed = $this->f3->get('POST.confirmed');
        if(!$confirmed || $confirmed!="true"){
            echo "You have to confirm the deletion!";
            $this->enqueueMessage("You have to confirm the deletion!",'danger');
            return;
        }

        // get site data
        $siteData = $this->f3->get('sic')->getSite($siteIdParam);
        if(!$siteData){
            $this->f3->error(404);
        }
        $siteName = $siteData['name'];

        $deleteID = $this->f3->get('sic')->deleteSite($siteIdParam);
        if($deleteID){
            $this->enqueueMessage("Site {$siteName} deleted",'success');
        } else {
            $this->enqueueMessage("Site {$siteName} could not be deleted",'danger');
        }
        return;
    }

    public function sitesExportRouteGet(){
        $this->f3->get('sic')->checkLogin(true, $admins_only = true);

        // set other template variables
        $this->f3->set('tplPagetitle','Export Sites Configuration');
        $this->f3->set('tplHeadline','Export Sites Configuration');
        $this->f3->set('tplSitesConfigJson',$this->f3->get('sic')->getSitesAsJson());
        $this->f3->set('tplPartial','core/views/sites-export.html');
        echo \Template::instance()->render('core/views/_base.html');
    }

    public function sitesExportDownloadRouteGet(){
        $this->f3->get('sic')->checkLogin(true, $admins_only = true);

        // sent JSON as file to browser
        $filename="sites-config.json";
        header("Content-disposition: attachment;filename=$filename");
        echo $this->f3->get('sic')->getSitesAsJson();
    }

    public function usersRouteGet(){
        $this->f3->get('sic')->checkLogin(true, $admins_only = true);

        // generate a new CSRF token and store in tplCSRF template variable
        /*
         * NOTE: all delete buttons on the page using the same CSRF token
         * which is not optimal, but should be sufficient for now.
         * We may generate a bunch of tokens and store them in session array
         * and do a check against that array on each delete request – some day.
         * */
        $this->f3->set('tplCSRF',$this->f3->get('sic')->getNewCsrfToken());

        // set other template variables
        $this->f3->set('tplPagetitle','Users');
        $this->f3->set('tplHeadline','Manage Users');
        $this->f3->set('tplPartial','core/views/users.html');
        $users = $this->f3->get('sic')->getUsers();
        $this->f3->set('tplUsers',$users);
        $currentUser = $this->f3->get('sic')->getCurrentUser();
        $this->f3->set('tplCurrentUser',$currentUser);
        $this->f3->set('tplUsersCount',count($users));
        echo \Template::instance()->render('core/views/_base.html');
    }

    public function usersEditRouteGet(){
        $this->f3->get('sic')->checkLogin(true, $admins_only = true);
        $userId = $this->f3->get('PARAMS.userId');

        // if no siteId is given (or 0), redirect to /sites/add
        if(!$userId || $userId=="" || $userId==0){
            $this->f3->reroute('/users/add');
        }

        // get user data
        $userData = $this->f3->get('sic')->getUser($userId);
        if(!$userData){
            $this->f3->error(404);
        }

        // get current logged in user
        $currentUser = $this->f3->get('sic')->getCurrentUser();
        $this->f3->set('tplCurrentUser',false);
        if($currentUser['id'] == $userId){
            $this->f3->set('tplCurrentUser',true);
        }

        // generate a new CSRF token and store in tplCSRF template variable
        $this->f3->set('tplCSRF',$this->f3->get('sic')->getNewCsrfToken());

        // set other template variables
        $userName = $userData['username'];
        $this->f3->set('tplUserData',$userData);
        $this->f3->set('tplPagetitle','Edit User');
        $this->f3->set('tplHeadline','Edit User: '.$userName);
        $this->f3->set('tplPartial','core/views/users-edit.html');
        echo \Template::instance()->render('core/views/_base.html');
    }

    public function usersEditRoutePost(){
        $this->f3->get('sic')->checkLogin(true, $admins_only = true);
        $id = $this->f3->get('POST.userId');
        $username = $this->f3->get('POST.userName');
        $email = $this->f3->get('POST.userMail');
        $password = $this->f3->get('POST.userPassword');
        $is_active = intval($this->f3->get('POST.userActive'));
        $is_admin = intval($this->f3->get('POST.userAdmin'));
        $csrf = $this->f3->get('POST.csrf');

        $referrer = $this->f3->get('SERVER.HTTP_REFERER');

        // get current data of the user to be changed
        $user = $this->f3->get('sic')->getUser($id);

        // check if new user or the username or email is not the same as before
        if(!$this->f3->get('sic')->checkUsernameAndEmailUnique($username,$email,$id)){
            $this->enqueueMessage("Username or email already exists",'danger');
            $this->f3->reroute($referrer);
        }

        // get current logged in user (who did the request)
        $currentUser = $this->f3->get('sic')->getCurrentUser();

        // prevent users from editing themselves is_active or is_admin status
        if($currentUser['id'] == $id){
            // overwrite submitted date with current user data (already in DB)
            $currentUserData = $this->f3->get('sic')->getUser($currentUser['id']);
            $is_active = $currentUserData['is_active'];
            $is_admin = $currentUserData['is_admin'];
        }

        // check CSRF token
        if(!$this->f3->get('sic')->checkCsrfToken($csrf)){
            $this->enqueueMessage("This request was aborted because it appears to be forged",'danger');
            $this->f3->reroute($referrer);
        }

        // if new user, check password length
        if($id==0 && strlen($password)<8){
            $this->enqueueMessage("You have to set a password with at leat 8 characters",'danger');
            $this->f3->reroute($referrer);
        }

        // if NOT new user and password is NOT empty, we check its length
        if($id!=0 && $password!="" && strlen($password)<8){
            $this->enqueueMessage("Password must be at least 8 characters long",'danger');
            $this->f3->reroute($referrer);
        }


        // save site, if successful we will get the ID back
        $editedID = $this->f3->get('sic')->saveUser($id,$username,$email,$password,$is_admin,$is_active);
        if($editedID){
            $this->enqueueMessage("User {$username} saved",'success');
            $this->f3->reroute('/users/edit/'.$editedID);
        } else {
            $this->enqueueMessage("User {$username} could not be saved",'danger');
            $this->f3->reroute($referrer);
        }

    }


    public function usersAddRouteGet(){
        $this->f3->get('sic')->checkLogin(true, $admins_only = true);
        // empty site data for preventing template errors
        $userData = array(
            'id' => 0, // 0 = new site
            'username' => '',
            'email' => '',
            'is_admin' => 0,
            'is_active' => 1,
            'created_at' => '',
            'created_by' => '',
            'updated_at' => '',
        );
        // generate a new CSRF token and store in tplCSRF template variable
        $this->f3->set('tplCSRF',$this->f3->get('sic')->getNewCsrfToken());

        $this->f3->set('tplUserData',$userData);
        $this->f3->set('tplPagetitle','Add User');
        $this->f3->set('tplHeadline','Add User');
        $this->f3->set('tplCurrentUser',false);
        $this->f3->set('tplPartial','core/views/users-edit.html');
        echo \Template::instance()->render('core/views/_base.html');
    }

    public function usersDeleteRoutePost(){
        /* NOTE: we don't use the f3->reroute() method here
         * because this causes the messageQueue to be emptied
         * in the background (because of the JS XHR request).
         *
         */
        $this->f3->get('sic')->checkLogin(true, $admins_only = true);
        $userIdParam = $this->f3->get('PARAMS.siteId');
        $userIdPost = $this->f3->get('POST.userId');
        $csrf = $this->f3->get('POST.csrf');

        // check CSRF token
        if(!$this->f3->get('sic')->checkCsrfToken($csrf)){
            $this->enqueueMessage("This request was aborted because it appears to be forged",'danger');
            return;
        }

        if($userIdParam != $userIdPost){
            $this->f3->error(404);
        }


        // we also check if the user has confirmed the deletion
        // in the modal dialog (which adds ?confirmed=true to the url)
        $confirmed = $this->f3->get('POST.confirmed');
        if(!$confirmed || $confirmed!="true"){
            echo "You have to confirm the deletion!";
            $this->enqueueMessage("You have to confirm the deletion!",'danger');
            return;
        }

        // get user data
        $userData = $this->f3->get('sic')->getUser($userIdParam);
        $userName = $userData['username'];
        if(!$userData){
            $this->f3->error(404);
        }

        // prevent user from deleting himself
        $currentUser = $this->f3->get('sic')->getCurrentUser();
        if($currentUser['id'] == $userData['id']){
            $this->enqueueMessage("You can't delete yourself",'danger');
            return;
        }

        $deleteID = $this->f3->get('sic')->deleteUser($userIdParam);
        if($deleteID){
            $this->enqueueMessage("User {$userName} deleted",'success');
        } else {
            $this->enqueueMessage("User {$userName} could not be deleted",'danger');
        }
        return;
    }


    public function profileRouteGet(){
        $this->f3->get('sic')->checkLogin(true);

        // get current logged in user
        $userData = $this->f3->get('sic')->getCurrentUser();
        if(!$userData){
            $this->f3->error(404);
        }

        // generate a new CSRF token and store in tplCSRF template variable
        $this->f3->set('tplCSRF',$this->f3->get('sic')->getNewCsrfToken());

        // set other template variables
        $userName = $userData['username'];
        $this->f3->set('tplUserData',$userData);
        $this->f3->set('tplPagetitle','Profile');
        $this->f3->set('tplHeadline','Edit Profile: '.$userName);
        $this->f3->set('tplPartial','core/views/profile-edit.html');
        echo \Template::instance()->render('core/views/_base.html');
    }

    public function profileRoutePost(){
        $this->f3->get('sic')->checkLogin(true);
        $emailPost = $this->f3->get('POST.userMail');
        $passwordPost = $this->f3->get('POST.userPassword');
        $csrf = $this->f3->get('POST.csrf');
        $referrer = $this->f3->get('SERVER.HTTP_REFERER');

        // get current logged in user and set defaults
        $userData = $this->f3->get('sic')->getCurrentUser();
        $id = $userData['id'];
        $username = $userData['username'];
        $email = $userData['email'];
        $password = "";
        $is_admin = $userData['is_admin'];
        $is_active = $userData['is_active'];

        // override defaults with POST data
        if($emailPost!="" && $emailPost!=$email){
            $email = $emailPost;
        }
        if($passwordPost!=""){
            if(strlen($passwordPost)<8){
                $this->enqueueMessage("Password must be at least 8 characters long",'danger');
                $this->f3->reroute('/profile');
            }
            $password = $passwordPost;
        }

        // check CSRF token
        if(!$this->f3->get('sic')->checkCsrfToken($csrf)){
            $this->enqueueMessage("This request was aborted because it appears to be forged",'danger');
            $this->f3->reroute('/profile');
        }

        // save user data, if successful we will get the ID back
        $editedID = $this->f3->get('sic')->saveUser($id,$username,$email,$password,$is_admin,$is_active);
        if($editedID){
            // check if default credentials were used (password minimum length should prevent it but to make sure ...
            $defaultAdminUsername = $this->f3->get('defaultAdminUsername');
            $defaultAdminPassword = $this->f3->get('defaultAdminPassword');
            $this->f3->set('SESSION.defaultCredentialsUsed',false);
            if($username == $defaultAdminUsername && $password == $defaultAdminPassword){
                $this->f3->set('SESSION.defaultCredentialsUsed',true);
            }

            $this->enqueueMessage("User {$username} saved",'success');
            $this->f3->reroute('/profile');
        } else {
            $this->enqueueMessage("User {$username} could not be saved",'danger');
            $this->f3->reroute($referrer);
        }
    }

    public function infoRouteGet(){
        $this->f3->get('sic')->checkLogin(true);
        $root = $this->f3->get('sic')->getRootPath();

        $md = \Markdown::instance();

        // SIC license
        $html ="<h1>License</h1>";
        $licenseFile = F3::instance()->read($root.'/LICENSE');
        $html.= $md->convert($licenseFile);
        $html.="<hr>";

        // SIC attribution
        $attributionFile = F3::instance()->read($root.'/attribution.md');
        $html.= $md->convert($attributionFile);


        $this->f3->set('tplContent','');
        $this->f3->set('tplHtmlContent',$html);
        $this->f3->set('tplPagetitle','Licenses and used software');
        $this->f3->set('tplHeadline','');
        $this->f3->set('tplPartial','core/views/blank.html');
        echo \Template::instance()->render('core/views/_base.html');
    }

    public function settingsRouteGet(){
        $this->f3->get('sic')->checkLogin(true, $admins_only = true);
        $this->f3->set('tplCSRF',$this->f3->get('sic')->getNewCsrfToken());
        $settings = $this->f3->get('sic')->getSettings();
        $this->f3->set('tplSatContact',$settings['sat_contact']);
        $this->f3->set('tplPagetitle','Settings');
        $this->f3->set('tplHeadline','Settings');
        $this->f3->set('tplPartial','core/views/settings.html');
        echo \Template::instance()->render('core/views/_base.html');
    }

    public function settingsRoutePost(){
        $this->f3->get('sic')->checkLogin(true, $admins_only = true);
        $sat_contact = $this->f3->get('POST.satContact');
        $csrf = $this->f3->get('POST.csrf');
        $referrer = $this->f3->get('SERVER.HTTP_REFERER');

        // check CSRF token
        if(!$this->f3->get('sic')->checkCsrfToken($csrf)){
            $this->enqueueMessage("This request was aborted because it appears to be forged",'danger');
            $this->f3->reroute($referrer);
        }

        // save setting, if successful we will get the ID back
        /*
         * Unless the practice in other routes, for settings we save one by one
         * to keep the code simple and the settings table extensible in the future.
         * So we do not redirect immediately, but save all settings and then redirect.
         * */
        $editedID = $this->f3->get('sic')->saveSetting('sat_contact',$sat_contact);
        if($editedID){
            $this->enqueueMessage("Satellite Contact Information saved",'success');
        } else {
            $this->enqueueMessage("Satellite Contact Information could not be saved",'danger');
        }


        $this->f3->reroute('/settings');
    }


    public function satgenRouteGet(){
        $this->f3->get('sic')->checkLogin(true,$admins_only = true);
        $siteId = $this->f3->get('PARAMS.siteId');

        // get site data
        $siteData = $this->f3->get('sic')->getSite($siteId);
        if(!$siteData){
            $this->f3->error(404);
        }

        $satgen = new SicSatGen($this->f3, $siteId);
        $satCode =  $satgen->getSatelliteContent();
        $satCacheFileInfo = $satgen->getCachedFileInfo();

        // set other template variables
        $siteName = $siteData['name'];
        $siteId = $siteData['id'];
        $this->f3->set('tplPagetitle','Generate Satellite');
        $this->f3->set('tplSatCode',$satCode);
        $this->f3->set('tplSatCacheFileInfo',$satCacheFileInfo);
        $this->f3->set('tplSiteId',$siteId);
        $this->f3->set('tplHeadline','Satellite for: '.$siteName);
        $this->f3->set('tplPartial','core/views/satellite.html');
        echo \Template::instance()->render('core/views/_base.html');
    }

    public function satdownloadRouteGet(){
        $this->f3->get('sic')->checkLogin(true,$admins_only = true);
        $siteId = $this->f3->get('PARAMS.siteId');

        // get site data
        $siteData = $this->f3->get('sic')->getSite($siteId);
        if(!$siteData){
            $this->f3->error(404);
        }

        $satgen = new SicSatGen($this->f3, $siteId);
        $satCode =  $satgen->getSatelliteContent();

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="satellite.php"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        echo $satCode;
        return;
    }
}