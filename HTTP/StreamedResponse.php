<?php

namespace Toby\HTTP;

use Toby\Exceptions\TobyException;

class StreamedResponse extends Response 
{
    protected $callback;
    
    protected $callbackCalled;
    
    function __construct(callable $callback = null, $statusCode = StatusCodes::HTTP_OK, array $headers = null)
    {
        parent::__construct(null, $statusCode, $headers);
        
        if($callback !== null) $this->setCallback($callback);
        $this->callbackCalled = false;
    }
    
    public function setCallback(callable $callback)
    {
        $this->callback = $callback;
        return $this;
    }

    public function setContent($content)
    {
        throw new TobyException('content can not be set on a StreamedResponse, use StreamedResponse::setCallback()');
    }
    
    public function sendContent()
    {
        if($this->callbackCalled) return;
        $this->callbackCalled = true;
        
        if(is_callable($this->callback))
        {
            call_user_func($this->callback);
        }
    }
}
