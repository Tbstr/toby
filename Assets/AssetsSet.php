<?php

namespace Toby\Assets;

use Toby\ThemeManager;

class AssetsSet
{
    /* variables */
    private $js   = [];
    private $css  = [];
    private $meta = [];

    /**
     * @param string $path
     * @param bool   $async
     *
     * @return AssetsSet
     */
    public function addJavaScript($path, $async = false)
    {
        // add
        $this->js[] = [$path, $async === true];

        // return self
        return $this;
    }

    /**
     * @param string $path
     * @param string $media
     *
     * @return AssetsSet
     */
    public function addCSS($path, $media = 'all')
    {
        // add
        $this->css[] = [$path, $media];

        // return self
        return $this;
    }

    /**
     * @param string $name
     * @param string $content
     *
     * @return AssetsSet
     */
    public function addMeta($name, $content)
    {
        // add
        $this->meta[] = ['name' => $name, 'content' => $content];

        // return self
        return $this;
    }

    /**
     * @param string $httpEquiv
     * @param string $content
     *
     * @return AssetsSet
     */
    public function addMetaHTTP($httpEquiv, $content)
    {
        // add
        $this->meta[] = ['http-equiv' => $httpEquiv, 'content' => $content];

        // return self
        return $this;
    }

    /**
     * @param string $charset
     *
     * @return AssetsSet
     */
    public function addMetaCharset($charset)
    {
        // add
        $this->meta[] = ['charset' => $charset];

        // return self
        return $this;
    }

    /**
     * @param array $properties
     *
     * @return AssetsSet
     */
    public function addMetaCustom(array $properties)
    {
        // add
        $this->meta[] = $properties;

        // return self
        return $this;
    }

    /* PLACEMENT */

    /**
     * @return array
     */
    public function buildDOMElementsJavaScript()
    {
        // vars
        $elements     = [];
        $versionQuery = empty(Assets::getCacheBuster()) ? '' : '?v='.Assets::getCacheBuster();

        // build
        foreach($this->js as $js)
        {
            if(preg_match('/^(https?:\/\/|\/)/', $js[0]))
            {
                $elements[] = '<script type="text/javascript" src="'.$js[0].$versionQuery.'" '.($js[1] ? 'async' : '').'></script>'.NL;
            }
            else
            {
                $elements[] = '<script type="text/javascript" src="'.ThemeManager::$themeURL.'/'.$js[0].$versionQuery.'" '.($js[1] ? 'async' : '').'></script>';
            }
        }

        // return
        return $elements;
    }
    
    /**
     * @return array
     */
    public function buildDOMElementsCSS()
    {
        // vars
        $elements       = [];
        $versionQuery   = empty(Assets::getCacheBuster()) ? '' : '?v='.Assets::getCacheBuster();

        // build
        foreach($this->css as $css)
        {
            if(preg_match('/^(https?:\/\/|\/)/', $css[0]))
            {
                $elements[] = '<link rel="stylesheet" type="text/css" media="'.$css[1].'" href="'.$css[0].$versionQuery.'" />'.NL;
            }
            else
            {
                $elements[] = '<link rel="stylesheet" type="text/css" media="'.$css[1].'" href="'.ThemeManager::$themeURL.'/'.$css[0].$versionQuery.'" />';
            }
        }

        // return
        return $elements;
    }

    /**
     * @return array
     */
    public function buildDOMElementsMeta()
    {
        // vars
        $elements = [];

        // build
        foreach($this->meta as $meta)
        {
            $props = '';
            foreach($meta as $key => $value)
            {
                $props .= $key.'="'.$value.'" ';
            }
            
            $elements[] = '<meta '.$props.'/>';
        }

        // return
        return $elements;
    }
}
