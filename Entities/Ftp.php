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

    public function uploadFolder(string $targetDirectory, string $sourceSubPath):void
    {
        try {
            $this->putAll(Config::$pathAttachments . $sourceSubPath, $targetDirectory);
        }catch (Exception $e){
            throw $e;
        }

    }
}
