<?php

namespace Toby;

class Layout extends View
{
    public $title               = '';
    public $jsVars              = null;
    
    public $headContent         = '';
    
    public $bodyId              = ''; 
    public $bodyClass           = '';
    
    public $content             = '';
    
    /* placements */
    protected function placeScripts()
    {
        ThemeManager::placeScripts();
    }
    
    protected function placeStyles()
    {
        ThemeManager::placeStyles();
    }

    protected function placeJSVars()
    {
        if(!empty($this->jsVars)) echo /** @lang text */'<script type="text/javascript">window.TobyVars='.json_encode($this->jsVars).';</script>';
    }

    protected function placeCustomHeadContent()
    {
        if(!empty($this->headContent)) echo $this->headContent;
    }
    
    protected function placeBodyAttributes()
    {
        // vars
        $idSet = false;

        // id
        if(!empty($this->bodyId))
        {
            echo "id=\"$this->bodyId\"";
            $idSet = true;
        }
        
        // class
        if(!empty($this->bodyClass))
        {
            // conversion
            if(is_string($this->bodyClass)) $this->bodyClass = explode(' ', $this->bodyClass);

            // add
            if($idSet) echo ' ';
            echo "class=\"".implode(' ', $this->bodyClass)."\"";
        }
    }
    
    /* to string */
    public function __toString()
    {
        return "Toby_Layout[$this->title]";
    }
}
