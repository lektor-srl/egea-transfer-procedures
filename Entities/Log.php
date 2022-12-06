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
                mkdir(Config::$logPath, 777, true);
            }

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


    /** Provide to write a log based on the configuration. If provided, it allow to change the logic with the logPropsOverride
     * @param string $message The message to log
     * @param array $logPropsOverride logDB, logFile, LogConsole - The properties to override if needed
     * @throws Exception
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

    public function customError(string $message):void
    {
        $dateTime = new DateTime();
        $logText = $dateTime->format('Y-m-d H:i:s')." - Error: ".$message;

        fwrite($this->_file, $logText);
    }

    public function exceptionError(Exception $e):void
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
