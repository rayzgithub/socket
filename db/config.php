<?php
ini_set('always_populate_raw_post_data','-1');
set_time_limit(0);
error_reporting(0);
//页面编码
header("Content-Type: text/html; charset=utf-8");
//时区
if (function_exists('date_default_timezone_set')) {
    @date_default_timezone_set("PRC");
}
define('JYSYSINC', 'nt');
/**
 * +----------------------------------------------------------
 * 是否为空
 * +----------------------------------------------------------
 * @default
 * @access default
 * @param $str{string}
 * +----------------------------------------------------------
 * @return Bool
 * +----------------------------------------------------------
 */
function isNull($string = '')
{
   $tmp=(!is_null($string) && isset($string) && $string !== '');
    return !$tmp? true : false;
}
/**
 * +----------------------------------------------------------
 * 获取全局变量
 * +----------------------------------------------------------
 * @default
 * @access default
 * @param string $key
 * @param $toObject 是否转对象
 * @param $toObjectType 是否全部转为对象（1是0否）
 * +----------------------------------------------------------
 * @return mixed
 * +----------------------------------------------------------
 */
function g($key='',$toObject=false,$toObjectType=0)
{
    if($key!="" && !isset($GLOBALS[$key])) return '';
    if($key=='') return $GLOBALS;
    return   (is_array($GLOBALS[$key]) && $toObject ?(arrayToObject($GLOBALS[$key],$toObjectType)):$GLOBALS[$key]);
}
/**
 * +----------------------------------------------------------
 * 数组转对象
 * +----------------------------------------------------------
 * @param array $array
 * @param int $toObjectType
 * @access default
 * +----------------------------------------------------------
 * @return object
 * +----------------------------------------------------------
 */
function arrayToObject( $array ,$toObjectType=0){
    if($toObjectType==1){
        foreach( $array as $key => $value ){
            if( is_array( $value ) ) $array[ $key ] = arrayToObject( $value );
        }
    }
    return (object) $array;
}
/**
 * +----------------------------------------------------------
 * 测试日志
 * +----------------------------------------------------------
 * @private
 * @access default
 * +----------------------------------------------------------
 * @return object
 * +----------------------------------------------------------
 */
function logs($ext = "", $resData = "", $head = 1, $filename = "")
{

    $filename = ($filename != "") ? $filename : 'data/logs/' . $ext . '_' . date('Y-m-d') .
        '.log';
    if (file_exists($filename)) {
        if (is_readable($filename)) {
            @chmod($filename, 0777);
        }
    }
    $fh = @fopen($filename, 'a');
    $messageContent = $resData;
    if ($fh) {
        @fwrite($fh, "  $messageContent \r\n");
        @fclose($fh);
    }
}

$_dbConfig=array();
/**
 * 业务主要数据库
 */
//数据库类型
$_dbConfig['main']['dbType']="mysql";
//主从配置
$_dbConfig['main']['deploy']=false;
//读写分离
$_dbConfig['main']['rw_separate']=false;
//主数量-避免计算数量，直接填写数字
$_dbConfig['main']['master_num']=1;
//总数-避免计算数量，直接填写数字
$_dbConfig['main']['host_num']=1;
//数据库清单 规则：主数据库放在一起，从数据库放在一起
$_dbConfig['main']['list']=array();
$_dbConfig['main']['list'][]=array(
    "id"=>"",//连接识别,默认空
    "host"=>"localhost",
    "port"=>"3306",
    "username"=>"root",
    "password"=>"1867362731@Fb",
    "dbName"=>"wordpress",
    "prefixFrom"=>"__dbprefix__",
    "prefixTo"=>"zy_",
);
require_once ("dbManager.class.php");
require_once ("sdb.class.php");
require_once ("redisClient.class.php");