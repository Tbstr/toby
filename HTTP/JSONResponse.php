<?php

namespace Toby\HTTP;

use Toby\Exceptions\TobyException;

class JSONResponse extends Response
{
    private $data           = [];
    private $dataEncoded    = null;
    
    function __construct(array $data = null, $statusCode = 200, array $headers = null)
    {
        parent::__construct('', $statusCode, $headers);
        
        if($data !== null) $this->set($data);
        $this->addHeader('Content-type: application/json');
    }

    public function set(array $data)
    {
        if($data === null) return $this;
        
        $this->data = $data;
        return $this;
    }

    public function add($key, $value)
    {
        $this->data[$key] =  $value;
        return $this;
    }
    
    public function setContent($content)
    {
        throw new TobyException('content can not be set on a JSONResponse, use JSONResponse::set()');
    }
    
    /* sending */
    public function sendHeaders()
    {
        $this->dataEncoded = json_encode($this->data);
        $this->addHeader('Content-Length: '.mb_strlen($this->dataEncoded));
        
        return parent::sendHeaders();
    }

    public function sendContent()
    {
        if($this->dataEncoded === null)
        {
            $this->dataEncoded = json_encode($this->data);
        }
        
        echo $this->dataEncoded;
        return $this;
    }
}
