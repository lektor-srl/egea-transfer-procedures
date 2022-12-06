<?php
namespace Entities;

use Exception;
use FtpClient\FtpClient;

class Ftp extends FtpClient{

    private static ?Ftp $_instance = null;
    private Log $log;

    public function __construct()
    {
        parent::__construct();
        try {
            $this->log = Log::getInstance();

            //$this->connect(Config::$sftpUrl, Config::$sftpUseSsl, Config::$sftpPort); // non funziona tramite sftp
            $this->connect(Config::$sftpUrl);
            $this->login(Config::$sftpUsername, Config::$sftpPassword);
            $this->log->info('Connection SFTP estabilished to '.Config::$sftpUrl);

        }catch (Exception $e){
            throw $e;
        }
    }

    /**
     * @return Ftp Return a new or last Ftp object instance created
     */
    public static function getInstance():Ftp
    {
        if (self::$_instance == null) {
            self::$_instance = new Ftp();
        }
        return self::$_instance;
    }

    /** Provide to upload an entire folder through Ftp
     * @param string $targetDirectory The folder where put the data into
     * @param string $sourceSubPath   The folder where get the data
     * @throws Exception
     */
    public function uploadFolder(string $targetDirectory, string $sourceSubPath):void
    {
        try {
            $this->putAll(Config::$pathAttachments . $sourceSubPath, $targetDirectory);
        }catch (Exception $e){
            throw $e;
        }

    }
    public function getFolder(string $sourceSubPath, string $targetDirectory):void
    {
        try {
            $this->createFolder(Config::$pathFiles.'/'.$targetDirectory);

            $this->getAll($sourceSubPath, Config::$pathFiles.'/'.$targetDirectory);
        }catch (Exception $e){
            throw $e;
        }

    }

    /** Provide to create a folder recursively
     * @param string $folder Folder or pathFolder to create
     * @throws Exception
     */
    private function createFolder(string $folder):void
    {
        try {
            if(!is_dir($folder)){
                mkdir($folder, 777, true);
                $this->log->info('Created folder "'.$folder.'"');
            }
        }catch (Exception $e){
            throw $e;
        }
    }
}
