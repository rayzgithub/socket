<?php

if (!defined('JYSYSINC'))
    exit('JYSYS:No direct script access allowed');
/**
 * 文件缓存类
 *
 * @version        $Id: filecache.class.php 2 2012-5-14 $
 * @package        Jysys.Libraries
 * @copyright      Copyright (c) 2012, REY, Inc.
 * @link           http://www.joyql.com
 */
class filecache
{

    static $cachefile;
    static $cachetype='base64';
    static $phpexit = '<?php exit("JYSYS");?>';
    /**
     * +----------------------------------------------------------
     * 析构函数
     * +----------------------------------------------------------
     * @default
     * @access default
     * +----------------------------------------------------------
     * @return void
     * +----------------------------------------------------------
     */
    function __construct($type = 'base64')
    {
        self::$cachetype = $type;
    }
    /**
     * +----------------------------------------------------------
     * 获取缓存文件
     * +----------------------------------------------------------
     * @default
     * @access default
     * +----------------------------------------------------------
     * @return void
     * +----------------------------------------------------------
     */
    public static function getcachefile($cachename)
    {
        self::$cachefile = '/home/www/duyun8690/data/cache/' . $cachename . '.php';
    }
    /**
     * +----------------------------------------------------------
     * 是否失效
     * +----------------------------------------------------------
     * @default
     * @access default
     * +----------------------------------------------------------
     * @return Bool
     * +----------------------------------------------------------
     */
    public static function isvalid($cachename, $cachetime)
    {
        if (0 == $cachetime)
        {
            return true;
        }
        self::getcachefile($cachename);
        if (!is_readable(self::$cachefile) || $cachetime < 0)
        {
            return false;
        }
        clearstatcache();
        return (time() - filemtime(self::$cachefile)) < $cachetime;
    }
    /**
     * +----------------------------------------------------------
     * 读取缓存内容
     * +----------------------------------------------------------
     * @default
     * @access default
     * +----------------------------------------------------------
     * @return String
     * +----------------------------------------------------------
     */
    public static function getcache($cachename, $cachetime = 0)
    {
        $data = null;
        self::getcachefile($cachename);
        if (file_exists_case(self::$cachefile))
        {
            if (self::isvalid($cachename, $cachetime))
            {
                if ($fp = @fopen(self::$cachefile, 'rb'))
                {
                    if (PHP_VERSION >= '4.3.0' && function_exists('file_get_contents'))
                    {
                        $data = file_get_contents(self::$cachefile);
                    } else
                    {
                        flock($fp, LOCK_EX);
                        $data = fread($fp, filesize(self::$cachefile));
                        flock($fp, LOCK_UN);
                        fclose($fp);
                    }
                    N('getcache', 1);
                    $method = 'decode_' . self::$cachetype;
                    $data = substr($data, 22);
                    $data = self::$method($data);


                }
            }
        }
        return $data;
    }
    /**
     * +----------------------------------------------------------
     * 写入缓存
     * +----------------------------------------------------------
     * @default
     * @access default
     * +----------------------------------------------------------
     * @return Object
     * +----------------------------------------------------------
     */
    public static function writecache($cachename, $data)
    {
        $bytes = 0;
        self::getcachefile($cachename);
        $method = 'encode_' . self::$cachetype;
        $data = self::$method($data);
        $data = self::$phpexit . $data;
        if ($fp = @fopen(self::$cachefile, 'wb'))
        {
            if (PHP_VERSION >= '4.3.0' && function_exists('file_put_contents'))
            {
                return file_put_contents(self::$cachefile, $data);
            } else
            {
                flock($fp, LOCK_EX);
                $data = u2utf8($data); //转换为UTF-8编码
                $bytes = fwrite($fp, $data);
                N('writecache', 1);
                flock($fp, LOCK_UN);
                fclose($fp);
            }
        }
        return $bytes;
    }
    /**
     * +----------------------------------------------------------
     * 删除缓存文件
     * +----------------------------------------------------------
     * @default
     * @access default
     * +----------------------------------------------------------
     * @return Void
     * +----------------------------------------------------------
     */
    public static function removecache($cachename)
    {
        self::getcachefile($cachename);
        if (file_exists(self::$cachefile))
        {
            unlink(self::$cachefile);
        }
    }
    /**
     * +----------------------------------------------------------
     * base64加密内容
     * +----------------------------------------------------------
     * @default
     * @access default
     * +----------------------------------------------------------
     * @return Bool
     * +----------------------------------------------------------
     */
    public static function encode_base64($data)
    {
        return base64_encode(@serialize($data));
        ;
    }
    /**
     * +----------------------------------------------------------
     * base64解密内容
     * +----------------------------------------------------------
     * @default
     * @access default
     * +----------------------------------------------------------
     * @return Bool
     * +----------------------------------------------------------
     */
    public static function decode_base64($data)
    {
        $datas = str_replace('!s:(\d+):"(.*?)";!se', "'s:'.strlen('$2').':\"$2\";'", $data); //解决unserilize Error at offset错误
        return @unserialize(base64_decode($datas));

    }

}
