<?php

namespace Toby\HTTP;

class Response
{
    protected $headers;
    
    /** @var string */
    protected $content;
    
    /** @var int */
    protected $statusCode;

    function __construct($content = '', $statusCode = StatusCodes::HTTP_OK, array $headers = null)
    {
        $this->content    = $content;
        $this->statusCode = $statusCode;
        $this->headers    = empty($headers) ? [] : $headers;
    }

    /**
     * @param string $header
     * 
     * * @return $this
     */
    public function addHeader($header)
    {
        $this->headers[] = $header;
        
        return $this;
    }

    /**
     * @param string $content
     * 
     * * @return $this
     */
    public function setContent($content)
    {
        $this->content = $content;

        return $this;
    }

    /**
     * @param int $statusCode
     *
     * @return $this
     */
    public function setStatusCode($statusCode)
    {
        $this->statusCode = $statusCode;

        return $this;
    }
    
    /* send */
    public function send()
    {
        // set status code
        http_response_code($this->statusCode);

        // send headers
        $this->sendHeaders();

        // send content
        $this->sendContent();
    }
    
    public function sendHeaders()
    {
        // cancellation
        if(headers_sent()) return $this;
        
        // send
        header(sprintf('HTTP/1.1 %s %s', $this->statusCode, StatusCodes::toText($this->statusCode)), true, $this->statusCode);
        foreach($this->headers as $header) { header($header, false, $this->statusCode); }
        
        // return self
        return $this;
    }
    
    public function sendContent()
    {
        echo $this->content;
        
        return $this;
    }
}
