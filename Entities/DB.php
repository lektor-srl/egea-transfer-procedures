<?php
namespace Entities;

use Exception;
use mysqli;

class DB extends mysqli {

    private static ?DB $_localInstance = null;
    private static ?DB $_googleInstance = null;


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
                    if (self::$_googleInstance == null) {
                        self::$_googleInstance = new DB('google');
                    }
                    return self::$_googleInstance;

                default:
                    if (self::$_localInstance == null) {
                        self::$_localInstance = new DB('local');
                    }
                    return self::$_localInstance;
            }


        }catch (Exception $e){
            throw $e;
        }

    }

    /**
     * > This function inserts a log entry into the database
     *
     * @param string job The name of the job that is running.
     * @param string level This is the level of the log. It can be one of the following: info, error
     * @param string message The message to be logged.
     */
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

    /**
     * It gets the names of the files from the database
     *
     * @param array utility array
     * @return array An array of files from the database.
     */
    public function getIndexFilesFromDB(array $utility):array
    {
        $filesDB = [];
        $query = $this->query
        ("SELECT nome_flusso FROM flussi_file 
                WHERE codice_ente = '".$utility['codice_ente']."' 
                AND sede_id = '".$utility['sede_id']."'");

        while($data = $query->fetch_assoc()){
            $filesDB[] = $data['nome_flusso'];
        }

        return $filesDB;
    }

}
