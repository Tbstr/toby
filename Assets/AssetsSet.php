<?php

namespace Toby\Assets;

use Toby\ThemeManager;

class AssetsSet
{
    /* variables */
    private $meta    = [];
    private $scripts = [];
    private $links   = [];

    /* core management functions */

    /**
     * @param array $props
     *
     * @return AssetsSet
     */
    public function addMeta(array $props)
    {
        // prepare
        $data = [];

        if(!empty($props))
        {
            foreach($props as $key => $value)
            {
                $data[$key] = $value;
            }
        }
        
        // add
        $this->meta[] = $data;

        // return self
        return $this;
    }
    
    /**
     * @param string $src
     * @param bool   $type
     * @param array  $props
     *
     * @return AssetsSet
     */
    public function addScript($src, $type, array $props = null)
    {
        // prepare
        $data = ['src' => $src, 'type' => $type];
        
        if(!empty($props))
        {
            foreach($props as $key => $value)
            {
                $data[$key] = $value;
            }
        }
        
        // add
        $this->scripts[] = $data;

        // return self
        return $this;
    }

    /**
     * @param string $href
     * @param string $rel
     * @param array  $props
     *
     * @return AssetsSet
     */
    public function addLink($href, $rel, array $props = null)
    {
        // prepare
        $data = ['href' => $href, 'rel' => $rel];

        if(!empty($props))
        {
            foreach($props as $key => $value)
            {
                $data[$key] = $value;
            }
        }

        // add
        $this->links[] = $data;

        // return self
        return $this;
    }
    
    /* high level management functions */
    
    /**
     * @param string $path
     * @param bool   $async
     *
     * @return AssetsSet
     */
    public function addJS($path, $async = false)
    {
        $props = [];
        if($async === true) $props['async'] = null;
        
        return $this->addScript($path, 'text/javascript', $props);
    }

    /**
     * @param string $path
     * @param string $media
     *
     * @return AssetsSet
     */
    public function addCSS($path, $media = 'all')
    {
        return $this->addLink($path, 'stylesheet', ['media' => $media]);
    }

    /**
     * @param string $name
     * @param string $content
     *
     * @return AssetsSet
     */
    public function addMetaStandard($name, $content)
    {
        return $this->addMeta(['name' => $name, 'content' => $content]);
    }
    
    /**
     * @param string $httpEquiv
     * @param string $content
     *
     * @return AssetsSet
     */
    public function addMetaHTTP($httpEquiv, $content)
    {
        return $this->addMeta(['http-equiv' => $httpEquiv, 'content' => $content]);
    }

    /**
     * @param string $charset
     *
     * @return AssetsSet
     */
    public function addCharset($charset)
    {
        return $this->addMeta(['charset' => $charset]);
    }
    
    /**
     * @param string $manifestURL
     *
     * @return AssetsSet
     */
    public function addManifest($manifestURL)
    {
        return $this->addLink($manifestURL, 'manifest');
    }
    
    /* PLACEMENT */

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
            $props = [];
            foreach($meta as $key => $value)
            {
                $props[] = $key.'="'.$value.'" ';
            }

            $elements[] = '<meta '.implode(' ', $props).'/>';
        }

        // return
        return $elements;
    }
    
    /**
     * @return array
     */
    public function buildDOMElementsScripts()
    {
        // vars
        $elements     = [];
        $versionQuery = empty(Assets::getCacheBuster()) ? '' : '?v='.Assets::getCacheBuster();

        // build
        foreach($this->scripts as $script)
        {
            if(preg_match('/^(https?:\/\/|\/)/', $script['src']))
            {
                $script['src'] = $script['src'].$versionQuery;
            }
            else
            {
                $script['src'] = ThemeManager::$themeURL.'/'.$script['src'].$versionQuery;
            }

            $props = [];
            foreach($script as $key => $value)
            {
                $props[] = $key.'="'.$value.'"';
            }
            
            $elements[] = '<script '.implode(' ', $props).'></script>';
        }

        // return
        return $elements;
    }

    /**
     * @return array
     */
    public function buildDOMElementsLinks()
    {
        // vars
        $elements     = [];
        $versionQuery = empty(Assets::getCacheBuster()) ? '' : '?v='.Assets::getCacheBuster();

        // build
        foreach($this->links as $link)
        {
            if(preg_match('/^(https?:\/\/|\/)/', $link['href']))
            {
                $link['href'] = $link['href'].$versionQuery;
            }
            else
            {
                $link['href'] = ThemeManager::$themeURL.'/'.$link['href'].$versionQuery;
            }

            $props = [];
            foreach($link as $key => $value)
            {
                $props[] = $key === null ? $key : $key.'="'.$value.'"';
            }

            $elements[] = '<link '.implode(' ', $props).' />';
        }

        // return
        return $elements;
    }
}
