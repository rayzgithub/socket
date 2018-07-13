<?php if (!defined('JYSYSINC'))
    exit('JYSYS:No direct script access allowed');
/**
 * 数据库操作类-静态调用
 *
 * @version        $Id: sdb.class.php 2 2012-5-16 $
 * @package        Jysys.Libraries
 * @copyright      Copyright (c) 2012, REY, Inc.
 * @link           http://www.joyql.com
 */
class sdb 
{
    protected static $dbs = null;

    /**
     * 初始化数据库连接
     * //支持分布式服务器
     * @access static
     * @param array  $param 扩展参数
     * @param boolean $master 主服务器     *
     * @return void
     */
    public static function init($param=array(),$master=false)
    {
        if(self::$dbs==null) {
            $_dbManager = new dbManager();
            if (isset($param['extends'])) {
                $extends = g('_dbConfig', true)->extends;
                $_dbClass = $_dbManager->getInstance($extends);
                $curConnNo = floor(mt_rand(0, $extends['host_num'] - 1));
                self::$dbs = $_dbClass[$curConnNo];
            } else {
                $_dbClass = $_dbManager->getInstance();
                $mainHost = g('_dbConfig', true)->main;
                $curConnNo = floor(mt_rand(0, $mainHost['host_num'] - 1));
                if ($mainHost['deploy']) {
                    if ($mainHost['rw_separate'] && $master) {
                        $curConnNo = floor(mt_rand(0, $mainHost['master_num'] - 1));
                    } else {
                        $curConnNo = floor(mt_rand($mainHost['master_num'], $mainHost['host_num'] - 1));
                    }
                }                
                self::$dbs = $_dbClass[$curConnNo];
            }
        }

    }
    /**
     * +----------------------------------------------------------
     * 查询语句
     * +----------------------------------------------------------
     * @public
     * @param array/SQL $datas SQL语句或SQL拼接数组
     * @param bool $cache 缓存开关
     * @param int  $cacheTime 缓存时间（单位：秒）
     * @param array $param 扩展参数
     * +----------------------------------------------------------
     * @return array/mixed
     * +----------------------------------------------------------
     */
    public static function query($datas=null, $cache = false, $cacheTime = '',$param=array())
    {
        self::init($param);
        return self::$dbs->query($datas, $cache, $cacheTime,$param);
    }
    /**
     * +----------------------------------------------------------
     * 查询语句-连贯操作
     * +----------------------------------------------------------
     * @public
     * @param string $str 表名称
     * @param array $param 扩展参数
     * @call：
     * sdb::table("user")[->field(" id,username ")]
     * [->where(" deleted='0'")][->order(" id desc ")][->group(" type ")]
     * [->having("  ")]
     * [->limit(1)或->limit(0,5)]->query();
     * @实例:sdb::table("ntgis_z_user")->where(" deleted='0' ")->limit(1)->query();
     * +----------------------------------------------------------
     * @return object
     * +----------------------------------------------------------
     */
    public static function table($str,$param=array()){
        self::init($param);
        return self::$dbs->table($str,$param);
    }
    /**
     * +----------------------------------------------------------
     * 查询语句-限定条数
     * +----------------------------------------------------------
     * @public
     * @param array/SQL $datas SQL语句或SQL拼接数组
     * @param $limitStart 索引开始位置
     * @param $limitCount 条数
     * @param bool $cache 缓存开关
     * @param int  $cacheTime 缓存时间（单位：秒）
     * @param array $param 扩展参数
     * +----------------------------------------------------------
     * @return array/mixed
     * +----------------------------------------------------------
     */
    public static function queryLimit($datas,$limitStart,$limitCount=1,$cache = false, $cacheTime = '',$param=array())
    {
        self::init($param);
        return self::$dbs->queryLimit($datas,$limitStart,$limitCount,$cache, $cacheTime,$param);
    }
    /**
     * +----------------------------------------------------------
     * 插入记录-根据select结果
     * +----------------------------------------------------------
     * @public
     * @param array $datas SQL拼接数组
     * @param array $param 扩展参数
     * +----------------------------------------------------------
     * @return object
     * +----------------------------------------------------------
     */
    public static function insert($datas,$param=array()){
        self::init($param,true);
        return self::$dbs->insert($datas,$param);
    }
    /**
     * +----------------------------------------------------------
     * 插入记录
     * +----------------------------------------------------------
     * @public
     * @param array $datas SQL拼接数组
     * @param array $param 扩展参数
     * +----------------------------------------------------------
     * @return object
     * +----------------------------------------------------------
     */
    public static function insertSelect($datas,$param=array()){
        self::init($param,true);
        return self::$dbs->insertSelect($datas,$param);
    }

