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
     * @param string $prefix        The GCloud bucket subfolder where the program get the attachments from
     * @param string|null $subPath  The subfolder where the program save the attachments to
     * @param int|null $limit       If set, provide a limit on Gcloud searching
     * @return int                  The total attachments downloaded
     * @throws Exception
     */
    public function downloadRecursiveAttachments(string $prefix, string $subPath = null, int $limit = null):int
    {
        try {
            $counter = 0;
            // Check and create if not exist the temp folder
            $this->createFolder(Config::$pathAttachments.$subPath);

            // Get the bucket information
            $objects = $this->bucket->objects([
                'resultLimit' => $limit,
                'prefix' => $prefix
            ]);

            $i = 0; //todo::debug - da togliere

            foreach ($objects as $object) {

                if($i >= Config::$maxDownload){
                    throw new Exception('Interrotto inaspettatamente - controllato');
                    //return $counter;
                } //todo::debug - da togliere

                if(!$this->checkObject($object)){
                    continue;
                }

                $fileName = $this->getFilename($object->name());

                $object->downloadToFile(Config::$pathAttachments . $subPath . $fileName);

                $this->log->info('Downloaded '.$fileName, ['logDB' => false]);

                $counter++;
                $i++;
            }

            return $counter;
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
                mkdir($folder, 777, true);
                $this->log->info('Create folder "'.$folder.'"');
            }
        }catch (Exception $e){
            throw $e;
        }
    }

    /** Provide to extract only the fileName from the GCloud object name
     * @param string $filePath  The object name got from GCloud
     * @return string           The filename with the extension included
     */
    private function getFilename(string $filePath):string
    {
        try {
            $strings = explode('/', $filePath);
            return end($strings);

        } catch (Exception $e){
            throw $e;
        }
    }

    /** Check if the object is valid to download or not
     * @param Object $object The GCloud object to check
     * @return bool 
     * @throws Exception
     */
    private function checkObject($object):bool
    {
        try {
            $lastUpdated = new DateTime($object->info()['updated']);

            if( false
                || $object->info()['contentType'] != 'image/jpeg'
                // || $lastUpdated->format(Config::$dateFormatCheck) != $this->today->format(Config::$dateFormatCheck)  //todo:: da attivare in prod
            ){ return false; }

            return true;

        } catch (Exception $e) {
            throw $e;
        }

    }
}
