<?php

namespace Toby\Utils\HTML;

class HTMLTag
{
    private $name           = null;
    
    private $attributes     = array();
    private $classes        = array();
    
    private $content        = false;
    
    function __construct($name)
    {
        $this->name = $name;
    }
    
    /* attribute management */
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
    
    public function addClass($className)
    {
        $this->classes[] = $className;
        return $this;
    }
    
    /* content management */
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

    public function prependToContent($content)
    {
        if(is_string($this->content)) $this->content = $content.$this->content;
        return $this;
    }
    
    /* build */
    public function build()
    {
        // open tag with name
        $html = "<$this->name";
        
        // prepare classes
        if(!empty($this->classes)) $this->attributes['class'] = implode(' ', $this->classes);
        
        // write attributes
        foreach($this->attributes as $attrName => $attrValue) $html .= " $attrName=\"$attrValue\"";
        
        // close tag
        if($this->content === false) $html .= '/>';
        else $html .= ">$this->content</$this->name>";
        
        // return
        return $html;
    }
    
    /* static creator */
    public static function render($name, $attributes = false, $content = false)
    {
        // tag
        $tag = new self($name);
        
        // attributes
        if(is_array($attributes)) $tag->addAttributes($attributes);
        
        // content
        if($content !== false) $tag->setContent($content);
        
        // return
        return $tag->build();
    }
    
    /* to string */
    public function __toString()
    {
        return "HTMLTag[$this->name]";
    }
}
