<?php

class Core_Html_Tag
{
    private $name           = null;
    private $content        = false;
    private $attributes     = array();
    
    function __construct($name)
    {
        $this->name = $name;
    }
    
    public function addAttribute($name, $value)
    {
        $this->attributes[$name] = $value;
        return $this;
    }
    
    public function addAttributes($attributes)
    {
        if(!is_array($attributes)) return $this;
        
        foreach($attributes as $attrName => $attrValue) $this->addAttribute($attrName, $attrValue);
        return $this;
    }
    
    public function setContent($content)
    {
        $this->content = $content;
        return $this;
    }
    
    public function appendToContent($content)
    {
        if(is_string($this->content)) $this->content .= $content;
        return $this;
    }
    
    public function build()
    {
        // tag name
        $html = "<$this->name";
        
        // attributes
        foreach($this->attributes as $attrName => $attrValue) $html .= " $attrName=\"$attrValue\"";
        
        // tag close
        if($this->content === false) $html .= '/>';
        else $html .= ">$this->content</$this->name>";
        
        // return
        return $html;
    }
    
    /* static creator */
    public static function render($name, $attributes = false, $content = false)
    {
        // tag
        $tag = new Core_Html_Tag($name);
        
        // attributes
        if(is_array($attributes)) $tag->addAttributes($attributes);
        
        // content
        if($content !== false) $tag->setContent($content);
        
        // return
        return $tag->build();
    }
}