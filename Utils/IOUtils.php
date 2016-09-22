<?php

namespace Toby\Utils;

class IOUtils
{
    public static function fileReadBackwards($path, $maxLines = INF)
    {
        $file = fopen($path, 'r');
        $cursor = 0;
        $lineBuffer = '';

        $linesCount = 0;
        $lines = array();

        while(fseek($file, $cursor, SEEK_END) !== -1)
        {
            $char = fgetc($file);

            if($char === "\n")
            {
                $lines[] = $lineBuffer;
                $lineBuffer = '';
                $linesCount++;

                if($linesCount >= $maxLines) break;
            }
            else $lineBuffer = $char.$lineBuffer;

            // proceed
            $cursor--;
        }

        fclose($file);
        if(!empty($lineBuffer)) $lines[] = $lineBuffer;

        return $lines;
    }

    public static function mkdir($path, $mode = 0777, $recursive = false)
    {
        // check for existance
        if(is_dir($path)) return true;

        // change umask & create dir
        $umask = umask(0);
        $result = mkdir($path, $mode, $recursive);
        umask($umask);

        // return result
        return $result;
    }
}
