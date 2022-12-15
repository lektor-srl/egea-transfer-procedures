<?php
namespace Entities;


class Utility {

    public static function checkFreeDiskSpace():bool
    {
        $MBAvailable = round(disk_free_space('/') / 1024 / 1024,0);
        $MBRequired = Config::$diskSpaceRequired;

        Log::getInstance()->info("Disk space available: $MBAvailable MB");
        Log::getInstance()->info("Disk space required: $MBRequired MB");

        return ($MBAvailable <= $MBRequired) ? false : true;
    }

    /**
     * It converts a file from Unix to DOS format
     * @param string tempFilePath The path to the temporary file that will be created.
     * @return bool The operation result, false if failed.
     */
    public static function unixToDos(string &$tempFilePath):bool
    {
        $unixfile = file_get_contents($tempFilePath);
        $dosfile= str_replace("\n", "\r\n", $unixfile );
        file_put_contents($tempFilePath, $dosfile);
        return true;
    }
}
