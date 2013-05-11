<?php

class Toby_ConfigFileManager
{
    public static function read($filePath, $processSections = false)
    {
        if(file_exists($filePath))
        {
            if(!$handle = @fopen($filePath, 'r')) return false;
            
            $data = array();
            
            if($processSections)
            {
                $data['global'] = array();
                $dataCursor = &$data['global'];
            }
            else $dataCursor = &$data;
            
            while(false !== ($line = fgets($handle)))
            {
                // trim line
                $line = trim($line);
                
                // continue if commented or empty
                if(strlen($line) == 0) continue;
                if($line[0] == '#') continue;
                
                // proove for section marker
                if($line[0] == '[' && $line[strlen($line)-1] == ']')
                {
                    if($processSections)
                    {
                        $sectionName = substr($line, 1, strlen($line) - 2);
                        $data[$sectionName] = array();
                        $dataCursor = &$data[$sectionName];
                    }
                    
                    continue;
                }
                
                // get line parts
                list($keys, $value) = explode('=', $line, 2);
                $keys = trim($keys);
                $value = self::readElement(trim($value));
                
                // get key parts
                $counter = 0;
                $buffer = '';
                $parts = array();
                
                // get keys
                if(strpos($keys, '[') === false) array_push($parts, $keys);
                else
                {
                    while($counter < strlen($keys))
                    {
                        switch($line[$counter])
                        {
                            case '[':
                                if(count($parts) == 0)
                                {
                                    array_push($parts, trim($buffer));
                                    $buffer = '';
                                }
                                break;

                            case ']':
                                array_push($parts, trim($buffer));
                                $buffer = '';
                                break;

                            default:
                                $buffer .= $line[$counter];
                                break;

                        }

                        $counter++;
                    }
                }
                
                // apply key parts
                $counter = 0;
                $keydataCursor = &$dataCursor;
                foreach($parts as $part)
                {
                    $counter++;
                    $last = $counter == count($parts);

                    if($part == '')
                    {
                        $index = array_push($keydataCursor, ($last ? $value : array()));
                        $keydataCursor = &$keydataCursor[$index - 1];
                    }
                    else
                    {
                        if(!isset($keydataCursor[$part]))
                        {
                            $keydataCursor[$part] = ($last ? $value : array());
                        }

                        $keydataCursor = &$keydataCursor[$part];
                    }
                }
            }
            
            fclose($handle);
            return $data;
        }
        
        return false;
    }
    
    private static function readElement($element)
    {
        // boolean
        $toLower = strtolower($element);
        if($toLower == 'true' || $toLower == 'false') return $toLower == 'true' ? true : false;
        
        // number
        if(is_numeric($element))
        {
            if(strpos($element, '.') === false) return (int)$element;
            else return (float)$element;
        }
        
        // else
        return $element;
    }
    
    public static function write($filePath, $data)
    {
        $content = self::writeDataset($data);

        if(!$handle = @fopen($filePath, 'w')) return false;
        if(!@fwrite($handle, $content)) return false;
        
        fclose($handle);
        return true; 
    }
    
    private static function writeDataset($dataset)
    {
        $content = '';
        
        foreach($dataset as $key => $elem)
        {
            // array
            if(is_array($elem)) 
            { 
                // associative
                if(self::isAssoc($elem)) foreach($elem as $subKey => $subElem) $content .= $key . "[" . $subKey . "] = " . self::writeElement($subElem) . "\n"; 

                // not associative
                else foreach($elem as $subElem) $content .= $key . "[] = " . self::writeElement($subElem) . "\n";
            }

            // element
            else $content .= $key . " = " . self::writeElement($elem) . "\n"; 
        }
        
        // return
        return $content;
    }
    
    private static function writeElement($element)
    {
        // string
        if(is_string($element)) return '"' . $element . '"';
        
        // boolean
        elseif(is_bool($element)) return $element === true ? 'true' : 'false';
        
        // else
        else return $element;
    }
    
    private static function isAssoc($arr)
    {
        return array_keys($arr) !== range(0, count($arr) - 1);
    }
}