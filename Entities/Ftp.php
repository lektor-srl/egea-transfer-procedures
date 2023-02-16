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



    /**
     * It uploads a folder to the server
     *
     * @param string $targetDirectory The directory on the server where the files will be uploaded.
     * @param string $sourceSubPath The path to the folder you want to upload.
     *
     * @return array An array of files that have been uploaded.
     */
    public function uploadFolder(string $targetDirectory, string $sourceSubPath):array
    {
        $data = [];
        try {
            //$this->putAll(Config::$pathAttachments . $sourceSubPath, $targetDirectory);
            $files = array_diff(scanDir(Config::$pathAttachments.$sourceSubPath), ['.', '..']);
            foreach ($files as $file){
                if($this->put($targetDirectory.$file, Config::$pathAttachments.$sourceSubPath.$file, 1)){
                    $data[] = Config::$pathAttachments.$sourceSubPath.$file;
                    $this->log->info('Uploaded ' . $sourceSubPath.$file, ['logDB' => false]);
                }else{
                    $this->log->info('Error - Not uploaded ' . $sourceSubPath.$file, ['logDB' => false]);
                }
                $i++;
            }

            return $data;
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
            $filesFtp[] = $fileData['name'];
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
