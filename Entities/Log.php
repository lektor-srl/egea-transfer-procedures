<?php
namespace Entities;

use DateTime;
use Exception;

class Log{
    private static ?Log $_instance = null;
    private $_file;
    private DB $_DB;
    private Mail $_mail;

    public function __construct()
    {

        $date = new DateTime();

        try {
            $fileName = $date->format(Config::$logFileNameFormat).'.log';

            if(!is_dir(Config::$logPath)) {
                mkdir(Config::$logPath, 0777, true);
            }

            touch(Config::$logPath.$fileName);
            chmod(Config::$logPath.$fileName, 0777);

            $this->_file = fopen(Config::$logPath.$fileName, 'a+', );
            $this->_DB = DB::getInstance('local');
            $this->_mail = Mail::getInstance();

        }catch (Exception $e){
            throw $e;
        }
    }

    public function __destruct()
    {
        fclose($this->_file);
    }

    /**
     * @return Log Return a new or last Log object instance created
     */
    public static function getInstance():Log
    {
        if (self::$_instance == null) {
            self::$_instance = new Log();
        }
        return self::$_instance;
    }

    /**
     * It logs a message to the console, to a file and to a database
     *
     * @param string $message the message to log
     * @param array $logPropsOverride an array of properties to override the default ones.
     */
    public function info(string $message, array $logPropsOverride = []):void
    {
        try {
            $logProps = Config::$logProps;

            if(!empty($logPropsOverride)){
                foreach ($logPropsOverride as $key => $value){
                    $logProps[$key] = $value;
                }
            }

            $dateTime = new DateTime();
            $dateTime = $dateTime->format('Y-m-d H:i:s');

            if($logProps['logFile']){
                $logText = PHP_EOL.$dateTime." - Info: ".$message;
                fwrite($this->_file, $logText);
            }

            if($logProps['logConsole']){
                echo PHP_EOL,$message;
            }

            if($logProps['logDB']){
                $this->_DB->insertLog('attachments', 'info', $message);  //todo:: trovare un modo per identificare il job a seconda del job lanciato
            }

        }catch (Exception $e){
            throw $e;
        }

    }

    /**
     * This function is used to log a custom error message
     *
     * @param string message The message to be logged.
     * @param array logPropsOverride This is an array of key/value pairs that will override the default log properties.
     */
    public function customError(string $message, array $logPropsOverride = []):void
    {
        $e = new Exception($message);
        $this->exceptionError($e, $logPropsOverride);
    }


    /**
     * It logs the error message to the console, file, database, and/or email
     *
     * @param Exception $e
     * @param array $logPropsOverride This is an array of key/value pairs that will override the default logProps array.
     * @throws Exception
     */
    public function exceptionError(Exception $e, array $logPropsOverride = []):void
    {
        try {
            $logProps = Config::$logProps;

            if(!empty($logPropsOverride)){
                foreach ($logPropsOverride as $key => $value){
                    $logProps[$key] = $value;
                }
            }

            $dateTime = new DateTime();

            $logText = $dateTime->format('Y-m-d H:i:s')." - Line: ".$e->getLine().' - Error: '.$e->getMessage();

            if($logProps['logFile']){
                $logText = PHP_EOL.$logText;
                fwrite($this->_file, $logText);
            }

            if($logProps['logConsole']){
                echo PHP_EOL,$e->getMessage();
            }

            if($logProps['logDB']){
                $this->_DB->insertLog('attachments', 'error', $e->getMessage());
            }

            if($logProps['logMail']){
                // $this->_mail->Subject = 'Error'; // Decomment for custom error
                $this->_mail->Body = $e->getMessage();
                $this->_mail->AltBody = $e->getMessage();
                $this->_mail->send();
            }

        }catch (Exception $e){
            throw $e;
        }
    }
}
