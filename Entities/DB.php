<?php
namespace Entities;

use Exception;
use mysqli;

class DB extends mysqli {

    private static ?DB $_instance = null;


    public function __construct()
    {
        try {
            parent::__construct(
                Config::$DBProps['host'],
                Config::$DBProps['username'],
                Config::$DBProps['password'],
                Config::$DBProps['dbName']
            );
        }catch (Exception $e){
            throw $e;
        }

    }

    public function __destruct(){
        $this->close();
    }

    /**
     * @return DB Return a new or last DB object instance created
     * @throws Exception
     */
    public static function getInstance():DB
    {
        try {
            if (self::$_instance == null) {
                self::$_instance = new DB();
            }
            return self::$_instance;
        }catch (Exception $e){
            throw $e;
        }

    }

    public function insertLog($job, $level, $message):void
    {
        try {

            $query = $this->prepare('INSERT INTO activity_log (job, level, log) VALUES (?,?,?)');

            $query->bind_param('sss', $job, $level, $message);
            $query->execute();

        }catch (Exception $e){
            throw $e;
        }

    }

}
