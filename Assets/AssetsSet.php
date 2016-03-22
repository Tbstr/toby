<?php

namespace Toby\Assets;

use Toby\ThemeManager;

class AssetsSet
{
    /* PUBLIC VARIABLES */
    public $type                        = 'standard';

    public $resolvePath                 = false;
    public $resolvePathStrict           = false;

    /* PRIVATE VARIABLES */
    private $javascripts                = array();
    private $stylesheets                = array();

    /* CONSTANTS */
    const TYPE_STANDARD                 = 'standard';
    const TYPE_RESOLVE_PATH             = 'resolve_path';
    const TYPE_RESOLVE_PATH_DEFAULT     = 'resolve_path_default';

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
        $versionQuery   = Assets::$cacheBuster === false ? '' : '?v='.Assets::$cacheBuster;

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
        $elements       = array();
        $versionQuery   = Assets::$cacheBuster === false ? '' : '?v='.Assets::$cacheBuster;

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