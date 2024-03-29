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
                        // Salto implanet perchè è fatto solo per gestire le foto
                        if ($utility['name'] === 'egea_implanet'){
                            continue;
                        }

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
                            $tempFile = Config::$runtimePath. '/' . $fileNameToDownload;

                            // Download the single file
                            $this->log->info("Downloading file $filePathToDownload" , ['logMail' => false]);
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

                            // Check the filesize to check if the file isn't corrupt todo:: da ricontrollare questo passaggio. Sembra non funzionare
//                            $originalFileSize = filesize($filePathToDownload);
//                            if(filesize($tempFile) != $originalFileSize){
//                                throw new Exception('File size is different '. $fileNameToDownload);
//                            }

                            // Move the file from tmp folder to shared folder
                            $this->log->info("Moving file $filePathToDownload from tmp to shared folder" , ['logMail' => false]);
                            if(!rename($tempFile,Config::$winShare . '/IN/' . $utility['sharedFolder'] .'/' . $fileNameToDownload)){
                                $this->log->customError('Unable to copy the file ' . $fileNameToDownload, ['logMail' => false]);
                                continue;   //continue the application for not block the program
                            }

                            // Insert a new record to GCloud DB
                            $now = new DateTime();
                            $data = $now->format('d/m/Y');
                            $ora = $now->format('H:i:s');

                            $p = [
                                'nome_flusso' => $fileNameToDownload,
                                'data_rilevamento' => $data,
                                'ora_rilevamento' => $ora,
                                'data_trasferimento_cartella_in' => $data,
                                'ora_trasferimento_cartella_in' => $ora,
                                'flag_pronto_per_importazione_da_cartella_in' => 1,
                                'codice_ente' => $utility['codice_ente'],
                                'sede_id' => $utility['sede_id'],
                            ];

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

                            $query->bind_param('sssssiss',
                                $p['nome_flusso'],
                                $p['data_rilevamento'],
                                $p['ora_rilevamento'],
                                $p['data_trasferimento_cartella_in'],
                                $p['ora_trasferimento_cartella_in'],
                                $p['flag_pronto_per_importazione_da_cartella_in'],
                                $p['codice_ente'],
                                $p['sede_id']
                            );

                            $this->log->info("Creating new record on GCloud DB" , ['logMail' => false]);
                            if(!$query->execute()){
                                $this->log->customError('Unable to insert data to GCloud DB for file ' . $fileNameToDownload, ['logMail' => false]);
                                continue;   //continue the application for not block the program
                            }


                            // Insert record into local Database
                            $query = $this->DBLocal->prepare("INSERT INTO files_download (nome_flusso, codice_ente, sede_id) VALUES (?,?,?)");
                            $query->bind_param('sss',
                                $p['nome_flusso'],
                                $p['codice_ente'],
                                $p['sede_id']
                            );

                            $this->log->info("Creating new record on local DB" , ['logMail' => false]);
                            if(!$query->execute()){
                                $this->log->customError('Unable to inset data into local database for file ' . $fileNameToDownload, ['logMail' => false]);
                                continue;   //continue the application for not block the program
                            }

                            // Delete this file from the ftp server only if no errors occured
                            $this->log->info("Deleting file $fileNameToDownload from sftp server" , ['logMail' => false]);
                            if(!$this->ftp->delete($filePathToDownload)){
                                $this->log->customError('Unable to delete the file '. $fileNameToDownload, ['logMail' => false]);
                                continue;   //continue the application for not block the program
                            }

                            $nDownload++;
                        }
                    }
                    $this->log->info('Nuovi files scaricati: ' . $nDownload);
                    break;


                case 'upload':
                    $nUpload = 0;

                    foreach (Config::$utilities as $utility){
                        // Salto implanet perchè è fatto solo per gestire le foto
                        if ($utility['name'] === 'egea_implanet'){
                            continue;
                        }
                        $query = $this
                            ->DBGoogle
                            ->query("SELECT id, nome_flusso, codice_ente, sede_id 
                                            FROM flussi_file 
                                                WHERE codice_ente = '".$utility['codice_ente']."'
                                              --  AND sede_id = '".$utility['sede_id']."'                                                          
                                                AND flag_esportato_cartella_out = 1
                                                AND flag_trasferimento_cartella_out = 0"); // Prendo quelli non ancora inviati

                        $filesToUpload = [];
                        while($data = $query->fetch_assoc()){
                            $filesToUpload[] = $data;
                        };

                        foreach ($filesToUpload as $fileToUpload){
                            $file = Config::$winShare.'/OUT/'.$utility['sharedFolder'].'/'.$fileToUpload['nome_flusso'];
                            $remoteFolder = $utility['ftpFolder'].'/LET/UP/';
                            //$remoteFolder = $utility['ftpFolder'].'/LET/UP/TEST/'; //da attivare in fase di test

                            if(!file_exists($file)){
                                $this->log->customError('File '.$file.', id: '.$fileToUpload['id'].' non presente', ['logMail' => false]);
                                continue;
                            }
                            /**
                            Cambio nome al file che trasferisco in neta2a perchè non gestisce più file con lo stesso nome.
                            Viene fatto per gestire la spedizione parziale di una lavorazione
                             */
                            $fileNameToUpload = $remoteFolder.date('YmdHis').'_'.$fileToUpload['nome_flusso'];
                            if(!$this->ftp->put($fileNameToUpload, $file, FTP_BINARY)){
                                $this->log->customError('Unable to upload file '.$file.', id: '.$fileToUpload['id'].' non presente', ['logMail' => false]);
                                continue;
                            }

                            // Aggiorno il record su google
                            $query = $this->DBGoogle->prepare("UPDATE flussi_file SET flag_trasferimento_cartella_out = 1 WHERE id = ?");
                            $query->bind_param('i', $fileToUpload['id']);
                            if(!$query->execute()){
                                $this->log->customError('Unable to update data with id: '.$fileToUpload['id'].' to Google database', ['logMail' => false]);
                                continue;
                            }

                            //Sposto il file inviato correttamente nella cartella di backup
                            if (!rename($file, Config::$winShare.'/OUT/'.$utility['sharedFolder'].'/backup/'.$fileToUpload['nome_flusso'])){
                                $this->log->info('Unable to move the file into backup directory with id: '.$fileToUpload['id'], ['logMail' => false]);
                            }

                            // Log locale
                            $query = $this->DBLocal->prepare("INSERT INTO files_upload (nome_flusso, codice_ente, sede_id) VALUES (?,?,?)");
                            $query->bind_param('sss',$fileToUpload['nome_flusso'], $utility['codice_ente'], $utility['sede_id']);

                            if(!$query->execute()){
                                $this->log->customError('Unable to save data into local database', ['logMail' => false]);
                                continue;
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
