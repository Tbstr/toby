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
    protected function placeHeaderInformation()
    {
        // js vars
        $this->placeJSVars();
        
        // theme related
        Toby_ThemeManager::placeHeaderInformation();
        
        // additional header content
        if(!empty($this->headContent)) echo $this->headContent;
    }
    
    private function placeJSVars()
    {
        // cancellation
        if(empty($this->jsVars)) return;

        // place
        echo '<script type="text/javascript">window.TobyVars = '.json_encode($this->jsVars).';</script>';
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
