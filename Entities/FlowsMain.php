<?php
namespace Entities;
use Exception;

/**
 * Class AttachmentsMain
 * @package Entities
 */
class FlowsMain{
    private Storage $storage;
    private Ftp $ftp;
    private Log $log;
    private DB $DB;

    public function __construct()
    {
        ini_set('display_errors', 1);
        error_reporting(E_ALL);

        try {
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


    private function exec(){
        try {
            $this->log->info('Start flows script, "'.$this->mode.'" mode selected');

            switch ($this->mode) {
                case 'download':
                    foreach (Config::$utilities as $utility){
                        // Get the folder from Ftp
                        if($utility['name'] != 'egea_alpiacque'){continue; } //todo:: debug - da togliere in prod

                        $this->log->info('Downloading files from "'. $utility['name'].'"');


                        // 1- Scan della cartella FTP per prendere i nomi dei files
                        $folder = $utility['ftpFolder'] . '/LET/DW';

                        $filesFtp = [];
                        foreach ($this->ftp->scanDir($folder) as $key => $fileData){
                            $filesFtp[] = $fileData['name'];
                        }

                        //2- Controllo sul DB di Google se esistono i files
                        $filesDB = [
                            '02_0000000866_P1_70_1.csv',
                            '02_P1_31_3110_2.csv',
                            '02_P1_31_3111_3.csv',
                            '02_P1_31_3115_5.csv',
                            '02_P1_31_313_7.csv',
                        ];//todo:: sql-> select nome_flusso from flussi_file where nome_flusso = $filename

                        //3- Scarico solo i files che non ci sono ancora
                        $filesToDownload = array_diff($filesFtp, $filesDB);

                        foreach ($filesToDownload as $fileNameToDownload){

                            $filePathToDownload = $folder . '/' . $fileNameToDownload;

                            if(!$this->ftp->get(Config::$runtimePath. '/' . $fileNameToDownload, $filePathToDownload, 1)){
                                throw new Exception('Unable to download the file '. $fileNameToDownload);
                            }

                            //4- Sposto il file nell'ambiente shared
                            if(!rename(
                                Config::$runtimePath.'/'. $fileNameToDownload,
                                Config::$winShare . '/IN/' . $utility['sharedFolder'] .'/Acqua Massiva/' . $fileNameToDownload)){
                                throw new Exception('Unable to copy the file ' . $fileNameToDownload);
                            }

                            $this->log->info('Files scaricati');
                        }

                    }
                    break;


                case 'upload':
                    echo "ok";
                    break;

            }
        } catch (Exception $e){
            $this->log->exceptionError($e);

        } finally {
            $this->endProgram();
        }

    }

    private function endProgram():void
    {
        $this->log->info('End flows script. ');
        $this->log->info("\n\n", ['logDB' => false, 'logFile' => false]);
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
