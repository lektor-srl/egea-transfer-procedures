<?php
namespace Entities;

use Exception;
use mysqli;

class DB extends mysqli {

    private static ?DB $_localInstance = null;


    public function __construct(string $type = 'local')
    {
        try {
            switch ($type){
                case 'google':
                    parent::__construct(
                        Config::$googleDBProps['host'],
                        Config::$googleDBProps['username'],
                        Config::$googleDBProps['password'],
                        Config::$googleDBProps['dbName']
                    );
                    break;

                default:
                    parent::__construct(
                        Config::$localDBProps['host'],
                        Config::$localDBProps['username'],
                        Config::$localDBProps['password'],
                        Config::$localDBProps['dbName']
                    );
                    break;
            }

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
    public static function getInstance(string $type = 'local'):DB
    {
        try {
            switch ($type){
                case 'google':
                    if (self::$_localInstance == null) {
                        self::$_localInstance = new DB('google');
                    }
                    break;

                default:
                    if (self::$_localInstance == null) {
                        self::$_localInstance = new DB('local');
                    }
                break;
            }

            return self::$_localInstance;
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
