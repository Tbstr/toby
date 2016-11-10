<?php

namespace Toby\HTTP;

class RedirectResponse extends Response
{
    protected $url;
    
    function __construct($url = null, $statusCode = StatusCodes::HTTP_FOUND, array $headers = null)
    {
        $this->url = $url;
        
        parent::__construct('', $statusCode, $headers);
    }
    
    public function setURL($url)
    {
        $this->url = $url;
        return $this;
    }
    
    public function sendHeaders()
    {
        $this->addHeader('Location: '.$this->url);
        return parent::sendHeaders();
    }
}
