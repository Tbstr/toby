<?php

class Toby_Utils
{
    public static $mailDryRun       = false;
    
    private static $stoppwatchData  = array();
    
    public static function printr()
    {
        $args = func_get_args();
        foreach($args as $arg) echo '<pre>'.print_r($arg, true).'</pre>';
    }
    
    /* debug */
    public static function debugBacktrace($return = false)
    {
        $backtraceInfo = debug_backtrace();
        $backtrace = array();
        
        $str = '';
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
        self::printr(implode("\n", $backtrace));
    }
    
    public static function mySQLRecStart()
    {
        return Toby_MySQL::getInstance()->startQueryRecording();
    }
    
    public static function mySQLRecStop($print = true, $finalize = true)
    {
        $recLog = Toby_MySQL::getInstance()->stopQueryRecording();
        
        if($print) Toby_Utils::printr($recLog);
        if($finalize) Toby::finalize(0);
        return $recLog;
    }
    
    public static function clearPath($path)
    {
        if($path == null) return "";
        
        // add backslash
        $path = rtrim($path, '\/');
        if(strlen($path) == 0) $path = '/';
        
        // return
        return $path;
    }
    
    public static function array2object($array)
    {
        if(!is_array($array)) return $array;

        $object = new stdClass();
        
        if(is_array($array))
        {
            foreach($array as $key => $value)
            {
                if(!empty($key)) $object->$key = Toby_Utils::array2object($value);
            }
        }
        
        // return
        return $object;
    }

    public static function object2array($object)
    {
        if(!is_object($object) && !is_array($object)) return $object;
        if(is_object($object)) $object = get_object_vars($object);

        return array_map('object2array', $object);
    }
    
    public static function formatFileSize($bytes, $precision = 2)
    {
        $units = array('B', 'KB', 'MB', 'GB', 'TB'); 

        $bytes = max($bytes, 0); 
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024)); 
        $pow = min($pow, count($units) - 1); 

        // Uncomment one of the following alternatives
        // $bytes /= pow(1024, $pow);
        $bytes /= (1 << (10 * $pow)); 

        return round($bytes, $precision).' '.$units[$pow]; 
    }
    
    public static function getMIMEFromExtension($filename)
    {
        // cancellation
        if(empty($filename)) return false;
        
        // vars
        $extension = strtolower(substr($filename, strrpos($filename, '.') + 1));
        
        $mimes = array(
            'pdf'      => 'application/pdf',
            'zip'      => 'application/zip',
            'gif'      => 'image/gif',
            'jpg'      => 'image/jpeg',
            'jpeg'     => 'image/jpeg',
            'jpe'      => 'image/jpeg',
            'png'      => 'image/png',
            'tif'      => 'image/tiff',
            'tiff'     => 'image/tiff',
            'htm'      => 'text/html',
            'html'     => 'text/html',
            'shtml'    => 'text/html',
            'txt'      => 'text/plain',
            'xml'      => 'text/xml'
        );
        
        // check
        if(isset($mimes[$extension])) return $mimes[$extension];
        else return false;
    }
    
    public static function constrainString($string, $length, $ellipsis = true, $backwards = false)
    {
        if(strlen($string) > $length)
        {
            $string = mb_substr ($string, ($backwards ? -$length : 0), $length);
            if($ellipsis) $string = $backwards ? '…'.$string : $string.'…';
        }
        
        return $string;
    }
    
    public static function listExtensions()
    {
        self::printr(get_loaded_extensions());
    }
    
    public static function extensionLoaded($name)
    {
        return in_array($name, get_loaded_extensions());
    }
    
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
    
    public static function rrmdir($path, $basePath = '', $allowBacklinks = false)
    {
        // cancellation
        if(!file_exists($path)) return false;
        if(strncmp($path, $basePath, strlen($basePath)) !== 0) return false;
        if(!$allowBacklinks) if(strpos($path, '..') !== false) return false;

        // rm
        if(is_file($path))
        {
            return @unlink($path);
        }
        else
        {
            $subfiles = scandir($path);
            foreach($subfiles as $subfile)
            {
                if($subfile == '.') continue;
                if($subfile == '..') continue;
                
                if(!self::rrmdir($path.'/'.$subfile, $basePath, $allowBacklinks)) return false;
            }
            
            return @rmdir($path);
        }
    }
    
    public static function addLeadingSlash($path)
    {
        if(empty($path)) return '/';
        
        $path = trim($path);
        if($path[0] != '/') return '/'.$path;
        
        return $path;
    }
    
    public static function addTrailingSlash($path)
    {
        if(empty($path)) return '/';
        
        $path = trim($path);
        if($path[strlen($path) - 1] != '/') return $path.'/';
        
        return $path;
    }
    
    public static function pathCombine($elements, $separator = '/')
    {
        // cancellation
        if(!is_array($elements)) return false;
        
        // prepare
        for($i = 0, $c = count($elements); $i < $c; $i++)
        {
            if($i > 0) $elements[$i] = ltrim($elements[$i], $separator);
            if($i < $c - 1) $elements[$i] = rtrim($elements[$i], $separator);
        }
        
        // return
        return implode($separator, $elements);
    }
    
    public static function plain2html($text)
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
    
    public static function parseValue($value, $datatype)
    {
        switch(strtolower($datatype))
        {    
            case 'boolean':
            case 'bool':
                
                if($value === null) return false;
                else  return (boolean) $value;
                break;
                
            case 'integer':
            case 'int':
                
                if($value === null) return -1;
                else return (int) $value;
                break;
                
            case 'float':
            case 'double':
            case 'real':
                
                if($value === null) return -1;
                else return (float) $value;
                break;
                
            case 'string':
                
                if($value === null) return '';
                else return (string) $value;
                break;
                
            case 'array':
                
                if($value === null) return array();
                else return (array) $value;
                break;
                
            case 'object':
                
                if($value === null) return new stdClass();
                else return (object) $value;
                break;
                
            default:
                
                if($value === null) return null;
                else return $value;
                break;
        }
    }
    
    public static function benchmark($method, $iterations = 1, $arguments = null)
    {
        // init
        $methodName = is_array($method) ? implode('::', $method) : (string)$method;
        
        // iterate
        $startTime = microtime(true);
        $c = $iterations; while($c--)
        {
            if(empty($arguments)) call_user_func($method);
            else call_user_func_array($method, $arguments);
        }
        $endTime = microtime(true);
        
        // report
        Toby_Utils::printr("$methodName with $iterations iterations: ".number_format(($endTime - $startTime) * 1000, 2).'ms');
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
    
    public static function randomChars($numChars, $charSet = null)
    {
        // vars
        if($charSet === null) $charSet = str_shuffle('abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ0123456789');
        $charCount  = strlen($charSet);
        $out    = '';
        
        // generate
        $i = 0;
        while($i < $numChars)
        {
            $out .= $charSet[rand(0, $charCount - 1)];
            $i++;
        }
        
        // return
        return $out;
    }
    
    public static function stoppwatchStart($id = false)
    {
        // id
        if($id === false) $id = uniqid(self::randomChars(4));
        
        // set startpoint
        self::$stoppwatchData[] = array(
            'id'    => $id,
            'st'    => microtime(true)
        );
        
        // return
        return $id;
    }
    
    public static function stoppwatchStop($id)
    {
        // find entry
        $data = null;
        foreach(self::$stoppwatchData as $swd)
        {
            if($swd['id'] === $id)
            {
                $data = &$swd;
                break;
            }
        }
        
        if($data === null) return false;
        
        // return diff
        return number_format((microtime(true) - $data['st']) * 1000, 3);
    }
}