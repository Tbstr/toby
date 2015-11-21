<?php

namespace Toby\Assets;

use \InvalidArgumentException;

class Assets
{
    /* public variables */
    public static $cacheBuster      = false;

    /* private variables */
    private static $sets            = array();

    /* set management */
    public static function setStandard()
    {
        // find existing & return
        foreach(self::$sets as $set)
        {
            if($set->type === Assets_Set::TYPE_STANDARD) return $set;
        }

        // create new & return
        $set = new Assets_Set();
        $set->type = Assets_Set::TYPE_STANDARD;

        self::$sets[] = $set;

        return $set;
    }

    public static function setResolvePath($path, $strictCompare = false)
    {
        // cancellation
        if(!is_string($path) || empty($path)) throw new InvalidArgumentException('argument $path is not of type string or empty');

        // find existing & return
        foreach(self::$sets as $set)
        {
            if($set->type === Assets_Set::TYPE_RESOLVE_PATH)
            {
                if($set->resolvePath === $path) return $set;
            }
        }

        // create new & set
        $set = new Assets_Set();

        $set->type                  = Assets_Set::TYPE_RESOLVE_PATH;

        $set->resolvePath           = $path;
        $set->resolvePathStrict     = $strictCompare;

        // add to list
        self::$sets[] = $set;

        // return
        return $set;
    }

    public static function setResolvePathDefault()
    {
        // find existing & return
        foreach(self::$sets as $set)
        {
            if($set->type === Assets_Set::TYPE_RESOLVE_PATH_DEFAULT) return $set;
        }

        // create new & set
        $set = new Assets_Set();
        $set->type = Assets_Set::TYPE_RESOLVE_PATH_DEFAULT;

        // add to list
        self::$sets[] = $set;

        // return
        return $set;
    }

    /* placement functionality */
    public static function getStandardSets()
    {
        // vars
        $setsOut = array();

        // find
        foreach(self::$sets as $set)
        {
            if($set->type === Assets_Set::TYPE_STANDARD) $setsOut[] = $set;
        }

        // return
        return $setsOut;
    }

    public static function getSetsByResolvePath($resolvePath)
    {
        // cancellation
        if(is_string($resolvePath) || empty($resolvePath)) throw new InvalidArgumentException('argument $resolvePath is not of type string or empty');

        // normalize input
        $resolvePath = strtolower($resolvePath);

        // vars
        $setsOut = array();

        // find matching
        foreach(self::$sets as $set)
        {
            if($set->type === Assets_Set::TYPE_RESOLVE_PATH)
            {
                if($set->resolvePathStrict === true)   { if($set->resolvePath === $resolvePath) $setsOut[] = $set; }
                else                                   { if(strncmp($set->resolvePath, $resolvePath, strlen($set->resolvePath)) === 0) $setsOut[] = $set; }
            }
        }

        // find matching
        if(empty($setsOut))
        {
            foreach(self::$sets as $set) { if($set->type === Assets_Set::TYPE_RESOLVE_PATH_DEFAULT) $setsOut[] = $set; }
        }

        // return
        return $setsOut;
    }
}