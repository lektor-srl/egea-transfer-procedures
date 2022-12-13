<?php
namespace Entities;
use DateTime;
use Exception;

/**
 * Class AttachmentsMain
 * @package Entities
 */
class FlowsMain{
    private Storage $storage;
    private Ftp $ftp;
    private Log $log;
    private DB $DBLocal;
    private DB $DBGoogle;

    public function __construct()
    {
        ini_set('display_errors', 1);
        error_reporting(E_ALL);

        try {
            $this->mode = $this->detectMode();

            $this->log = Log::getInstance();
            $this->storage = Storage::getInstance();
            $this->ftp = Ftp::getInstance();
            $this->DBLocal = DB::getInstance();
            $this->DBGoogle = DB::getInstance('google');

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
                    $nDownload = 0;
                    foreach (Config::$utilities as $utility){
                        $this->log->info('Downloading files from "'. $utility['name'].'"');


                        // Scan ftp Folder for index files
                        $filesFtp = [];
                        $filesFtp = $this->ftp->getIndexFilesFromFtp($utility);

                        // Check on GCloud DB which files are already worked. If a file is present, it will not handle again
                        $filesDB = [];
                        $filesDB = $this->DBGoogle->getIndexFilesFromDB($utility);


                        // Download only file aren't handled already
                        $filesToDownload = array_diff($filesFtp, $filesDB);

                        foreach ($filesToDownload as $fileNameToDownload){

                            $folder = $utility['ftpFolder'] . '/LET/DW';
                            $filePathToDownload = $folder . '/' . $fileNameToDownload;
                            //$originalFileSize = filesize($filePathToDownload);
                            $tempFile = Config::$runtimePath. '/' . $fileNameToDownload;

                            // Download the single file
                            if(!$this->ftp->get($tempFile, $filePathToDownload, 1)){
                                $this->log->customError('Unable to download the file '. $fileNameToDownload, ['logMail' => false]);
                                continue;   //continue the application for not block the program
                            }

                            // Check if the file is valid
                            if(!is_file($tempFile)){
                                $this->log->customError('File not correct '. $fileNameToDownload, ['logMail' => false]);
                                continue;   //continue the application for not block the program
                            }

                            // Convert the file into DOS format for be able to be readble to
                            if(!Utility::unixToDos($tempFile)){
                                $this->log->customError('Unable to convert into dos format the file '. $fileNameToDownload, ['logMail' => false]);
                                continue;   //continue the application for not block the program
                            }

                            // Controllo la dimensione del file todo:: da verificare questo passaggio
//                            if(filesize($tempFile) != $originalFileSize){
//                                throw new Exception('File size is different '. $fileNameToDownload);
//                            }

                            if(!rename($tempFile,Config::$winShare . '/IN/' . $utility['sharedFolder'] .'/' . $fileNameToDownload)){
                                $this->log->customError('Unable to copy the file ' . $fileNameToDownload, ['logMail' => false]);
                                continue;   //continue the application for not block the program
                            }

                            //5- Scrittura a DB google nuovo record
                            $now = new DateTime();
                            $data = $now->format('d/m/Y');
                            $ora = $now->format('H:i:s');

                            $query = $this->DBGoogle->prepare(
                                "INSERT INTO flussi_file (
                                         nome_flusso,
                                         data_rilevamento, 
                                         ora_rilevamento, 
                                         data_trasferimento_cartella_in, 
                                         ora_trasferimento_cartella_in, 
                                         flag_pronto_per_importazione_da_cartella_in,
                                         codice_ente,
                                         sede_id) 
                                        VALUES (?,?,?,?,?,?,?,?)");
                            $flag = 1; //todo:: da refactor
                            $query->bind_param('sssssiss',$fileNameToDownload, $data, $ora, $data, $ora, $flag, $utility['codice_ente'], $utility['sede_id']);
                            $query->execute();


                            //6- Log a DB locale dei files passati
                            $query = $this->DBLocal->prepare("INSERT INTO files_download (nome_flusso, codice_ente, sede_id) VALUES (?,?,?)");
                            $query->bind_param('sss',$fileNameToDownload, $utility['codice_ente'], $utility['sede_id'] );

                            if(!$query->execute()){
                                throw new Exception('Unable to save data into local database');
                                //todo:: blocca lo script, considerare se fare un continue per passare al prossimo file
                            }

                            $nDownload++;
                        }
                    }
                    $this->log->info('Nuovi files scaricati: ' . $nDownload);
                    break;


                case 'upload':
                    $nUpload = 0;

                    foreach (Config::$utilities as $utility){
                        $query = $this
                            ->DBGoogle
                            ->query("SELECT id, nome_flusso, codice_ente, sede_id 
                                            FROM flussi_file 
                                                WHERE codice_ente = '".$utility['codice_ente']."'
                                                AND sede_id = '".$utility['sede_id']."'                                                          
                                                AND flag_trasferimento_cartella_out = 1");

                        $filesToUpload = [];
                        while($data = $query->fetch_assoc()){
                            $filesToUpload[] = $data;
                        };

                        foreach ($filesToUpload as $fileToUpload){
                            $file = Config::$winShare.'/OUT/'.$utility['sharedFolder'].'/'.$fileToUpload['nome_flusso'];
                            $remoteFolder = $utility['ftpFolder'].'/LET/UP/';

                            if(!is_file($file)){
                                $this->log->customError('File '.$file.', id: '.$fileToUpload['id'].' non presente');
                                continue;

                                //throw new Exception('File '.$fileToUpload['nome_flusso'].' non presente');
                            }

                            if(!$this->ftp->put($remoteFolder.$fileToUpload['nome_flusso'], $file, 1)){
                                throw new Exception('Unable to upload file');
                                //todo:: considerare se bloccare lo script o continuare
                            }

                            // Aggiorno il record su google
                            $query = $this->DBGoogle->prepare("UPDATE flussi_file SET flag_esportato_cartella_out = 1 WHERE id = ?");
                            $query->bind_param('i', $fileToUpload['id']);
                            if(!$query->execute()){
                                throw new Exception('Unable to update data with id: '.$fileToUpload['id'].' to Google database');
                                //todo:: considerare se continuare invece di bloccare lo script
                            }

                            // Log locale
                            $query = $this->DBLocal->prepare("INSERT INTO files_upload (nome_flusso, codice_ente, sede_id) VALUES (?,?,?)");
                            $query->bind_param('sss',$fileToUpload['nome_flusso'], $utility['codice_ente'], $utility['sede_id']);

                            if(!$query->execute()){
                                throw new Exception('Unable to save data into local database');
                            }
                            $this->log->info('File '.$fileToUpload['nome_flusso']. ' uploaded into '.$remoteFolder);
                            $nUpload++;
                        }
                    }

                    $this->log->info('End script, '.$nUpload.' files uploaded');
                    break;

            }
        } catch (Exception $e){
            $this->log->exceptionError($e);

        } finally {
            $this->endProgram();
        }

    }

    /**
     * It ends the program.
     */
    private function endProgram():void
    {
        $this->log->info('End flows script. ');
        $this->log->info("\n\n", ['logDB' => false, 'logFile' => false]);
    }


    /**
     * It gets the command line arguments, checks if the mode is valid and returns it
     * @throws Exception
     * @return string|null The mode selected by the user.
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
