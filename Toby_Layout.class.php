<?php

class Toby_Layout extends Toby_View
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
        
        echo "<script type=\"text/javascript\">window.TobyVars = {";
        
        $varLength = count($this->jsVars);
        $counter = 0;
        foreach($this->jsVars as $key => $var)
        {
            if(is_string($var))
            {
                if(preg_match('/(^\[.*\]$)|(^{.*}$)|(^\(.*\)$)/', $var))    echo "$key:$var";
                else                                                        echo "$key:\"$var\"";
            }
            elseif(is_numeric($var))                                        echo "$key:$var";
            elseif(is_bool($var))                                           echo "$key:".($var ? 'true' : 'false');
            elseif($var == null)                                            echo "$key:null";
            
            $counter++;
            if($counter != $varLength)                                      echo ',';
        }

        echo'};</script>';
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
