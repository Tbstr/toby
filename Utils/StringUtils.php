<?php

namespace Toby\Utils;

class StringUtils
{
    public static function randomChars($numChars, $charSet = null)
    {
        // vars
        if($charSet === null) $charSet = str_shuffle('abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ0123456789');
        $charCount  = strlen($charSet);
        $result = '';

        // generate
        $i = 0;
        while($i < $numChars)
        {
            $result .= $charSet[rand(0, $charCount - 1)];
            $i++;
        }

        // return
        return $result;
    }

    public static function formatFileSize($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        // Uncomment one of the following alternatives
        // $bytes /= pow(1024, $pow);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, $precision).' '.$units[$pow];
    }

    public static function constrain($string, $length, $ellipsis = true, $revert = false)
    {
        if(strlen($string) > $length)
        {
            $string = mb_substr ($string, ($revert ? -$length : 0), $length);
            if($ellipsis) $string = $revert ? '…'.$string : $string.'…';
        }

        return $string;
    }

    public static function replaceChars($chars, $replacement, $subject)
    {
        $strlen = mb_strlen($subject);
        $result = '';

        for($i = 0; $i < $strlen; $i++)
        {
            $chr = mb_substr($subject, $i, 1);
            $result .= (mb_strpos($chars, $chr) === false) ? $chr : $replacement;
        }

        return $result;
    }
    
    public static function text2html($text)
    {
        $text = preg_replace('/>[ \t]*(\n\r?|\r\n?)/', '>', $text);
        $text = nl2br($text);

        $text = str_replace("ä", '&auml;', $text);
        $text = str_replace("Ä", '&Auml;', $text);
        $text = str_replace("ö", '&ouml;', $text);
        $text = str_replace("Ö", '&Ouml;', $text);
        $text = str_replace("ü", '&uuml;', $text);
        $text = str_replace("Ü", '&Uuml;', $text);

        $text = str_replace("ß", '&szlig;', $text);
        $text = str_replace("'", '&apos;', $text);
        $text = str_replace("«", '&laquo;', $text);
        $text = str_replace("»", '&raquo;', $text);

        return $text;
    }

    public static function validateMail($mail)
    {
        // cancellation
        if(empty($mail)) return false;

        // check
        return (boolean)preg_match('/^[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,32}$/i', $mail);
    }

    public static function validateURL($url)
    {
        // cancellation
        if(empty($url)) return false;

        // check
        return (boolean)preg_match('/^(https?:\/\/)?([0-9a-z\.-]+)\.([a-z\.]{2,32})([\/\w \.-]*)*\/?$/i', $url);
    }

    public static function buildPath(array $elements, $separator = '/')
    {
        // prepare
        for($i = 0, $c = count($elements); $i < $c; $i++)
        {
            if($i > 0) $elements[$i] = ltrim($elements[$i], $separator);
            if($i < $c - 1) $elements[$i] = rtrim($elements[$i], $separator);
        }

        // return
        return implode($separator, $elements);
    }
}