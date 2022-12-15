<?php
namespace Entities;
use Exception;

/**
 * Class AttachmentsMain
 * @package Entities
 */
class AttachmentsMain{
    private Storage $storage;
    private Ftp $ftp;
    private Log $log;
    private DB $DB;
    private ?string $mode;
    private array $attachmentsDownloaded = [];

    /**
     * AttachmentsMain constructor.
     * @throws Exception
     */
    public function __construct()
    {
        ini_set('display_errors', 1);
        error_reporting(E_ALL);

        try {
            // Check the healthy status
            $this->mode = $this->detectMode();

            $this->log = Log::getInstance();
            $this->storage = Storage::getInstance();
            $this->ftp = Ftp::getInstance();
            $this->DB = DB::getInstance();

            // If no errors detected, launch the application
            $this->exec();

        }catch (Exception $e){
            Log::getInstance()->exceptionError($e);
        }

    }

    /**
     * @throws Exception
     */
    private function exec(){
        try {
            $this->log->info('Start attachments script, "'.$this->mode.'" mode selected');

            switch ($this->mode){
                case 'download':
                    foreach (Config::$utilities as $utility){
                        $this->log->info('Downloading attachments from "'. $utility['name'].'"');
                        $data = $this->storage->downloadRecursiveAttachments('foto/'.$utility['name'].'/lav_', $utility['name'].'/');
                        foreach ($data as $datum){
                            // Fatto in questo modo per avere un array complessivo delle foto di tutte le utility
                            $this->attachmentsDownloaded[] = $datum;
                        }
                    }
                    $this->log->info(count($this->attachmentsDownloaded).' attachments downloaded.');

                    break;

                case 'upload':
                    foreach (Config::$utilities as $utility){
                        $this->log->info('Uploading attachments to "'. $utility['ftpFolder'].'"');

                        //todo:: considerare se caricare i files in modo ricorsivo per intercettarne ogni nome
                        $this->ftp->uploadFolder('/'.$utility['ftpFolder'].'/IMG/UP/testLektor/', $utility['name'].'/');

                        $this->log->info('Utility "'.$utility['name'].'" uploaded to '.$utility['ftpFolder']);
                    }

                    break;

                default:
                    throw new Exception('Error: Malformed mode');
            }


        } catch (Exception $e){
            $this->log->exceptionError($e);

        } finally {
            $this->endProgram();
        }

    }

    /**
     * It provide the endProgram methods
     * @throws Exception
     */
    private function endProgram():void
    {
        try {
            $this->storage->removeLocalFiles($this->attachmentsDownloaded);

            $this->log->info('End '.$this->mode.' attachments script. ');
            $this->log->info("\n\n", ['logDB' => false, 'logFile' => false]);
        }catch (Exception $e){
            throw $e;
        }
    }

    /**
     * It gets the command line arguments, checks if the mode is valid and returns it
     * @return string|null The mode selected by the user.
     * * @throws Exception
     */
    private function detectMode():string|null
    {
        $args = getopt("", ["mode:"]);

        if(!$args || $args == ''){
            throw new Exception('No mode selected: --mode=download|upload');
        }
        if(!in_array($args['mode'], Config::$modesAvailable)){
            throw new Exception('Mode malformed!');
        }

        return $args['mode'];
    }
}
