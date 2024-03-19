<?php
require_once 'core/init.php';

// SIC UI index route
$f3->route('GET /','SicUiViews->indexRouteGet');

// SIC UI login routes
$f3->route('GET /login','SicUiViews->loginRouteGet');
$f3->route('POST /login','SicUiViews->loginRoutePost');
$f3->route('GET /logout','SicUiViews->logoutRouteGet');

// SIC UI migrate routes
$f3->route('GET /migrate','SicUiViews->migrateRouteGet');
$f3->route('POST /migrate','SicUiViews->migrateRoutePost');

// SIC UI sites routes
$f3->route('GET /sites','SicUiViews->sitesRouteGet');
$f3->route('GET /sites/edit/@siteId','SicUiViews->sitesEditRouteGet');
$f3->route('POST /sites/edit/@siteId','SicUiViews->sitesEditRoutePost');
$f3->route('GET /sites/add','SicUiViews->sitesAddRouteGet');
$f3->route('POST /sites/delete/@siteId','SicUiViews->sitesDeleteRoutePost');
$f3->route('GET /sites/export','SicUiViews->sitesExportRouteGet');
$f3->route('GET /sites/export/download','SicUiViews->sitesExportDownloadRouteGet');

// SIC UI users routes
$f3->route('GET /users','SicUiViews->usersRouteGet');
$f3->route('GET /users/edit/@userId','SicUiViews->usersEditRouteGet');
$f3->route('POST /users/edit/@siteId','SicUiViews->usersEditRoutePost');
$f3->route('GET /users/add','SicUiViews->usersAddRouteGet');
$f3->route('POST /users/delete/@siteId','SicUiViews->usersDeleteRoutePost');

// SIC UI profile routes
$f3->route('GET /profile','SicUiViews->profileRouteGet');
$f3->route('POST /profile','SicUiViews->profileRoutePost');

// SIC UI info route
$f3->route('GET /info','SicUiViews->infoRouteGet');

// SIC UI settings routes
$f3->route('GET /settings','SicUiViews->settingsRouteGet');
$f3->route('POST /settings','SicUiViews->settingsRoutePost');

// SIC UI satellite generator routes
$f3->route('GET /satgen/@siteId','SicUiViews->satgenRouteGet');
$f3->route('GET /satdownload/@siteId','SicUiViews->satdownloadRouteGet');

// REST API routes
$f3->route('GET /api/v1/sites/@endpoint','SicRestApi->apiV1SitesRouteGet');
$f3->route('POST /api/v1/sites/@endpoint','SicRestApi->apiV1SitesRoutePost');
$f3->route('GET /api/v1/history/render/@siteId','SicRestApi->apiV1HistoryRenderRouteGet');
$f3->route('GET /api/v1/history/download/@siteId','SicRestApi->apiV1HistoryDownloadRouteGet');
$f3->route('GET /api/v1/phpinfo/@siteId','SicRestApi->apiV1PhpInfoRouteGet');

// TESTS route
$f3->route('GET /tests','SicTests->doTests');


// execute Fat-Free Framework
$f3->run();

