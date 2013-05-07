<?php

require_once 'Mail.php';

class Core_Mailer
{
    /**
     * @var string
     */
    static private $defaultSender;

    /**
     * @var string
     */
    private $sender;

    /**
     * @var string
     */
    private $recipient;

    /**
     * @var string
     */
    private $subject;

    /**
     * @var string
     */
    private $textMessage;

    /**
     * @var string
     */
    private $htmlMessage;

    /**
     * @var array
     */
    private $attachments = array();

    /**
     * @param string $htmlMessage
     * @return Core_Mailer
     */
    public function setHtmlMessage($htmlMessage)
    {
        $this->htmlMessage = $htmlMessage;
        return $this;
    }

    /**
     * @param string $recipient
     * @return Core_Mailer
     */
    public function setRecipient($recipient)
    {
        $this->recipient = $recipient;
        return $this;
    }

    /**
     * @param string $sender
     * @return Core_Mailer
     */
    public function setSender($sender)
    {
        $this->sender = $sender;
        return $this;
    }

    /**
     * @param string $subject
     * @return Core_Mailer
     */
    public function setSubject($subject)
    {
        $this->subject = $subject;
        return $this;
    }

    /**
     * @param string $textMessage
     * @return Core_Mailer
     */
    public function setTextMessage($textMessage)
    {
        $this->textMessage = $textMessage;
        return $this;
    }

    public function addAttachment($path, $filename)
    {
        $this->attachments[] = array(
            "path" => $path,
            "filename" => $filename
        );
        return $this;
    }

    /**
     * @return boolean
     */
    public function send()
    {
        $mail = Mail::factory("mime", (array(
            "eol" => "\n",
            "head_charset" => "utf-8",
            "text_charset" => "utf-8",
            "html_charset" => "utf-8",
        )));

        if ($this->textMessage != null)
        {
            $mail->setTXTBody($this->textMessage);
        }
        if ($this->htmlMessage != null)
        {
            $mail->setHTMLBody($this->htmlMessage);
        }
        
        foreach ($this->attachments as $attachment)
        {
            $mail->addAttachment($attachment["path"], 'application/octet-stream', $attachment["filename"], true);
        }

        $additionalHeaders = array(
            "From" => $this->sender,
            "Reply-To" => $this->sender,
            "Subject" => $this->subject,
        );

        $body = $mail->get();

        $headers = $mail->headers($additionalHeaders);

        /* @var $sender Mail_mail */
        $sender = Mail::factory("mail");
        $result = $sender->send(array($this->recipient), $headers, $body);
        if($result !== true) return false;

        return true;
    }

}
