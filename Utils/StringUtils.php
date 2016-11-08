<?php

namespace Toby\Utils;

class StringUtils
{
    const CHARSET_BASE58 = "123456789abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ";
    const CHARSET_BASE62 = "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
    const CHARSET_PASSWD = "23456789abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ";
    const CHARSET_PASSLC = "23456789abcdefghjkmnpqrstuvwxyz";

    public static function randomChars($numChars, $charSet = self::CHARSET_BASE62)
    {
        $charsetLength = strlen($charSet);
        $result = '';

        for ($i = 0; $i < $numChars; $i++)
        {
            $result .= $charSet[rand(0, $charsetLength - 1)];
        }

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

        // floor by precision
        $precisionPow = pow(10, $precision);
        $bytes = floor($bytes * $precisionPow) / $precisionPow;
        
        // return
        return $bytes.' '.$units[$pow];
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

        // check (regexp from http://emailregex.com/)
        return (boolean)preg_match('/^(?!(?:(?:\x22?\x5C[\x00-\x7E]\x22?)|(?:\x22?[^\x5C\x22]\x22?)){255,})(?!(?:(?:\x22?\x5C[\x00-\x7E]\x22?)|(?:\x22?[^\x5C\x22]\x22?)){65,}@)(?:(?:[\x21\x23-\x27\x2A\x2B\x2D\x2F-\x39\x3D\x3F\x5E-\x7E]+)|(?:\x22(?:[\x01-\x08\x0B\x0C\x0E-\x1F\x21\x23-\x5B\x5D-\x7F]|(?:\x5C[\x00-\x7F]))*\x22))(?:\.(?:(?:[\x21\x23-\x27\x2A\x2B\x2D\x2F-\x39\x3D\x3F\x5E-\x7E]+)|(?:\x22(?:[\x01-\x08\x0B\x0C\x0E-\x1F\x21\x23-\x5B\x5D-\x7F]|(?:\x5C[\x00-\x7F]))*\x22)))*@(?:(?:(?!.*[^.]{64,})(?:(?:(?:xn--)?[a-z0-9]+(?:-[a-z0-9]+)*\.){1,126}){1,}(?:(?:[a-z][a-z0-9]*)|(?:(?:xn--)[a-z0-9]+))(?:-[a-z0-9]+)*)|(?:\[(?:(?:IPv6:(?:(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){7})|(?:(?!(?:.*[a-f0-9][:\]]){7,})(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,5})?::(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,5})?)))|(?:(?:IPv6:(?:(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){5}:)|(?:(?!(?:.*[a-f0-9]:){5,})(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,3})?::(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,3}:)?)))?(?:(?:25[0-5])|(?:2[0-4][0-9])|(?:1[0-9]{2})|(?:[1-9]?[0-9]))(?:\.(?:(?:25[0-5])|(?:2[0-4][0-9])|(?:1[0-9]{2})|(?:[1-9]?[0-9]))){3}))\]))$/iD', $mail);
    }

    public static function validateURL($url)
    {
        // cancellation
        if(empty($url)) return false;

        // check
        return (boolean)preg_match('/^https?:\/\/([0-9.]+(:\d{1,5})?|(?![0-9 .-])(\.?(-?[0-9a-z]+)+)+\.[a-z]{2,32})(\/[\w .-]+)?\/?(\?(&?\w+(=\w+)?)+)?$/i', $url);
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

    /**
     * converts a string that is not already encoded in UTF-8
     *
     * @param string $value
     * @param string $preferredSourceEncoding encoding to try before using detected encoding
     * @return string
     */
    public static function convertToUtf8($value, $preferredSourceEncoding = null)
    {
        $encoding = mb_detect_encoding($value, array("UTF-8", "Windows-1252", "ISO-8859-15", "ISO-8859-1"));
        if ($encoding === false)
        {
            return $value;
        }

        if ($encoding !== 'UTF-8')
        {
            $convertedValue = false;

            // look for common special characters (äöüßÄÖÜéè) encoded in MacRoman
            if (preg_match("/[\x8a\x9a\x9f\xa7\x80\x85\x86\x8e\x8f]/", $value))
            {
                $convertedValue = @iconv("MACINTOSH", "UTF-8", $value);
            }

            if ($preferredSourceEncoding !== null)
            {
                // first try to convert using preferred encoding
                $convertedValue = @iconv($preferredSourceEncoding, "UTF-8", $value);
            }

            if ($convertedValue === false)
            {
                $convertedValue = mb_convert_encoding($value, "UTF-8", $encoding);
            }

            $value = $convertedValue;
        }

        if (SysUtils::extensionLoaded("intl"))
        {
            // normalize UTF-8 into NFC
            $value = \Normalizer::normalize($value, \Normalizer::FORM_C);
        }

        return $value;
    }

    public static function convertToUtf8PreferringMacRoman($value)
    {
        return self::convertToUtf8($value, "MACINTOSH");
    }
}
