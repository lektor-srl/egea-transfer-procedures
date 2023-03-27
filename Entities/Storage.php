<?php /** @noinspection PhpFullyQualifiedNameUsageInspection */

namespace Entities;
use Exception;
use Google\Cloud\Storage\Bucket;
use Google\Cloud\Storage\StorageClient;
use DateTime;

class Storage extends StorageClient{

    private static ?Storage $_instance = null;
    private DateTime $today;
    private Bucket $bucket;
    private Log $log;
    private array $attachmentsDownloaded;

    public function __construct()
    {
        $this->log = Log::getInstance();

        parent::__construct([
            'keyFilePath' => Config::$keyFile
        ]);

        $this->today = new DateTime();
        $this->bucket = self::bucket(Config::$bucketName);
        $this->log->info('Google cloud connection estabilished and bucket "'.$this->bucket->name().'" selected');
    }

    /**
     * @return Storage Return a new or last Storage object instance created
     */
    public static function getInstance():Storage
    {
        if (self::$_instance == null) {
            self::$_instance = new Storage();
        }
        return self::$_instance;
    }

    /** Provide to download the attachments from GCloud recursively
     * @param array $utility
     * @param array $params If set, provide some custom params [limit, dateFrom]
     * @return array                An array containing the filePath+fileName of files downloaded
     * @throws Exception
     */
    public function downloadRecursiveAttachments(array $utility, array $params = []):array
    {
        $this->attachmentsDownloaded = [];
        $lettureDB = [];


        try {
            /**
                1) prendo tutte le lavorazioni secondo la data di riferimento
                2) per ogni lavorazione, mi prendo il progressivo e cerco sul bucker filtrando per quella lavorazione => /foto/lav_n
                3) mi prendo tutte le foto di questa lavorazione
             */

            // Inietto dei parametri per usarli nella query di ricerca lettura
            $params['sedeDatabase'] = $utility['sedeDatabase'];
            $params['sede_id'] = $utility['sede_id'];

            // Check and create if not exist the temp folder
            $this->createFolder(Config::$pathAttachments.$utility['name']);

            $lettureDB = $this->getLettureDB($params);

            $this->log->info("Trovati seguenti progressivi: " . json_encode($lettureDB['progressivi']));

            /** Per ogni progressivo mi recupero le foto dal bucket di google */
            $c = 0; // Contatore foto totali per singolo progressivo
            foreach ($lettureDB['progressivi'] as $progressivo){
                //Raggruppo per progrssivo
                $this->createFolder(Config::$pathAttachments . $utility['name'] . "/" . $progressivo);
                $c = 0;
                $fileNames = []; // resetto i nomi dei files ad ogni nuovo progressivo

                // Get the bucket information
                $objects = $this->bucket->objects([
                    'resultLimit' => $params['limit'],
                    'prefix' => 'foto/' . $utility['name'] . '/lav_' . $progressivo
                ]);

                /** Per ogni oggetto del bucket, eseguo i controlli e scarico i metadata della foto */
                foreach ($objects as $object) {
                    if(!$this->checkObject($object, $params)){
                        continue;
                    }
                    $fileNames[] = $this->getFilename($object->name());
                }


                /** Per ogni oggetto di cui mi sono preso i metadata, faccio il match con la matrice dei dati da DB e se corrisponde scarico la foto */
                foreach ($fileNames as $fileName){
                    foreach ($fileName as $key => $data){

                        // prendo i dati nel rispettivo array del DB
                        if(array_key_exists($key, $lettureDB['data'])){
                            /** Scarico il file */

                            //Riformatto la data
                            $dmY = substr($data['data'], 6, 2) . substr($data['data'], 4, 2) . substr($data['data'], 0, 4);

                            $object = $this->bucket->object($data['originalFilename']);

                            //format destinazione
                            //p.ivacliente_"codice_utente"_"matricola"_dmY_00#.jpg
                            $newName = $utility['partita_iva'] . "_" . $lettureDB['data'][$key]['codice_utente'] . "_" . $lettureDB['data'][$key]['matricola'] . "_" . $dmY . "_00" . $data['index'] . ".jpg";

                            // Inserito controllo e continuo se la singola foto va in errore
                            try{
                                // Raggruppo per progressivi
                                $object->downloadToFile(Config::$pathAttachments . $utility['name'] . "/" . $progressivo . "/". $newName);
                                $c++;
                            }catch (exception $e){
                                $dateTime = new DateTime();
                                $logText = PHP_EOL.$dateTime->format('Y-m-d H:i:s')." - Line: ".$e->getLine().' - Warning: '.$e->getMessage();
                                $logText.= PHP_EOL."Waiting 10 seconds for a possible connection lost...";
                                sleep(10);
                                Log::getInstance()->info($logText);
                                continue;
                            }


                            $this->log->info('Downloaded ' . $newName, ['logDB' => false]);
                            $this->attachmentsDownloaded[] = Config::$pathAttachments . $utility['name'] . "/" . $newName;
                        }

                    }
                }
                $this->log->info("Scaricate $c foto per progressivo $progressivo");
            }

            return $this->attachmentsDownloaded;

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
                mkdir($folder, 0777, true);
                $this->log->info('Create folder "'.$folder.'"');
            }
        }catch (Exception $e){
            throw $e;
        }
    }

    /** Provide to extract only the fileName from the GCloud object name,
     * splitting into it's informations
     * @param string $filePath  The object name got from GCloud
     * @return array           An array containing the splitted filename
     */
    private function getFilename(string $filePath):array
    {
        try {

            // Il file ha il seguente formato: progressivo_sequenza_tipo_dataora.jpg
            $record = null;
            $strings = explode('/', $filePath);
            $filename = explode('_', end($strings));

            $progressivo = $filename[0];
            $sequenza = $filename[1];

            $record[$progressivo."_".$sequenza]['originalFilename'] = $filePath;
            $record[$progressivo."_".$sequenza]['index'] = $filename[2];
            $record[$progressivo."_".$sequenza]['data'] = $filename[3];
            $record[$progressivo."_".$sequenza]['ora'] = explode('.', $filename[4])[0];

            return $record;

        } catch (Exception $e){
            throw $e;
        }
    }

    /**
     * It takes a date range and returns an array of arrays containing the data from the database
     *
     * @param array $params
     * @return array with two keys: data and progressivi.
     */
    private function getLettureDB($params):array
    {
        $data = [];
        $progressivi = [];

        $sql = 'SELECT 	l.progressivo, 
                            l.sequenza, 
                            l.codice_utente, 
                            l.matricola, 	
                            DATE_FORMAT(CONCAT(
                                    SUBSTRING(lv.lavorazione_data_out, 1,4), "-",
                                    SUBSTRING(lv.lavorazione_data_out, 5,2), "-",
                                    SUBSTRING(lv.lavorazione_data_out, 7,2)
                                ),"%Y-%m-%d") AS lavorazione_data_out
                 FROM '.$params["sedeDatabase"].'.letture l
                    INNER JOIN lavorazione lv ON l.progressivo = lv.lavorazione_progressivo
                    WHERE lv.lavorazione_sede_id = '.$params["sede_id"].'
                        AND DATE_FORMAT(CONCAT(
                                SUBSTRING(lv.lavorazione_data_out, 1,4), "-",
                                SUBSTRING(lv.lavorazione_data_out, 5,2), "-",
                                SUBSTRING(lv.lavorazione_data_out, 7,2)
                            ),"%Y-%m-%d") BETWEEN "'.$params["dateFrom"].'" AND "'.$params["dateTo"].'"';

        $result = DB::getInstance('google')->query($sql);
        while($row = $result->fetch_assoc()){

        /* Costruisco un array con questa struttura
           "progressivo_sequenza" = [
               "codice_utente" = n
               "matricola" = n
           ]
        */
            $progressivo = str_pad($row['progressivo'], 6, '0', STR_PAD_LEFT);
            $sequenza = str_pad($row['sequenza'], 6, '0', STR_PAD_LEFT);

            //Popolo l'array contenente i dati singoli
            $data[$progressivo."_".$sequenza]['codice_utente'] = $row['codice_utente'];
            $data[$progressivo."_".$sequenza]['matricola'] = $row['matricola'];

            // Popolo l'array con solo la lista dei progressivi
            $progressivi[] = $progressivo;
        }

        $progressivi = array_unique($progressivi);

        return [
            'data' => $data,
            'progressivi' => $progressivi
        ];
    }

    /** Check if the object is valid to download or not
     * @param Object $object The GCloud object to check
     * @param $params
     * @return bool
     * @throws Exception
     */
    private function checkObject(object $object, $params):bool
    {
        try {
            $contentType = $object->info()['contentType'] ?? $object->info()['kind'];
//
//            if (false
//                || $contentType != 'image/jpeg' // Controlla il formato della foto
//            ) {
//                $this->log->customError('Oggetto scartato - tipologia: ' . $contentType . " - name: ".$object->name);
//                return false; }

            if($object->name()){
                if(!str_contains($object->name(), '.jpg')){
                    $this->log->customError('Oggetto scartato - tipologia: ' . $contentType . " - name: ".$object->name(), ['logMail' => false]);
                    return false;
                }
            }


            return true;

        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * It removes files from the local server
     * @param array $files array of files to be removed
     * @throws Exception
     */
    public function removeLocalFiles(array $files):void
    {
        try {
            foreach ($files as $file){
                if(is_file($file)){
                    unlink($file);
                    $this->log->info('Removed file from local '.$file, ['logDB' => false]);
                }
            }
        }catch (Exception $e){
            throw $e;
        }

    }
}
