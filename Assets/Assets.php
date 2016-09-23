<?php

namespace Toby\Assets;

class Assets
{
    /* private variables */
    private static $defaultSet               = null;
    private static $setsForLayout            = null;
    private static $setsForResolvePath       = [];
    private static $setsForResolvePathStrict = [];
    private static $setForResolvePathUnset   = null;

    private static $cacheBuster              = null;

    /* set management */

    /**
     * @return AssetsSet
     */
    public static function byDefault()
    {
        // lazy create
        if(self::$defaultSet === null)
        {
            self::$defaultSet = new AssetsSet();
        }
        
        // return
        return self::$defaultSet;
    }

    /**
     * @param $layout
     *
     * @return AssetsSet
     */
    public static function forLayout($layout)
    {
        // lazy create
        if(!isset(self::$setsForLayout[$layout]))
        {
            self::$setsForLayout[$layout] = new AssetsSet();
        }

        // return
        return self::$setsForLayout[$layout];
    }

    /**
     * @param string $path
     * @param bool   $strictCompare
     *
     * @return AssetsSet
     */
    public static function forResolvePath($path, $strictCompare = false)
    {
        // strict
        if($strictCompare)
        {
            // lazy create
            if(!isset(self::$setsForResolvePathStrict[$path]))
            {
                self::$setsForResolvePathStrict[$path] = new AssetsSet();
            }

            // return
            return self::$setsForResolvePathStrict[$path];
        }
        
        // not strict
        else
        {
            if(!isset(self::$setsForResolvePath[$path]))
            {
                self::$setsForResolvePath[$path] = new AssetsSet();
            }

            // return
            return self::$setsForResolvePath[$path];
        }
    }

    /**
     * @return AssetsSet
     */
    public static function forResolvePathUnset()
    {
        // lazy create
        if(self::$setForResolvePathUnset === null)
        {
            self::$setForResolvePathUnset = new AssetsSet();
        }

        // return
        return self::$setForResolvePathUnset;
    }

    /* cachebuster */

    /**
     * @param string $cacheBuster
     */
    public static function setCacheBuster($cacheBuster)
    {
        self::$cacheBuster = empty($cacheBuster) ? null : (string)$cacheBuster;
    }

    /**
     * @return string|null
     */
    public static function getCacheBuster()
    {
        return self::$cacheBuster;
    }
    
    /* collecting sets */

    /**
     * @param string $layout
     * @param string $resolvePath
     *
     * @return array
     */
    public static function gatherSets($layout, $resolvePath)
    {
        // gather sets
        $sets = [];

        $defaultSet = self::getDefaultSet();
        if($defaultSet !== null) $sets[] = $defaultSet;

        $layoutSet = self::getSetForLayout($layout);
        if($layoutSet !== null) $sets[] = $layoutSet;

        $sets = array_merge($sets, self::getSetsForResolvePath($resolvePath));

        // return
        return $sets;
    }
    
    /**
     * @return AssetsSet
     */
    public static function getDefaultSet()
    {
        return self::$defaultSet;
    }

    /**
     * @param string $layout
     *
     * @return AssetsSet
     */
    public static function getSetForLayout($layout)
    {
        return isset(self::$setsForLayout[$layout]) ? self::$setsForLayout[$layout] : null;
    }
    
    /**
     * @param string $resolvePath
     *
     * @return AssetsSet[]
     */
    public static function getSetsForResolvePath($resolvePath)
    {
        // normalize input
        $resolvePath = strtolower($resolvePath);

        // vars
        $sets = [];
        
        // get strict
        if(isset(self::$setsForResolvePathStrict[$resolvePath]))
        {
            $sets[] = self::$setsForResolvePathStrict[$resolvePath];
        }

        // unstrict
        foreach(self::$setsForResolvePath as $path => $set)
        {
            if(strncmp($path, $resolvePath, strlen($path)) === 0)
            {
                $sets[] = $set;
            }
        }

        // unset
        if(empty($sets))
        {
            if(self::$setForResolvePathUnset !== null)
            {
                $sets[] = self::$setForResolvePathUnset;
            }
        }

        // return
        return $sets;
    }
}
