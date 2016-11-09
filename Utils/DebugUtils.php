<?php

namespace Toby\Utils;

use Toby\MySQL\MySQL;
use Toby\Toby;

class DebugUtils
{
    private static $timingValues = [];

    /* timing */
    public static function timingStart($id = null)
    {
        // id
        if($id === null) $id = uniqid(StringUtils::randomChars(4));

        // set startpoint
        self::$timingValues[$id] = microtime(true);

        // return
        return $id;
    }

    public static function timingStop($id)
    {
        // return diff
        return number_format((microtime(true) - self::$timingValues[$id]) * 1000, 3);
    }
    
    /* mysql query recording */
    public static function sqlRecStart()
    {
        return MySQL::getInstance()->startQueryRecording();
    }

    public static function sqlRecStop($print = true, $finalize = true)
    {
        $recLog = MySQL::getInstance()->stopQueryRecording();

        if($print) SysUtils::printr($recLog);
        if($finalize) Toby::getInstance()->finalize(0);
        
        return $recLog;
    }

    /* print debug backtrace */
    public static function printDebugBacktrace($return = false)
    {
        $backtraceInfo = debug_backtrace();
        $backtrace = array();

        foreach($backtraceInfo as $t)
        {
            // reset str
            $str = '';

            // class
            if(isset($t['class'])) $str .= $t['class'].$t['type'];

            // function
            $str .= $t['function'];

            $backtrace[] = $str;
        }

        // print or return
        if($return) return implode("\n", $backtrace);
        SysUtils::printr(implode("\n", $backtrace));

        return null;
    }

    /* function benchmark */

    /**
     * @param callable $callable
     * @param int      $iterations
     * @param array    $arguments
     */
    public static function fnBenchmark(callable $callable, $iterations = 1, array $arguments = null)
    {
        // init
        $callableStr = is_array($callable) ? implode('::', $callable) : (string)$callable;

        // iterate
        $startTime = microtime(true);
        
        $c = $iterations;
        while($c !== 0)
        {
            if(empty($arguments))
            {
                call_user_func($callable);
            }
            else
            {
                call_user_func_array($callable, $arguments);
            }
            
            $c--;
        }
        
        $endTime = microtime(true);

        // report
        SysUtils::printr("$callableStr with $iterations iterations: ".number_format(($endTime - $startTime) * 1000, 2).'ms');
    }

}
