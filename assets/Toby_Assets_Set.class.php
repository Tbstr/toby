<?php

class Toby_Assets_Set
{
    /* public variables */
    public $type                        = 'standard';
    public $resolvePath                 = false;

    /* private variables */
    private $javascripts                = array();
    private $stylesheets                = array();

    /* constants */
    const TYPE_STANDARD                 = 'standard';
    const TYPE_RESOLVE_PATH             = 'resolve_path';
    const TYPE_RESOLVE_PATH_DEFAULT     = 'resolve_path_default';

    /* getter setter */
    public function addJavaScript($path, $async = false)
    {
        // add
        $this->javascripts[] = array($path, $async === true);

        // return self
        return $this;
    }

    public function addCSS($path, $media = 'all')
    {
        // add
        $this->stylesheets[] = array($path, $media);

        // return self
        return $this;
    }

    /* placement */
    public function buildDOMElementsCSS()
    {
        // vars
        $elements       = array();
        $versionQuery   = Toby_Assets::$cacheBuster === false ? '' : '?v='.Toby_Assets::$cacheBuster;

        // build
        foreach($this->stylesheets as $css)
        {
            if(preg_match('/^(https?:\/\/|\/)/', $css[0]))
            {
                $elements[] = '<link rel="stylesheet" type="text/css" media="'.$css[1].'" href="'.$css[0].$versionQuery.'" />'.NL;
            }
            else
            {
                $elements[] = '<link rel="stylesheet" type="text/css" media="'.$css[1].'" href="'.Toby_ThemeManager::$themeURL.'/'.$css[0].$versionQuery.'" />'.NL;
            }
        }

        // return
        return $elements;
    }

    public function buildDOMElementsJavaScript()
    {
        // vars
        $elements       = array();
        $versionQuery   = Toby_Assets::$cacheBuster === false ? '' : '?v='.Toby_Assets::$cacheBuster;

        // build
        foreach($this->javascripts as $js)
        {
            if(preg_match('/^(https?:\/\/|\/)/', $js[0]))
            {
                $elements[] = '<script type="text/javascript" src="'.$js[0].$versionQuery.'" '.($js[1] ? 'async' : '').'></script>'.NL;
            }
            else
            {
                $elements[] = '<script type="text/javascript" src="'.Toby_ThemeManager::$themeURL.'/'.$js[0].$versionQuery.'" '.($js[1] ? 'async' : '').'></script>'.NL;
            }
        }

        // return
        return $elements;
    }
}