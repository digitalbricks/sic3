<?php

/**
 * This is an example extension for the Versioncheck addon for SIC3.
 * Such extension can be used to query the latest version of systems, not bundled with the Versioncheck addon.
 *
 * In this example we use ProcessWire CMS as an example, because it does not make use of Github Releases.
 * Instead, like the official ProcessWireUpgrade module for the CMS, we query the latest version by parsing the
 * ProcessWire.php file in the ProcessWire Github repository, where the version is defined as constants.
 *
 * Each VersionCheck extension must meet the naming convention of the system name followed by "VcExtension",
 * e.g. "ProcesswireVcExtension" for ProcessWire, "ExampleVcExtension" for Example CMS, etc. There has to be
 * a class with the same name as the file name, and it must implement a static method "getLatestVersion()",
 * which returns the latest version of the system as a string, e.g. "3.0.0".
 *
 * To make use of an extension in the Versioncheck addon, simply add "x_extensionname" to the "endpoint" setting
 * of a system ("CMS-Definitionen"). So for this extension, that entry is "x_processwire".
 * See included setup.jpg screenshot.
 *
 * The VersioncheckController does this
 * – if a system endpoint starts with "x_", it will
 * --- trim the "x_" (e.g. "x_processwire" becomes "processwire")
 * ––– the first letter of the remaining string is capitalized (e.g. "processwire" becomes "Processwire")
 * ––– for class name "VcExtension" is appended to it (e.g. "Processwire" becomes "ProcesswireVcExtension")
 * ––– checks if a file in /addons/Versioncheck/extensions/ with the name of the class exists (e.g. "ProcesswireVcExtension.php")
 * --- if the file exists, it is included and the static method getLatestVersion() is called to get the latest version of the system.
 *
 */
class ProcesswireVcExtension {
    /**
     * Mandatory method to return the lastest version of the system
     * @return string
     */
    public static function getLatestVersion() {
        $githubResponse = self::loadGithubFile();
        if(!$githubResponse){ return null; }

        preg_match('/const\s+versionMajor\s*=\s*(\d+)\s*;/', $githubResponse, $major);
        preg_match('/const\s+versionMinor\s*=\s*(\d+)\s*;/', $githubResponse, $minor);
        preg_match('/const\s+versionRevision\s*=\s*(\d+)\s*;/', $githubResponse, $revision);

        $versionMajor    = $major[1] ?? null;
        $versionMinor    = $minor[1] ?? null;
        $versionRevision = $revision[1] ?? null;

        if(!is_null($versionMajor) && !is_null($versionMinor) && !is_null($versionRevision)){
            return "$versionMajor.$versionMinor.$versionRevision";
        }

        return null;
    }


    private static function loadGithubFile(){
        $url = "https://raw.githubusercontent.com/processwire/processwire/master/wire/core/ProcessWire.php";
        $headers = ['User-Agent: SIC3-VersionCheck/1.0'];
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


}