<?php
namespace Entities;
use Cassandra\Date;
use DateTime;
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
    private array $attachmentsUploaded = [];
    private DateTime $startTime;

    /**
     * AttachmentsMain constructor.
     * @param array $params
     * @throws Exception
     */
    public function __construct(array $params = [])
    {
        ini_set('display_errors', 1);
        error_reporting(E_ALL);
        $this->startTime = new DateTime();

        try {
            // Check the healthy status
            (Utility::checkFreeDiskSpace()) ?: throw new Exception("Not enough disk space available, script interrupted\n");

            $this->mode = $this->detectMode();

            $this->log = Log::getInstance();
            $this->storage = Storage::getInstance();
            $this->ftp = Ftp::getInstance();
            $this->DB = DB::getInstance();

            // If no errors detected, launch the application
            $this->exec($params);

        }catch (Exception $e){
            Log::getInstance()->exceptionError($e);
        }

    }


    /**
     * It downloads or uploads attachments from/to the FTP server
     * @param array $params
     * @throws Exception
     */
    private function exec(array $params = []){
        try {
            $this->log->info('Start attachments script, "'.$this->mode.'" mode selected');

            switch ($this->mode){
                case 'download':
                    $params['currentBatch'] = DB::getInstance('local')->query("select max(batch) as batch from attachments_upload")->fetch_object()->batch + 1;

                    foreach (Config::$utilities as $utility){
                        $this->log->info('Downloading attachments from "'. $utility['name'].'"');

                        $data = $this->storage->downloadRecursiveAttachments($utility, $params);

                        foreach ($data as $datum){
                            // Fatto in questo modo per avere un array complessivo delle foto di tutte le utility
                            $this->attachmentsDownloaded[] = $datum;
                        }
                        $this->log->info(count($data).' attachments downloaded for utility '.$utility['name']);
                    }

                    $this->log->info(count($this->attachmentsDownloaded).' attachments downloaded.');

                    break;

                case 'upload':
                    foreach (Config::$utilities as $utility){
                        $this->log->info('Uploading attachments to "'. $utility['ftpFolder'].'"');
                        $data = []; //Lo resetto per ogni utility
                        //ciclo le lavorazioni
                        $lavorazioni = array_diff(scanDir(Config::$pathAttachments.$utility['name'].'/'), ['.', '..']);

                        foreach ($lavorazioni as $lavorazione){

                            /**
                             * Controllo verticale per implanet.
                             * Questa utility legge per i tre enti, ma le foto devono andare rispettivamente nelle 3 cartelle.
                             * Non c'è una cartella ftp destinata ad implanet
                             */
                            if($utility['name'] === 'egea_implanet'){
                                // Capisco a quale ente fa riferimento questa lavorazione prendendo le info dal DB locale
                                $ente_id = DB::getInstance('local')->query('SELECT ente FROM progressivo_ente WHERE progressivo = '.$lavorazione)->fetch_object()->ente;
                                if(!is_null($ente_id)){
                                    // 1-6 alpiacque | 2-5 tecnoedil | 3-7 alse
                                    switch ($ente_id){
                                        case 1:
                                        case 6:
                                            $utility['ftpFolder'] = 'neta2a_prod_alpiacque';
                                            break;

                                        case 2:
                                        case 5:
                                            $utility['ftpFolder'] = 'neta2a_prod_tecnoedl';
                                            break;

                                        case 3:
                                        case 7:
                                            $utility['ftpFolder'] = 'neta2a_prod_alse';
                                            break;
                                    }
                                }else{
                                    $this->log->customError('ente_id not found for progressivo '. $lavorazione);
                                }
                            }


                            $folder = '/'.$utility['ftpFolder'].'/IMG/UP/PARK/'.$lavorazione.'/';
                            if(!is_dir($folder)){
                                $this->ftp->mkdir($folder, true);
                                $this->log->info('Create folder on ftp server "'.$folder.'"');
                            }
                            $data = $this->ftp->uploadFolder('/'.$utility['ftpFolder'].'/IMG/UP/PARK/'.$lavorazione.'/', $utility['name'].'/'.$lavorazione.'/');

                            foreach ($data as $datum){
                                // Fatto in questo modo per avere un array complessivo delle foto caricate correttamente di tutte le lavorazioni di tutte le utility
                                $this->attachmentsUploaded[] = $datum;
                            }

                            $this->log->info(count($data).' attachments uploaded for utility '.$utility['name'].' and lavorazione '.$lavorazione);
                        }
                        //$data = $this->ftp->uploadFolder('/'.$utility['ftpFolder'].'/IMG/UP/', $utility['name'].'/');



                    }

                    $this->log->info(count($this->attachmentsUploaded).' total attachments uploaded.');

                    // Remove only the file are correctly uploaded
                    $this->storage->removeLocalFiles($this->attachmentsUploaded);

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
            $endTime = new DateTime();
            $this->log->info('End '.$this->mode.' attachments script. ');
            //$this->log->info('Executed in ' . $this->startTime->diff(new DateTime())->format('m') . ' minutes');
            $this->log->info('Executed in ' . $endTime->diff($this->startTime)->s . ' seconds');
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
