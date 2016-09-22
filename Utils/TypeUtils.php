<?php

namespace Toby\Utils;

class TypeUtils
{
    public static function asArray($obj)
    {
        return is_array($obj) ? $obj : [$obj];
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

                if($value === null) return new \stdClass();
                else return (object) $value;
                break;

            default:

                if($value === null) return null;
                else return $value;
                break;
        }
    }
}
