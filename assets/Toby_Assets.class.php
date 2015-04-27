<?php

class Toby_Assets
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
            if($set->type === Toby_Assets_Set::TYPE_STANDARD) return $set;
        }

        // create new & return
        $set = new Toby_Assets_Set();
        $set->type = Toby_Assets_Set::TYPE_STANDARD;

        self::$sets[] = $set;

        return $set;
    }

    public static function setResolvePath($path)
    {
        // cancellation
        if(empty($path)) throw new BadFunctionCallException('argument missing: path');

        // find existing & return
        foreach(self::$sets as $set)
        {
            if($set->type === Toby_Assets_Set::TYPE_RESOLVE_PATH)
            {
                if($set->resolvePath === $path) return $set;
            }
        }

        // create new & return
        $set = new Toby_Assets_Set();

        $set->type          = Toby_Assets_Set::TYPE_RESOLVE_PATH;
        $set->resolvePath   = $path;

        self::$sets[] = $set;

        return $set;
    }

    public static function setResolvePathDefault()
    {
        // find existing & return
        foreach(self::$sets as $set)
        {
            if($set->type === Toby_Assets_Set::TYPE_RESOLVE_PATH_DEFAULT) return $set;
        }

        // create new & return
        $set = new Toby_Assets_Set();
        $set->type = Toby_Assets_Set::TYPE_RESOLVE_PATH_DEFAULT;

        self::$sets[] = $set;

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
            if($set->type === Toby_Assets_Set::TYPE_STANDARD) $setsOut[] = $set;
        }

        // return
        return $setsOut;
    }

    public static function getSetsByResolvePath($resolvePath)
    {
        // cancellation
        if(empty($resolvePath)) throw new BadFunctionCallException('argument missing: resolvePath');

        // normalize input
        $resolvePath = strtolower($resolvePath);

        // vars
        $setsOut = array();

        // find matching
        foreach(self::$sets as $set)
        {
            if($set->type === Toby_Assets_Set::TYPE_RESOLVE_PATH)
            {
                if(strncmp($set->resolvePath, $resolvePath, strlen($set->resolvePath)) === 0) $setsOut[] = $set;
            }
        }

        // find matching
        if(empty($setsOut))
        {
            foreach(self::$sets as $set)
            {
                if($set->type === Toby_Assets_Set::TYPE_RESOLVE_PATH_DEFAULT) $setsOut[] = $set;
            }
        }

        // return
        return $setsOut;
    }
}