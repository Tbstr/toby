<?php

namespace Toby\Utils\HTML;

use Toby\Security;

class HTMLForm
{
    /* variables */
    private $name;
    private $action;
    private $method; 
    private $attr;
    
    private $elements                       = array();
    
    private $omitFormTag                    = false;
    
    /* constants */
    const METHOD_GET                        = 'GET';
    const METHOD_POST                       = 'POST';
    
    const ELEMENT_INPUT_TEXT                = 'inputText';
    const ELEMENT_INPUT_PASSWORD            = 'inputPassword';
    const ELEMENT_INPUT_HIDDEN              = 'inputHidden';
    const ELEMENT_TEXTAREA                  = 'textarea';
    const ELEMENT_CHECKBOX                  = 'checkbox';
    const ELEMENT_CUSTOM                    = 'custom';
    
    function __construct($name, $action, $method = 'POST', $attr = null)
    {
        // set vars
        $this->name     = $name;
        $this->action   = $action;
        $this->method   = $method;
        $this->attr     = $attr;
    }
    
    public function addTextInput($name, $value = '', $attr = null)
    {
        $this->elements[] = array(
            'type'          =>  self::ELEMENT_INPUT_TEXT,
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
            'type'          =>  self::ELEMENT_INPUT_PASSWORD,
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
            'type'          =>  self::ELEMENT_INPUT_HIDDEN,
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
            'type'          =>  self::ELEMENT_TEXTAREA,
            'name'          => $name,
            'value'         => $value,
            'attr'          => $attr
        );

        // return
        return $this;
    }
    
    public function addCheckBox($name, $checked = false, $attr = null)
    {
        $this->elements[] = array(
            'type'          =>  self::ELEMENT_CHECKBOX,
            'name'          => $name,
            'checked'       => $checked,
            'attr'          => $attr
        );

        // return
        return $this;
    }
    
    public function addCustomElement($tagName, $content = null, $attr = null)
    {
        $this->elements[] = array(
            'type'          =>  self::ELEMENT_CUSTOM,
            'tagName'       => $tagName,
            'content'       => $content,
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
                case self::ELEMENT_INPUT_TEXT:
                    
                    $tag = new HTMLTag('input');
                    
                    $tag->addAttribute('type', 'text')
                        ->addAttribute('name', "$this->name[{$elm['name']}]")
                        ->addAttribute('value', isset($_POST[$this->name][$elm['name']]) ? $_POST[$this->name][$elm['name']] : $elm['value']);
                    
                    if(isset($elm['attr'])) $tag->addAttributes($elm['attr']);
                        
                    $elements .= $tag->build().NL;
                    
                    break;
                
                case self::ELEMENT_INPUT_PASSWORD:
                    
                    $tag = new HTMLTag('input');
                    
                    $tag->addAttribute('type', 'password')
                        ->addAttribute('name', "$this->name[{$elm['name']}]")
                        ->addAttribute('value', isset($_POST[$this->name][$elm['name']]) ? $_POST[$this->name][$elm['name']] : $elm['value']);
                    
                    if(isset($elm['attr'])) $tag->addAttributes($elm['attr']);
                        
                    $elements .= $tag->build().NL;
                    
                    break;
                
                case self::ELEMENT_INPUT_HIDDEN:
                    
                    $tag = new HTMLTag('input');
                    
                    $tag->addAttribute('type', 'hidden')
                        ->addAttribute('name', "$this->name[{$elm['name']}]")
                        ->addAttribute('value', isset($_POST[$this->name][$elm['name']]) ? $_POST[$this->name][$elm['name']] : $elm['value']);
                        
                    if(isset($elm['attr'])) $tag->addAttributes($elm['attr']);
                    
                    $elements .= $tag->build().NL;
                    
                    break;
                
                case self::ELEMENT_TEXTAREA:
                    
                    $tag = new HTMLTag('textarea');
                    
                    $tag->addAttribute('name', "$this->name[{$elm['name']}]")
                        ->setContent(isset($_POST[$this->name][$elm['name']]) ? $_POST[$this->name][$elm['name']] : $elm['value']);
                    
                    if(isset($elm['attr'])) $tag->addAttributes($elm['attr']);
                    
                    $elements .= $tag->build().NL;
                    
                    break;
                
                case self::ELEMENT_CHECKBOX:
                    
                    $tag = new HTMLTag('input');
                    
                    $tag->addAttribute('type', 'checkbox')
                        ->addAttribute('name', "$this->name[{$elm['name']}]");
                    
                    if(isset($_POST[$this->name][$elm['name']]) || $elm['checked'] === true) $tag->addAttribute('checked', 'checked');
                    if(isset($elm['attr'])) $tag->addAttributes($elm['attr']);
                    
                    $elements .= $tag->build().NL;
                    
                    break;
                    
                case self::ELEMENT_CUSTOM:
                    
                    $tag = new HTMLTag($elm['tagName']);
                    
                    if(isset($elm['attr'])) $tag->addAttributes($elm['attr']);
                    if(isset($elm['content'])) $tag->setContent($elm['content']);
                    
                    $elements .= $tag->build().NL;
                    
                    break;
            }
        }
        
        // add XSRF input
        $elements .= Security::XSRFFormElement();
        
        // form
        $formTag = null;
        if(!$this->omitFormTag)
        {
            $formTag = new HTMLTag('form');
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
    
    /* to string */
    public function __toString()
    {
        return "HTMLForm[$this->name]";
    }
}
