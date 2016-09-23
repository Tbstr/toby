<?php

namespace Toby;

use Toby\Assets\AssetsSet;

class Layout extends View
{
    protected $title            = '';
    
    protected $jsVars           = null;
    protected $assetSets        = null;

    protected $bodyId           = null;
    protected $bodyClasses      = [];

    protected $content          = '';

    /**
     * Layout constructor.
     *
     * @param string      $scriptPath
     * @param array       $vars
     * @param AssetsSet[] $assetSets
     */
    function __construct($scriptPath, array $vars = null, array $assetSets = null)
    {
        // vars
        $this->assetSets = $assetSets;
        
        // call parent
        parent::__construct($scriptPath, $vars);
    }

    /* getter & setter */
    public function setTitle($title)
    {
        $this->title = (string)$title;
    }

    public function setJavaScriptVars($vars)
    {
        $this->jsVars = empty($vars) ? null : $vars;
    }
    
    public function setContent($content)
    {
        $this->content = (string)$content;
    }
    
    public function setBodyId($id)
    {
        $this->bodyId = empty($id) ? null : (string)$id;
    }

    public function addBodyClass($classes)
    {
        if(!is_array($classes)) $classes = [(string)$classes];
        
        foreach($classes as $class)
        {
            $this->bodyClasses[] = (string)$class;
        }
    }

    public function removeBodyClass($classes)
    {
        if(!is_array($classes)) $classes = [(string)$classes];
        
        foreach($classes as $class)
        {
            if(($key = array_search($class, $this->bodyClasses)) !== false)
            {
                array_splice($this->bodyClasses, $key, 1);
            }
        }
    }
    
    /* placements */
    protected function placeScripts()
    {
        if($this->assetSets === null) return;
        
        foreach($this->assetSets as $set)
        {
            echo implode("\n", $set->buildDOMElementsJavaScript())."\n";
        }
    }
    
    protected function placeStyles()
    {
        if($this->assetSets === null) return;
        
        foreach($this->assetSets as $set)
        {
            echo implode("\n", $set->buildDOMElementsCSS())."\n";
        }
    }

    protected function placeMeta()
    {
        if($this->assetSets === null) return;

        foreach($this->assetSets as $set)
        {
            echo implode("\n", $set->buildDOMElementsMeta())."\n";
        }
    }

    protected function placeJSVars()
    {
        if(!empty($this->jsVars)) echo /** @lang text */'<script type="text/javascript">window.TobyVars='.json_encode($this->jsVars).';</script>';
    }
    
    protected function placeBodyAttributes()
    {
        // vars
        $out = [];

        // id
        if(!empty($this->bodyId))
        {
            $out[] = 'id="'.$this->bodyId.'"';
        }
        
        // class
        if(!empty($this->bodyClasses))
        {
            // add
            $out[] = 'class="'.implode(' ', $this->bodyClasses).'"';
        }
        
        echo implode(' ', $out);
    }
    
    /* to string */
    public function __toString()
    {
        return "Layout[$this->title]";
    }
}
