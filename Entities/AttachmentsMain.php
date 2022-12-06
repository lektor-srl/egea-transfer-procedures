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
    private int $nDownload = 0;

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
            $this->log->info('Start script, "'.$this->mode.'" mode selected');

            switch ($this->mode){
                case 'download':
                    foreach (Config::$utilities as $utility){
                        $this->log->info('Downloading attachments from "'. $utility['name'].'"');
                        $this->nDownload += $this->storage->downloadRecursiveAttachments('foto/'.$utility['name'].'/lav_', $utility['name'].'/');
                    }
                    $this->log->info($this->nDownload.' attachments downloaded.');

                    break;

                case 'upload':
                    foreach (Config::$utilities as $utility){
                        $this->log->info('Uploading attachments to "'. $utility['uploadFolder'].'"');

                        $this->ftp->uploadFolder('/'.$utility['uploadFolder'].'/IMG/UP/testLektor/', $utility['name'].'/');

                        $this->log->info('Utility "'.$utility['name'].'" uploaded to '.$utility['uploadFolder']);
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

    private function endProgram():void
    {
        $this->log->info('End '.$this->mode.' attachments script. ');
        $this->log->info("\n\n", ['logDB' => false, 'logFile' => false]);
        // todo:: prevedere la cancellazione della cartella temporanea
    }

    /**
     * @return string|null Attachment mode selected
     * @throws Exception
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
