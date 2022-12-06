<?php
namespace Entities;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;


class Mail extends PHPMailer {

    private static ?Mail $_instance = null;

    public function __construct()
    {
        parent::__construct(true);

        //Server settings
        $this->SMTPDebug = SMTP::DEBUG_OFF;
        $this->isSMTP();
        $this->Host       = Config::$SmtpProps['host'];
        $this->SMTPAuth   = true;
        $this->Username   = Config::$SmtpProps['username'];
        $this->Password   = Config::$SmtpProps['password'];
        $this->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $this->Port       = Config::$SmtpProps['port'];
        $this->Subject    = Config::$SmtpProps['subject'];

        //Recipients
        $this->setFrom('smtp@lektor.it', 'Lektor');
        $this->addAddress(Config::$SmtpProps['emailTo']);


        //Content
        $this->isHTML(false);

    }

    /**
     * @return Mail Return a new or last Ftp object instance created
     */
    public static function getInstance():Mail
    {
        if (self::$_instance == null) {
            self::$_instance = new Mail();
        }
        return self::$_instance;
    }
}
