<?php

namespace Toby\Assets;

class Assets
{
    /* private variables */
    private static $cacheBuster             = null;
    
    private static $defaultSet               = null;
    private static $setsForLayout            = null;
    private static $setsForResolvePath       = [];
    private static $setsForResolvePathStrict = [];
    private static $setForResolvePathUnset   = null;

    /* set management */
    public static function byDefault()
    {
        // lazy create
        if(self::$defaultSet === null)
        {
            self::$defaultSet = new AssetsSet(AssetsSet::TYPE_DEFAULT);
        }
        
        // return
        return self::$defaultSet;
    }

    public static function forLayout($layout)
    {
        // lazy create
        if(!isset(self::$setsForLayout[$layout]))
        {
            $set = new AssetsSet(AssetsSet::TYPE_LAYOUT);
            $set->layout = $layout;

            self::$setsForLayout[$layout] = $set;
        }

        // return
        return self::$setsForLayout[$layout];
    }

    public static function forResolvePath($path, $strictCompare = false)
    {
        // strict
        if($strictCompare)
        {
            // lazy create
            if(!isset(self::$setsForResolvePathStrict[$path]))
            {
                $set = new AssetsSet(AssetsSet::TYPE_RESOLVE_PATH);
                $set->resolvePath           = $path;
                $set->resolvePathStrict     = true;

                self::$setsForResolvePathStrict[$path] = $set;
            }

            // return
            return self::$setsForResolvePathStrict[$path];
        }
        
        // not strict
        else
        {
            if(!isset(self::$setsForResolvePath[$path]))
            {
                $set = new AssetsSet(AssetsSet::TYPE_RESOLVE_PATH);
                $set->resolvePath           = $path;
                $set->resolvePathStrict     = false;

                self::$setsForResolvePath[$path] = $set;
            }

            // return
            return self::$setsForResolvePath[$path];
        }
    }

    public static function forResolvePathUnset()
    {
        // lazy create
        if(self::$setForResolvePathUnset === null)
        {
            self::$setForResolvePathUnset = new AssetsSet(AssetsSet::TYPE_RESOLVE_PATH_UNSET);
        }

        // return
        return self::$setForResolvePathUnset;
    }

    /* cachebuster */
    public static function setCacheBuster($cacheBuster)
    {
        self::$cacheBuster = empty($cacheBuster) ? null : (string)$cacheBuster;
    }

    public static function getCacheBuster()
    {
        return self::$cacheBuster;
    }
    
    /* collecting sets */

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
