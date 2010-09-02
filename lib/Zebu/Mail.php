<?php


class Zebu_Mail
{   
    protected $sender = array();
    protected $recipients = array();
    protected $subject;
    protected $bodyText;
    protected $bodyHtml;
    protected $attachments = array();
    
    //lazy constructor
    public function __contstruct()
    {
        //this space intentionally left blank
    }
    
    //heavy send
    public function send()
    {
        global $conf;
    
        $zendMail = new Zend_Mail('UTF-8');
        $zendMail->setHeaderEncoding(Zend_Mime::ENCODING_BASE64);
        
        if(empty($this->bodyText) && empty($this->bodyHtml)){
            throw new Exception('mail must contain a body (either text or html)');
        }
        if(!empty($this->bodyText)) {
            $zendMail->setBodyText($this->bodyText);
        }
        if(!empty($this->bodyHtml)) {
            $zendMail->setBodyHtml($this->bodyHtml);
        }
        
        if(!isset($this->sender['email']) || empty($this->sender['email'])){
            throw new Exception('mail must contain a sender');
        }
        $zendMail->setFrom($this->sender['email'], $this->sender['name']);
        
        if( empty($this->recipients) ){
            throw new Exception('mail must contain at least one recipient');
        }
        if( !empty($conf->mail->force_to)) {
            $zendMail->addTo($conf->mail->force_to);    
        } else {
            foreach($this->recipients as $recipient) {
                if( !isset($recipient['email']) ) {
                    throw new Exception('each recipeint must have an email address');
                }
                $zendMail->addTo($recipient['email'], $recipient['name']);
            }
        }
        
        if( empty($this->subject) ){
            throw new Exception('mail must contain a subject');
        }
        $zendMail->setSubject($this->subject);
        
        foreach($this->attachments as $attachment) {
            $att = $zendMail->createAttachment($attachment->getContent());
            $att->filename = $attachment->filename;
        }
        
        $zendMail->send();
    }
    
    
    public function setBodyText($text)
    {
        $this->bodyText = $text;
        
        return $this;
    }
    
    public function setBodyHtml($html)
    {
        $this->bodyHtml = $html;
        
        return $this;
    }
    
    public function setFrom($senderEmail, $senderName = '')
    {
        $this->sender['email'] = $senderEmail;
        $this->sender['name']  = $senderName;
        
        return $this;
    }
    
    public function addTo($recipientEmail, $recipientName = '')
    {
        $recipient['email'] = $recipientEmail;
        $recipient['name']  = $recipientName;
        array_push($this->recipients,$recipient);
        
        return $this;
    }
    
    public function setSubject($subject)
    {
        $this->subject = $subject;
        
        return $this;
    }
    
    public function addAttachment($attachment)
    {
        if (!($attachment instanceof Zend_Mime_Part)) {
            $filePath = realpath($attachment);
            $file = file_get_contents($filePath);
            $attachment = new Zend_Mime_Part($file);
            $attachment->filename = basename($filePath);
        }

        array_push($this->attachments,$attachment);
        
        return $this;
    }
    
    public static function createInstance()
    {
        return new Zebu_Mail();
    }

}