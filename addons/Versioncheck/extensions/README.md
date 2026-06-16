# Extensions for Versioncheck addon
If the built in functionality of the addon does not cover your CMS for querying the latest available version,
you can create your own extension for it. 

## How to create an extension
Have a look at the bundled ProcesswireVcExtension.php file, which is an extension for the ProcessWire CMS/CMF.
It demonstrates the basic structure of an extension, the filename and class naming convention, 
and how to implement the required static method.

### Steps
Lets a assume we have a CMS called "Mycms" and we want to create an extension for it. We would do the following:
1. Create a new file in the "extensions" folder of the Versioncheck addon, and name it "MycmsVcExtension.php" (the "VcExtension.php" is mandatory).
2. Create a new class in that file, and name it "MycmsVcExtension" (same as filename, without .php) and **public static function** called "getLatestVersion".
3. This static function must return the version number as string, the logic for querying the latest version is up to you.
4. Log in to SIC and navigate to the Versioncheck addon settings "CMS Definitionen" and add a new entry for your CMS
5. In the "endpoint" field you would enter "x_mycms" (the "x_" prefix is mandatory, indicating the use of an extension).

Thats it. If the extension is implemented correctly, the Versioncheck addon will use it to query the latest version for 
your CMS and display it in the overview.

## Under the hood
* when loading the Versioncheck overview page, the addon iterates over all sites and the according CMS definitions.
* when it encouters a CMS definition with an endpoint starting with "x_", it will try to load the corresponding extension class.
* it strips the "x_" prefix from the endpoint and appends "VcExtension" to it, to get the class name of the extension (e.g. "MycmsVcExtension").
* it looks for a file with the same name as the class in the "extensions" folder (e.g. "MycmsVcExtension.php") and includes it.
* it calls the static method "getLatestVersion" of the extension class to get the latest version number for the CMS.
