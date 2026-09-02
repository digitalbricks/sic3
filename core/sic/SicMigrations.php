<?php

/*
 * Class holding methods for migrations of the SIC database.
 * This class is used to migrate the database to the latest version,
 * updating the schema and data as needed.
 *
 * NOTE: This class is only for migrations between SIC 3.x versions.
 * It is NOT for the config file migration from SIC 1 or SIC 2 to SIC 3.
 */
class SicMigrations
{
    private $sicDB;
    protected $rootPath;

    function __construct(DB\SQL $sicDB, string $rootPath)
    {
        $this->sicDB = $sicDB;
        $this->rootPath = $rootPath;
        $this->migrate();
    }

    public function migrate():void{
        $this->migrateToVersion_3_5_0();
    }

    /**
     * Migrate the database to version 3.5.0
     * - create setting for concurrent requests (if not exists)
     */
    private function migrateToVersion_3_5_0():void{
        // create setting for concurrent requests (if not exists)
        $concurrent_requests = $this->sicDB->exec('SELECT value FROM settings WHERE name = "concurrent_requests"');
        if(count($concurrent_requests) != 0) return;
        $this->sicDB->exec('INSERT INTO settings (name,value) VALUES ("concurrent_requests","8")');
    }

}
