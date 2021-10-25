<?php
    $MAIN_PATH = (PHP_OS === 'Linux' || PHP_OS === 'Darwin') ? str_replace('/classes', '', __DIR__) . '/vendor/autoload.php' : str_replace('\classes', '', __DIR__) . '\vendor\autoload.php';
    require $MAIN_PATH;

    use Mailgun\Mailgun;

    use PHPMailer\PHPMailer\PHPMailer;
    use PHPMailer\PHPMailer\SMTP;
    use PHPMailer\PHPMailer\Exception;

    //  Mailgun  
    class Email {

        // Mailgun
        private string $KEY;
        private string $FROM;
        private string $HOST;
        private string $DOMAIN;

        //  SMTP
        private string $SMTP_HOST;
        private string $SMTP_USERNAME;
        private string $SMTP_PASSWORD;
        private string $SMTP_PORT;

        /**
         * Mailgun init
         */
        public function Mailgun_init() 
        {
            $PATH = str_replace('\classes', '',__DIR__);
            
            //  Get values from init file
                $CONFIG = parse_ini_file($PATH.'\php.ini');

            //  Assign the value
                $this->KEY = $CONFIG['MG_KEY'];
                $this->FROM = $CONFIG['MG_FROM'];
                $this->HOST = $CONFIG['MG_HOST'];
                $this->DOMAIN = $CONFIG['MG_DOMAIN'];
        }

        /**
         * Send email
         * @param string $recipient_email
         * @param string $subject
         * @param string $text
         * @param string $html_body
         * @return array
         */
        public function SendEmail(
            string $recipient_email,
            string $subject,
            string $text,
            string $html_body
        ): array
        {
            try {
                $mg = Mailgun::create($this->KEY, $this->HOST);
                $mg_param = [
                    'from' => $this->FROM,
                    'to' => $recipient_email,
                    'subject' => $subject,
                    'text' => $text,
                    'html' => $html_body,
                    'o:tracking' => 'true'
                ];
                $mg_result = $mg->message()->send($this->DOMAIN, $mg_param);
                return ['status'=>TRUE, 'result'=>$mg_result];
            } catch (Exception $e) {
                return ['status'=>FALSE, 'msg'=>$e->getMessage()];
            }
        }

        /**
         * Email validation
         * @param string $email_address         Email address
         */
        public function EmailValidation(
            string $email_address
        ): array
        {
            try {
                
                $params = ["address" => $email_address];
                $ch = curl_init();

                $API_KEY = $this->KEY;
            
                curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
                curl_setopt($ch, CURLOPT_USERPWD, "api:$API_KEY");
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
                curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
                curl_setopt($ch, CURLOPT_URL, 'https://api.mailgun.net/v4/address/validate');
                $result = curl_exec($ch);
                curl_close($ch);
            
                return ['status'=>TRUE, 'result'=>$result];
                
            } catch (Exception $e) {
                return ['status'=>FALSE, 'msg'=>$e->getMessage()];
            }
        }

        /**
         * Initialize SMTP 
         * @param string $hostname,
         * @param string $username,
         * @param string $password,
         * @param string $port
         */
        public function SMTP_init() 
        {
            $PATH = str_replace('\classes', '',__DIR__);
            $CONFIG = parse_ini_file($PATH.'\php.ini');
            $company_id = $CONFIG['COMPANY_ID'];

            $DB = new DB();
            $tokens = $DB->select('smtp_token', ['host', 'username', 'password', 'port'], ['company_id'=>$company_id]);
            $this->SMTP_HOST = $tokens['host'];
            $this->SMTP_USERNAME = $tokens['username'];
            $this->SMTP_PASSWORD = $tokens['password'];
            $this->SMTP_PORT = $tokens['port'];
        }

        /**
         * Smtp send email
         * @param array $params     
         * requrie params [
         *      from_email => 'info@crm.702pros.com',
		 *      from_name => 'PulsnestCRM',
		 *      recipients =>[
		 *          ['email'=>'ryan@702pros.com', 'name'=>'ryan'],
		 *      ],
		 *      reply_email => 'info@crm.702pros.com',
		 *      reply_name => 'PulsenestCRM',
		 *      subject => 'Zoom meetng with ryan',
		 *      body => $email_template['html'],
		 *      body_text=> $email_template['text']
         * ]
         * @return array status of the result
         */
        public function SMTP_sendEmail(
            array $params
        ): array
        {
            //  
                $from_email =   $params['from_email'];
                $from_name =    $params['from_name'];
                $recipients =   $params['recipients'];
                $reply_email =  (isset($params['reply_email'])) ? $params['reply_email'] : NULL;
                $reply_name =   (isset($params['reply_name'])) ? $params['reply_name'] : NULL;
                $cc =           (isset($params['cc'])) ? $params['cc'] : NULL;
                $bcc =          (isset($params['bcc'])) ? $params['bcc'] : NULL;
                $attachments =  (isset($params['attachments'])) ? $params['attachments'] : NULL;
                $subject =      $params['subject'];
                $body =         $params['body'];
                $body_text =    $params['body_text'];

            //
                $mail = new PHPMailer(true);

            //  recipients must not be empty and must be array list
                if(!isset($recipients) && empty($recipients)){
                    return ['status'=>FALSE, 'msg'=>'Recipients must be not be null'];
                }
                if(!is_array($recipients)){
                    return ['status'=>FALSE, 'msg'=>'Recipients must be array'];
                }

            //  Send email
                try {

                    //  Send email
                        $mail->SMTPDebug = SMTP::DEBUG_SERVER;                      //Enable verbose debug output
                        $mail->isSMTP();     
                        $mail->SMTPDebug  = 0;
                        $mail->Host       = $this->SMTP_HOST;                     //Set the SMTP server to send through
                        $mail->SMTPAuth   = true;                                   //Enable SMTP authentication
                        $mail->Username   = $this->SMTP_USERNAME;                     //SMTP username
                        $mail->Password   = $this->SMTP_PASSWORD;                               //SMTP password
                        $mail->SMTPSecure = 'ssl';                  //Enable TLS encryption; `PHPMailer::ENCRYPTION_SMTPS` encouraged
                        $mail->Port       = $this->SMTP_PORT;           
                    
                    //  Recipients
                        $mail->setFrom($from_email, $from_name);

                    //  Reply email
                        if(isset($reply_email) && !empty($reply_email)){
                            $mail->addReplyTo($reply_email, $reply_name);
                        }
                        
                    //  CC && BCC
                        if(isset($cc) && !empty($cc)){
                            $mail->addCC($cc);
                        }
                        if(isset($bcc) && !empty($bcc)){
                            $mail->addBCC($bcc);
                        }

                    //  Recipients
                        foreach ($recipients as $value) {
                            $email = $value['email'];
                            $name = (isset($value['name'])) ? $value['name'] : '';
                            $mail->addAddress($email, $name);
                        }

                    //  Attachments
                        if(!empty($attachments) && isset($attachments)){
                            if(!is_array($attachments)){
                                return ['status'=>FALSE, 'msg'=>'Attachments must be array'];
                                exit;
                            }
                            foreach ($attachments as $value) {
                                $file_path = $value['file_path'];
                                $file_name = $value['filen_name'];
                                $mail->addAttachment($file_path, $file_name);
                            }
                        }

                    //  Content
                        $mail->isHTML(true);
                        $mail->Subject = $subject;
                        $mail->Body    = $body;
                        $mail->AltBody = $body_text;

                    //  Sent
                        $sent = $mail->send();
                        return (!$sent) ? ['status'=>FALSE, 'msg'=>$mail->ErrorInfo] : ['status'=>TRUE];

                } catch (Exception $e) {
                    return ['status'=>FALSE, 'msg'=>"Message could not be sent. Mailer Error: {$mail->ErrorInfo}"];
                }
        }

    }
?>