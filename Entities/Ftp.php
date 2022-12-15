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
            // todo::Prevedere la cancellazione della cartella una volta finito
        }catch (Exception $e){
            throw $e;
        }

    }


    /**
     * It creates a folder in the target directory, then it gets all the files from the source sub path and puts them in
     * the target directory
     * @param string sourceSubPath The path to the folder you want to download.
     * @param string targetDirectory The directory where the files will be downloaded.
     * @return array An array of all the files in the target directory.
     */
    public function getFolder(string $sourceSubPath, string $targetDirectory):array
    {
        try {
            $this->createFolder(Config::$pathFiles.'/'.$targetDirectory);
            $this->getAll($sourceSubPath, Config::$pathFiles.'/'.$targetDirectory);

            return array_diff(scandir(Config::$pathFiles.'/'.$targetDirectory), array('..', '.'));
        }catch (Exception $e){
            throw $e;
        }

    }

    public function scanDir($directory = '.', $recursive = false)
    {
        return parent::scanDir($directory, $recursive);
    }


    /**
     * It scans a folder on an FTP server and returns an array of file names
     *
     * @param array utility the utility name
     *
     * @return array An array of files from the FTP server.
     */
    public function getIndexFilesFromFtp(array $utility):array
    {
        $filesFtp = [];
        $folder = $utility['ftpFolder'] . '/LET/DW';

        foreach ($this->scanDir($folder) as $key => $fileData){
            //if(str_contains($fileData['name'], 'LEKTOR')){  //todo::serve per testare - da togliere in prod
            $filesFtp[] = $fileData['name'];
            //}
        }
        return $filesFtp;
    }



    /** Provide to create a folder recursively
     * @param string $folder Folder or pathFolder to create
     * @throws Exception
     */
    private function createFolder(string $folder):void
    {
        try {
            if(!is_dir($folder)){
                mkdir($folder, 0777, true);
                $this->log->info('Created folder "'.$folder.'"');
            }
        }catch (Exception $e){
            throw $e;
        }
    }
}