    /**
     * +----------------------------------------------------------
     * 更新记录
     * +----------------------------------------------------------
     * @public
     * @param array $datas SQL拼接数组
     * @param array $param 扩展参数
     * @e.g.
     *  单表更新
     *  $datas=array();
     *  $datas['table'] = "表名";
    $datas['fields'] = array(字段名=>值)或“字段=字段+1”;
    $datas['where'] = " id='1'"; //条件
    sdb::update($datas);
     * 关联更新：
     * sdb::update(array("sql"=>" test set del='1' where id=1"));
     * +----------------------------------------------------------
     * @return object
     * +----------------------------------------------------------
     */
    public static function update($datas,$param=array()){
        self::init($param,true);
        return self::$dbs->update($datas,$param);
    }
    /**
     * +----------------------------------------------------------
     * 分页查询方法
     * +----------------------------------------------------------
     * @public
     * @param string/array $mixParam  表名或变量数组集合
     * e.g array("tablename"=>"",//必选
     *  "fields"=>"*",//可选
     *  //"where"=>"",//可选
     *  //"pages"=>"",//可选
     *  //"pagesizes"=>"",//可选
     *  //"lkurl"=>"",//可选
     *  //"sort"=>""//可选
     *  //"fenye"=>"",//可选
     *  //"cache"=>true, //可选
     *  //"showTotal"=>true, //可选
     *  //"param"=>array() //可选
     * )
     * @param string $fields 字段
     * @param string $where 条件
     * @param string $pages 当前页
     * @param string $pagesizes 每页记录数
     * @param string $lkurl 翻页触发方法/URL
     * @param string $sort 排序
     * @param string $fenye 生成分页方法
     * @param string $fenye 生成分页方法
     * @param bool $cache 是否缓存
     * @param bool $showTotal 是否显示按页跳转
     * @param array $param 扩展参数
     * e.g $param['groupby']  ;$param['having']
     * +----------------------------------------------------------
     * @return array
     * +----------------------------------------------------------
     */
    public static function indexquerylist($mixParam, $fields = '*', $where='', $pages='', $pagesizes =
    '', $lkurl = '', $sort='', $fenye = 'fenye', $cache = true, $showTotal = true, $param =
                                          array())
    {

        $tablename=$mixParam;
        if(is_array($mixParam) && count($mixParam)>0){
            if(!isset($mixParam['tablename'])) return array();
            if(isNull($mixParam['tablename'])) return array();
            foreach($mixParam as $ks=>$vs){
                $$ks=$vs;
            }
        }
        self::init($param);

        return self::$dbs->indexquerylist(
            $tablename,
            $fields,
            $where,
            $pages,
            $pagesizes,
            $lkurl,
            $sort,
            $fenye,
            $cache,
            $showTotal,
            $param);
    }
    /**
     * +----------------------------------------------------------
     * 获取库表结构字段
     * +----------------------------------------------------------
     * @public
     * @param string $table 表名称
     * @param string $where 字段
     * +----------------------------------------------------------
     * @return array
     * +----------------------------------------------------------
     */
    public  static  function  getfields($table, $where = '',$param=array()){
        self::init($param);
        return self::$dbs->getfields($table, $where);
    }
    /**
     * +----------------------------------------------------------
     * 获取lob字段值
     * +----------------------------------------------------------
     * @public
     * @param string $table 表名称
     * @param string $where 字段
     * +----------------------------------------------------------
     * @return array
     * +----------------------------------------------------------
     */
    public  static  function getClob($t_name, $f_name, $id, $field = "id",$param=array()){
        self::init($param);
        return self::$dbs->getClob($t_name, $f_name, $id, $field,$param);
    }

