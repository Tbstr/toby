<?php

namespace Toby\Assets;

use Toby\ThemeManager;

class AssetsSet
{
    /* PUBLIC VARIABLES */
    public $type                        = null;
    
    public $layout                      = null;
    public $resolvePath                 = null;
    public $resolvePathStrict           = null;

    /* PRIVATE VARIABLES */
    private $javascripts                = [];
    private $stylesheets                = [];

    /* CONSTANTS */
    const TYPE_DEFAULT                  = 'default';
    const TYPE_LAYOUT                   = 'layout';
    const TYPE_RESOLVE_PATH             = 'resolve_path';
    const TYPE_RESOLVE_PATH_UNSET       = 'resolve_path_unset';

    function __construct($type)
    {
        $this->type = $type;
    }

    /**
     * @param      $path
     * @param bool $async
     *
     * @return AssetsSet
     */
    public function addJavaScript($path, $async = false)
    {
        // add
        $this->javascripts[] = array($path, $async === true);

        // return self
        return $this;
    }

    /**
     * @param        $path
     * @param string $media
     *
     * @return AssetsSet
     */
    public function addCSS($path, $media = 'all')
    {
        // add
        $this->stylesheets[] = array($path, $media);

        // return self
        return $this;
    }

    /* PLACEMENT */

    /**
     * @return array
     */
    public function buildDOMElementsCSS()
    {
        // vars
        $elements       = array();
        $versionQuery   = empty(Assets::getCacheBuster()) ? '' : '?v='.Assets::getCacheBuster();

        // build
        foreach($this->stylesheets as $css)
        {
            if(preg_match('/^(https?:\/\/|\/)/', $css[0]))
            {
                $elements[] = '<link rel="stylesheet" type="text/css" media="'.$css[1].'" href="'.$css[0].$versionQuery.'" />'.NL;
            }
            else
            {
                $elements[] = '<link rel="stylesheet" type="text/css" media="'.$css[1].'" href="'.ThemeManager::$themeURL.'/'.$css[0].$versionQuery.'" />'.NL;
            }
        }

        // return
        return $elements;
    }

    /**
     * @return array
     */
    public function buildDOMElementsJavaScript()
    {
        // vars
        $elements     = [];
        $versionQuery = empty(Assets::getCacheBuster()) ? '' : '?v='.Assets::getCacheBuster();

        // build
        foreach($this->javascripts as $js)
        {
            if(preg_match('/^(https?:\/\/|\/)/', $js[0]))
            {
                $elements[] = '<script type="text/javascript" src="'.$js[0].$versionQuery.'" '.($js[1] ? 'async' : '').'></script>'.NL;
            }
            else
            {
                $elements[] = '<script type="text/javascript" src="'.ThemeManager::$themeURL.'/'.$js[0].$versionQuery.'" '.($js[1] ? 'async' : '').'></script>'.NL;
            }
        }

        // return
        return $elements;
    }
}
