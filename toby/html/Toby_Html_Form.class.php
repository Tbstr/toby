<?php

class Toby_Html_Form
{
    private $name;
    private $action;
    private $method; 
    private $attr;
    
    private $elements                       = array();
    
    private $omitFormTag                    = false;
    
    public static $METHOD_GET               = 'GET';
    public static $METHOD_POST              = 'POST';
    
    public static $ELEMENT_INPUT_TEXT       = 'inputText';
    public static $ELEMENT_INPUT_PASSWORD   = 'inputPassword';
    public static $ELEMENT_INPUT_HIDDEN     = 'inputHidden';
    public static $ELEMENT_TEXTAREA         = 'textarea';
    
    function __construct($name, $action, $method = 'POST', $attr = null)
    {
        $this->name     = $name;
        $this->action   = $action;
        $this->method   = $method;
        $this->attr     = $attr;
    }
    
    public function addTextInput($name, $value = '', $attr = null)
    {
        $this->elements[] = array(
            'type'          =>  self::$ELEMENT_INPUT_TEXT,
            'name'          => $name,
            'value'         => $value,
            'attr'          => $attr
        );

        // return
        return $this;
    }
    
    public function addPasswordInput($name, $value = '', $attr = null)
    {
        $this->elements[] = array(
            'type'          =>  self::$ELEMENT_INPUT_PASSWORD,
            'name'          => $name,
            'value'         => $value,
            'attr'          => $attr
        );

        // return
        return $this;
    }
    
    public function addHiddenInput($name, $value = '', $attr = null)
    {
        $this->elements[] = array(
            'type'          =>  self::$ELEMENT_INPUT_HIDDEN,
            'name'          => $name,
            'value'         => $value,
            'attr'          => $attr
        );

        // return
        return $this;
    }
    
    public function addTextArea($name, $value = '',  $attr = null)
    {
        $this->elements[] = array(
            'type'          =>  self::$ELEMENT_TEXTAREA,
            'name'          => $name,
            'value'         => $value,
            'attr'          => $attr
        );

        // return
        return $this;
    }
    
    public function build()
    {
        // elements
        $elements = '';
        foreach($this->elements as $elm)
        {
            switch($elm['type'])
            {
                case self::$ELEMENT_INPUT_TEXT:
                    
                    $tag = new Toby_Html_Tag('input');
                    
                    $tag->addAttributes(isset($elm['attr']) ? $elm['attr'] : null);
                    
                    $tag->addAttribute('type', 'text')
                        ->addAttribute('name', "$this->name[{$elm['name']}]")
                        ->addAttribute('value', isset($_POST[$this->name][$elm['name']]) ? $_POST[$this->name][$elm['name']] : $elm['value']);
                    
                    $elements .= $tag->build().NL;
                    
                    break;
                
                case self::$ELEMENT_INPUT_PASSWORD:
                    
                    $tag = new Toby_Html_Tag('input');
                    
                    $tag->addAttributes(isset($elm['attr']) ? $elm['attr'] : null);
                    
                    $tag->addAttribute('type', 'password')
                        ->addAttribute('name', "$this->name[{$elm['name']}]")
                        ->addAttribute('value', isset($_POST[$this->name][$elm['name']]) ? $_POST[$this->name][$elm['name']] : $elm['value']);
                    
                    $elements .= $tag->build().NL;
                    
                    break;
                
                case self::$ELEMENT_INPUT_HIDDEN:
                    
                    $tag = new Toby_Html_Tag('input');
                    
                    $tag->addAttributes(isset($elm['attr']) ? $elm['attr'] : null);
                    
                    $tag->addAttribute('type', 'hidden')
                        ->addAttribute('name', "$this->name[{$elm['name']}]")
                        ->addAttribute('value', isset($_POST[$this->name][$elm['name']]) ? $_POST[$this->name][$elm['name']] : $elm['value']);
                    
                    $elements .= $tag->build().NL;
                    
                    break;
                
                case self::$ELEMENT_TEXTAREA:
                    
                    $tag = new Toby_Html_Tag('textarea');
                    
                    $tag->addAttributes(isset($elm['attr']) ? $elm['attr'] : null);
                    
                    $tag->addAttribute('name', "$this->name[{$elm['name']}]")
                        ->setContent(isset($_POST[$this->name][$elm['name']]) ? $_POST[$this->name][$elm['name']] : $elm['value']);
                    
                    $elements .= $tag->build().NL;
                    
                    break;
            }
        }
        
        // form
        $formTag = null;
        if(!$this->omitFormTag)
        {
            $formTag = new Toby_Html_Tag('form');
            $formTag->addAttributes($this->attr)
                    ->addAttribute('name', $this->name)
                    ->addAttribute('action', $this->action)
                    ->addAttribute('method', $this->method)
                    ->setContent($elements);
        }
        
        // return
        if($formTag === null) return $elements;
        else return $formTag->build();
    }
}