    /**
     * +----------------------------------------------------------
     * 生成table Json 数据
     * +----------------------------------------------------------
     * @public
     * @param  string  $table 表
     * @param  string  $fields 字段
     * @param  string  $where 查询条件
     * @param  string  $colNames 配置字段
     * @param  array  $param 扩展参数
     * +----------------------------------------------------------
     * @return array
     * +----------------------------------------------------------
     */
    public static function jsonTable($table, $fields, $where, $colNames, $keyField,
                                     $param = array(),$parentClass = null, $paramExt =
                                     array())
    {
        if(isset($this)){
            $parentClass=$this;
        }
        $resArr = array();
        $pages = (intval(request('page')) == 0) ? 1 : request('page');
        $returnFun = getArrVal($param, "returnFun");
        $returnFunAll = getArrVal($param, "returnFunAll");
        $sort = getArrVal($param, "sort");
        if ($table != "" and $fields != "" and $colNames != "" and $keyField != "") {
            $res = sdb::indexquerylist($table, $fields, $where, $pages, $pagesize = '', $lkurl =
                '', $sort,$fenye = 'fenye', $cache = true, $showTotal = true, $paramExt);
            //输出结果
            $resArr['page'] = $pages; //当前页
            $resArr['total'] = $resArr['pages'] = $res['pagesnums']; //分页总数
            $resArr['records'] = $resArr['count'] = $res['count']; //记录总数
            $resArr['result'] = $resArr['pagesnums'] = $res['result']; //原记录集
            $rows = array();
            foreach ($res['result'] as $k => $v) {
                $cell = array();
                foreach ($colNames as $ks => $vs) {
                    $custom = getArrVal($vs, 'custom');
                    $reset = ($custom == 1) ? 1 : getArrVal($vs, 'reset');
                    if ($custom == 1) { //自定义【非实际字段值或组合】
                        $v[$vs['name']] = '';
                    }
                    if ($reset == 1) {
                        if(!empty($returnFun)) {
                            $resetVal = $parentClass->$returnFun(array(
                                "reset" => 1,
                                "field" => $vs['name'],
                                "fieldData" => $v));
                            if (getArrVal($resetVal, "flag") == 1) {
                                $cell[] = getArrVal($resetVal, "newData");
                            }
                        }else{
                            $cell[] = isset($v[$vs['name']])?$v[$vs['name']]:"";
                        }
                    } else {
                        $cell[] = $v[$vs['name']];
                    }

                }
                $rows[] = array("id" => $v[$keyField], "cell" => $cell);
            }
            $resArr['rows'] = $rows;
        }
        return $resArr;
    }
    /**
     * +----------------------------------------------------------
     * 获取库表结构字段类型
     * +----------------------------------------------------------
     * @public
     * @param array $param
     * +----------------------------------------------------------
     * @return array
     * +----------------------------------------------------------
     */
    public  static function fieldtype($param=array())
    {
        self::init($param);
        return self::$dbs->fieldtype($param);

    }
    /**
     * +----------------------------------------------------------
     * 获取库表结构字段加入库表
     * +----------------------------------------------------------
     * @public
     * @param string $table
     * @param array $param
     * +----------------------------------------------------------
     * @return void
     * +----------------------------------------------------------
     */
    public  static  function  tableFieldSaveToTab($table,$param=array()){
        self::init($param);
        return self::$dbs->tableFieldSaveToTab($table,$param=array());
    }
    /**
     * +----------------------------------------------------------
     * 库表入库
     * +----------------------------------------------------------
     * @public
     * @param string $table
     * +----------------------------------------------------------
     * @return void
     * +----------------------------------------------------------
     */
    public  static  function  tablesavetotab($param=array()){
        self::init($param);
        return self::$dbs->tablesavetotab($param);
    }
    /**
     * +----------------------------------------------------------
     * 预览数据
     * +----------------------------------------------------------
     * @public
     * @param string $table
     * +----------------------------------------------------------
     * @return array
     * +----------------------------------------------------------
     */
    public  static function  previewQuery($sql,$param=array()){
        self::init($param);
        return self::$dbs->previewQuery($sql,$param);
    }
    /**
     * +----------------------------------------------------------
     * 写入缓存
     * +----------------------------------------------------------
     * @public
     * @param string $cachename 名称
     * @param mixed $data 内容
     * @param int $time 缓存时间
     * +----------------------------------------------------------
     * @return void
     * +----------------------------------------------------------
     */
    public static function cacheSave($cachename, $data, $time = null)
    {
        if (g('cfg_cache_enable') == "Y") {
            if (g('cfg_memcache_enable') == "Y") {
                mcache::setmem($cachename, $data, $time);
            } else {
                filecache::writecache($cachename, $data);
            }
        }
    }
    /**
     * +----------------------------------------------------------
     * 读取缓存
     * +----------------------------------------------------------
     * @public
     * @param string $cachename 名称
     * @param int $time 缓存时间
     * @param array $param 扩展参数
     * +----------------------------------------------------------
     * @return void
     * +----------------------------------------------------------
     */
    public  static  function cacheGet($cachename,$param=array())
    {
        if (g('cfg_cache_enable')!= "Y") return  false;
        if (g('cfg_memcache_enable')  == "Y") {
            $res = mcache::getmem($cachename,$param);
        } else {
            $res = filecache::getcache($cachename, (isset($param['time'])?$param['time']:null));
        }
        return $res;
    }
    /**
     * +----------------------------------------------------------
     * 获取写入ID
     * +----------------------------------------------------------
     * @public
     * @param string $table 表名
     * @param mixed $createby 内容
     * @param int $sequence 缓存时间
     * @param string $ycode 识别码
     * +----------------------------------------------------------
     * @return int
     * +----------------------------------------------------------
     */
    public  static function  entityid($table, $createby = 0, $sequence = 0, $ycode =
    '',$param=array()){
        self::init($param);
        return self::$dbs->entityid($table, $createby, $sequence, $ycode,$param);
    }
    /**
     * +----------------------------------------------------------
     * 新增字段-表结构
     * +----------------------------------------------------------
     * @public
     * @param array $datas 表名
     * @param array $param 扩展参数
     * +----------------------------------------------------------
     * @return void
     * +----------------------------------------------------------
     */
    public static function addfields($datas,$param=array())
    {
        self::init($param);
        return self::$dbs->addfields($datas);
    }
    /**
     * +----------------------------------------------------------
     * 修改字段-表结构
     * +----------------------------------------------------------
     * @public
     * @param array $datas 表名
     * @param array $param 扩展参数
     * +----------------------------------------------------------
     * @return void
     * +----------------------------------------------------------
     */
    public static function modifyfields($datas,$param=array())
    {
        self::init($param);
        return self::$dbs->modifyfields($datas);
    }
    /**
     * +----------------------------------------------------------
     * 删除字段-表结构
     * +----------------------------------------------------------
     * @public
     * @param array $datas 表名
     * @param array $param 扩展参数
     * +----------------------------------------------------------
     * @return void
     * +----------------------------------------------------------
     */
    public static function delfields($datas,$param=array())
    {
        self::init($param);
        return self::$dbs->delfields($datas);
    }
    /**
     * +----------------------------------------------------------
     * 删除表结构
     * +----------------------------------------------------------
     * @public
     * @param string $table 表
     * @param array $param 扩展参数
     * +----------------------------------------------------------
     * @return void
     * +----------------------------------------------------------
     */
    public static function dropTable($table,$param=array())
    {
        self::init($param);
        return self::$dbs->dropTable($table,$param);
    }
    /**
     * +----------------------------------------------------------
     * 删除表数据（物理删除）
     * +----------------------------------------------------------
     * @public
     * @param array $datas
     * array("table"=>"",//表名
     * "where"=>""//条件
     * )
     * @param array $param 扩展参数
     * +----------------------------------------------------------
     * @return void
     * +----------------------------------------------------------
     */
    public static function delete($datas,$param=array())
    {
        self::init($param);
        return self::$dbs->delete($datas,$param);
    }
    /**
     * +----------------------------------------------------------
     * 创建表-表结构
     * +----------------------------------------------------------
     * @public
     * @param array $datas 表名
     * @param array $param 扩展参数
     * +----------------------------------------------------------
     * @return void
     * +----------------------------------------------------------
     */
    public static function addTable($datas,$param=array())
    {
        self::init($param);
        return self::$dbs->addTable($datas);
    }
    /**
     * +----------------------------------------------------------
     * 获取所有表名
     * +----------------------------------------------------------
     * @public
     * @param string $where 条件
     * @param array $param 扩展参数
     * +----------------------------------------------------------
     * @return void
     * +----------------------------------------------------------
     */
    public static function gettables($where = '',$param=array())
    {
        return self::$dbs->getTables($where,$param);
    }
    /**
     * +----------------------------------------------------------
     * 是否已存在字段表中
     * +----------------------------------------------------------
     * @public
     * @param string $table 表名
     * @param string $columnname 字段名
     * +----------------------------------------------------------
     * @return int
     * +----------------------------------------------------------
     */
    public static function isExsitDbField($table,$columnname)
    {
        return self::$dbs->isExsitDbField($table,$columnname);
    }
    /**
     * +----------------------------------------------------------
     * 根据32位识别码获取ID
     * +----------------------------------------------------------
     * @public
     * @param string $seqno 识别码
     * @param string $table 涉及表
     * @param string $keyField ID字段
     * +----------------------------------------------------------
     * @return int
     * +----------------------------------------------------------
     */
    public static function getseqid($seqno, $table = 'entity', $keyField = 'crmid')
    {
        $table = ($table == "") ? 'entity' : $table;
        $keyField = ($keyField == "") ? 'crmid' : $keyField;
        $sql = "select $keyField as id from __dbprefix__$table where seqno like '" . $seqno .
            "' ";
        $result = sdb::query($sql, false);
        return count($result) > 0 ? $result[0]['id'] : 0;
    }
    /**
     * +----------------------------------------------------------
     * 根据查询语句生成sql结构语句
     * 适用于联合查询条件的数据，单一查询条件或大数据量建议用sdb::dump($param=array());
     * +----------------------------------------------------------
     * @public
     * @param array $param 参数
     * array("sql"=>"查询语句",
     * "table"=>"主表",
     * "createTable"=>false,//是否生成创建表结构语句，默认false,
     * //"savePath"=>'',//生成文件路径，填写路径后直接生成文件
     * )
     * +----------------------------------------------------------
     * @return mixed  返回文件路径或sql字符串
     * +----------------------------------------------------------
     */
    public static function queryToSql($param=array())
    {
        if(!isset($param['sql']) || isNull($param['sql'])) return '';
        if(!isset($param['table']) || isNull($param['table'])) return '';
        self::init($param);
        return self::$dbs->queryToSql($param);
    }
    /**
     * +----------------------------------------------------------
     * 数据库导出为SQL文件
     * +----------------------------------------------------------
     * @public
     * @param array $param 参数
     * array(
     *  //"savePath"=>"",//保存路径
     *  //"table"=>"",//导出指定表，多个用空格分开
     *  //"fileName"=>"",//生成文件名,
     *  //"noCreate"=>false,//不写重新创建表的CREATE TABLE语句
     *  //"noData"=>false,//不生成数据插入语句
     *  //"dumpParam"=>''//其他扩展参数
     *
     * )
     * +----------------------------------------------------------
     * @return mixed
     * +----------------------------------------------------------
     */
    public static function dump($param=array())
    {
        self::init($param);
        return self::$dbs->dump($param);
    }
    /**
     * +----------------------------------------------------------
     * 导入sql文件
     * +----------------------------------------------------------
     * @public
     * @param array $param 扩展参数
     * * array(
     * "fileName"=>"sql文件路径"//路径，必选
     * //"h"=>"",//IP，可选
     * //"u"=>"",//用户名，可选
     * //"p"=>"",//密码，可选
     * //"port"=>"",//端口，可选
     * //"db"=>"",//数据库，可选
     * )
     * +----------------------------------------------------------
     * @return mixed
     * +----------------------------------------------------------
     */
    public static function import($param=array())
    {
        self::init($param);
        return self::$dbs->import($param);
    }
    /**
     * +----------------------------------------------------------
     * 日期格式化
     * +----------------------------------------------------------
     * @public
     * @param array $param 扩展参数
     * @param date/time $date 日期
     * @param string $format 格式化
     * %Y %m %d
     * +----------------------------------------------------------
     * @return mixed
     * +----------------------------------------------------------
     */
    public static function dateFormat($date,$format,$param=array())
    {
        self::init($param);
        return self::$dbs->dateFormat($date,$format,$param);
    }



}
