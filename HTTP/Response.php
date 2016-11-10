<?php

namespace Toby\HTTP;

class Response
{
    /** @var string */
    protected $protocolVersion;
    
    /** @var array */
    protected $headers;
    
    /** @var string */
    protected $content;
    
    /** @var int */
    protected $statusCode;

    function __construct($content = '', $statusCode = StatusCodes::HTTP_OK, array $headers = null)
    {
        // vars
        $this->content    = $content;
        $this->statusCode = $statusCode;
        $this->headers    = empty($headers) ? [] : $headers;
        
        // set protocol
        if(isset($_SERVER['SERVER_PROTOCOL']))
        {
            if($_SERVER['SERVER_PROTOCOL'] === 'HTTP/1.0')
            {
                $this->protocolVersion = '1.0';
            }
            else
            {
                $this->protocolVersion = '1.1';
            }
        }
        else
        {
            $this->protocolVersion = '1.1';
        }
    }

    /**
     * @param string $header
     * 
     * * @return $this
     */
    public function addHeader($header, $replace = true)
    {
        $this->headers[] = ['header' => $header, 'replace' => $replace];
        
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
        // send headers
        $this->sendHeaders();

        // send content
        $this->sendContent();
    }
    
    protected function sendHeaders()
    {
        // cancellation
        if(headers_sent()) return $this;
        
        // defined headers
        foreach($this->headers as $header)
        {
            header($header['header'], $header['replace'], $this->statusCode);
        }
        
        // status
        header(sprintf('HTTP/%s %s %s', $this->protocolVersion, $this->statusCode, StatusCodes::toText($this->statusCode)), true, $this->statusCode);
        
        // return self
        return $this;
    }

    protected function sendContent()
    {
        // print content
        echo $this->content;
        
        // return self
        return $this;
    }
}
