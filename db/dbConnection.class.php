<?php
if (!defined('JYSYSINC'))
    exit('JYSYS:No direct script access allowed');

/**
 * 数据库接口类
 *
 * @version        $Id: dbConnection.class.php 3 20:57 2012年5月13日 $
 * @package        Jysys.Libraries
 * @copyright      Copyright (c) 2012, REY, Inc.
 * @link           http://www.joyql.com
 */
abstract class dbConnection implements  dbInterface
{

    // 操作实例
    protected $statement = null;
    //表前缀
    protected  $prefixFrom='';//替换符
    protected  $prefixTo='';//替换值
    //当前查询语句
    protected  $sqlStr='';
    //模拟预处理语句
    protected $prepare=false;
    //数据库连接参数配置
    protected $config=array();
    //数据行数
    protected $rowCount=0;
    //影响行数
    protected $execCount=0;
    //错误消息
    protected $error      = '';
    // 数据库连接ID 支持多个连接
    protected $connID     = array();
    // 当前连接ID
    protected $_connID    = null;
    // 查询次数
    protected $queryCount   =   0;
    // 执行次数
    protected $executeCount =   0;
    //替换变量
    protected $replace =   array();
    // PDO连接参数
    protected $options = array(
        PDO::ATTR_CASE              =>  PDO::CASE_LOWER,
        PDO::ATTR_ERRMODE           =>  PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_ORACLE_NULLS      =>  PDO::NULL_NATURAL,
        PDO::ATTR_STRINGIFY_FETCHES =>  false,
        PDO::ATTR_PERSISTENT=>true,
        //PDO::ATTR_AUTOCOMMIT=>false
    );
    protected $replaceQuote = "\\'";
    /**
     * +----------------------------------------------------------
     * 构造方法
     * +----------------------------------------------------------
     * @public
     * @param array $config 配置信息
     * +----------------------------------------------------------
     * @return void
     * +----------------------------------------------------------
     */
    public function __construct($config=''){
        if(!empty($config)) {
            $this->config   =   array_merge($this->config,$config);
            if(is_array($this->config['params'])){
                $this->options  =   $this->config['params'] + $this->options;
            }
            if(isset($this->config['prefixFrom']))  $this->prefixFrom=$this->config['prefixFrom'] ;
            if(isset($this->config['prefixTo']))  $this->prefixTo=$this->config['prefixTo'] ;
            if(isset($this->config['replace']))  $this->replace=$this->config['replace'] ;
        }
    }
    /**
     * +----------------------------------------------------------
     * 连接数据库方法
     * +----------------------------------------------------------
     * @public
     * @param  array $config
     * @param  string/int $connKey
     * +----------------------------------------------------------
     * @return object
     * +----------------------------------------------------------
     */
    public function connect($config='',$connKey=0) {


        if ( !isset($this->connID[$connKey]) ) {
            if(empty($config))  $config =   $this->config;
            try{
                if(!$this->prepare){
                    $this->options[PDO::ATTR_EMULATE_PREPARES]  =   false;
                }
                $this->connID[$connKey]=$this->_connID = new PDO( $config['linkStr'], $config['username'], $config['password'],$this->options);
                if(isset($this->config['execDefault'])){
                    foreach($this->config['execDefault'] as $val){
                        $this->_connID->Exec($val);
                    }
                }
                if(isset($this->config['connParamSet'])){
                    foreach($this->config['connParamSet'] as $key=>$val){
                        $this->_connID->$key=$val;
                    }
                }
                $this->connID[$connKey]->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); //设置错误级别
                $this->connID[$connKey]->setAttribute(PDO::ATTR_CASE, PDO::CASE_LOWER); //强制列名是小写
            }catch (PDOException $e) {
                $this->error($e);
            }
        }
        return $this->connID[$connKey];
    }
    /**
     * 初始化数据库连接
     * @access protected
     * @param boolean $master 主服务器
     * @return void
     */
    public function initConnect($master=true) {
        if ( !$this->_connID ) $this->_connID = $this->connect();
    }
    /**
     * +----------------------------------------------------------
     * 执行SQL
     * +----------------------------------------------------------
     * @public
     * @param SQL $sql
     * +----------------------------------------------------------
     * @return array
     * +----------------------------------------------------------
     */
    public function execute($sql, $params = array(), $table = "", $lobField = 0){
        $this->initConnect(true);
        if ( !$this->_connID ) return false;
        $sql=$this->sqlReplace($sql);
        //释放前次的查询结果
        if ( !empty($this->statement) ) $this->free();
        $this->executeCount++;
        try {
            if($lobField > 0 || $this->prepare==1){
                return $this->executeParams($sql, $params, $table);
            }else{
                return $this->_connID->exec($sql);
            }
        }
        catch (PDOException $e) {
            $this->error($e);
        }
    }
    /**
     * +----------------------------------------------------------
     * 绑定参数方式执行SQL
     * +----------------------------------------------------------
     * @public
     * @param SQL $sql
     * +----------------------------------------------------------
     * @return array
     * +----------------------------------------------------------
     */
    private function executeParams($sql, $params, $table = "")
    {

        $this->statement = $this->_connID->prepare($sql);
        $result = false;
        foreach ($params as $ks => $vs) {
            $value = $params[$ks];
            $curF = str_replace(":", "_", $table . $ks);
            $flag = 0;
            if (in_array($curF, g('cfg_lobField'))) {
                $param = PDO::PARAM_STR;
                $flag = 1;
            } else {
                if (is_numeric($value))
                    $param = PDO::PARAM_INT;
                elseif (is_bool($value))
                    $param = PDO::PARAM_BOOL;
                elseif (is_null($value) || $value == '')
                    $param = PDO::PARAM_NULL;
                elseif (is_string($value))
                    $param = PDO::PARAM_STR;
                else
                    $param = PDO::PARAM_STR;
            }
            if ($param == PDO::PARAM_LOB || $flag == 1) {
                $this->statement->bindParam($ks, $params[$ks], PDO::PARAM_STR, strlen($params[$ks])); //,PDO::SQLSRV_ENCODING_BINARY
            } else {
                $this->statement->bindParam($ks, $params[$ks], $param); //,0, PDO::SQLSRV_ENCODING_BINARY
            }

        }
        if ($this->statement && $this->statement->execute()) {
            $result = $this->statement->rowCount();
        }
        return $result;
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
    public function insertSelect($datas,$param=array()){

        if(isset($datas['table']) && $datas['fields'] && $datas['select']){
            $sqlInsertData=$this->sqlInsertSelectData($datas['table'],$datas['fields'],$datas['select']);
            $this->sqlStr=$sqlInsertData;
            return $this->execute($this->sqlStr);
        }
        return false;
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
    public function insert($datas,$param=array()){

        if(isset($datas['table']) && $datas['fields']){
            $sqlInsertData=$this->sqlInsertData($datas['table'],$datas['fields']);
            $this->sqlStr=($this->prepare == 1)?$sqlInsertData['sql']:$sqlInsertData;
            $res=$this->execute($this->sqlStr,$this->prepare == 1 ? $sqlInsertData['realVal'] : array(),$datas['table'],$this->prepare == 1 ? $sqlInsertData['lobField']:0);
            if(isset($param['returnLastInsertId'])){
                $tmp=$this->query($param['returnLastInsertId']);
                if(count($tmp)>0) $res=$tmp[0]['id'];
            }
            return $res;
        }elseif(is_array($datas)){
            $sqlStr=$result=array();
            foreach($datas as $ks=>$vs){
                $sqlInsertData=$this->sqlInsertData($vs['table'],$vs['fields']);
                $sqlStr[]=$curSql=($this->prepare == 1)?$sqlInsertData['sql']:$sqlInsertData;
                $tmpres=$this->execute($curSql,$this->prepare == 1 ? $sqlInsertData['realVal'] : array(),$vs['table'],$this->prepare == 1 ? $sqlInsertData['lobField']:0);
                if(isset($param['returnLastInsertId'])){
                    $tmp=$this->query($param['returnLastInsertId']);
                    if(count($tmp)>0) $tmpres=$tmp[0]['id'];
                }
                $result[]=$tmpres;
            }
            $this->sqlStr=implode(";",$sqlStr);
            return $result;
        }
    }
    /**
     * +----------------------------------------------------------
     * 插入SQL-根据select结果
     * +----------------------------------------------------------
     * @public
     * @param string $table ;array $field;select sql $selectSql
     * +----------------------------------------------------------
     * @return string
     * +----------------------------------------------------------
     */
    private function sqlInsertSelectData($table, $field,$selectSql)
    {

        $query = 'INSERT INTO ' . $table . ' (' . implode(",",$field) . ') '.$selectSql;
        return $query;
    }
    /**
     * +----------------------------------------------------------
     * 插入SQL
     * +----------------------------------------------------------
     * @public
     * @param string $table ;array $data
     * +----------------------------------------------------------
     * @return string
     * +----------------------------------------------------------
     */
    private function sqlInsertData($table, $data)
    {

        $sql_fields = '';
        $sql_data = '';
        $prepareVal = $realVal = array();
        $lobField = 0;
        foreach ($data as $walk => $cur) {
            $sql_fields .= ($sql_fields ? ',' : '') . "`" . $walk . "`";
            if ($this->prepare == 1 && $lobField > 0) {
                $realVal[":" . $walk] = $this->sql_quote($this->sqlReplace($cur,$lobField), $lobField);
                $prepareVal[] = ":" . $walk;
            }
            $sql_data .= ($sql_data ? ',' : '') . $this->sql_quote($cur, $lobField);

        }
        if($this->prepare == 1){
            foreach ($data as $walk => $cur) {
                $curF = strtolower(str_ireplace($this->prefixFrom,"", $table) . "_" . $walk);
                if (in_array($curF, $GLOBALS['cfg_lobField'])) {
                    $lobField++;
                }
            }
        }
        $query = 'INSERT INTO ' . $table . ' (' . $sql_fields . ') VALUES (' . ($this->
            prepare == 1 && $lobField > 0 ? implode(",", $prepareVal) : $sql_data) . ')';
        return $this->prepare == 1 ? array(
            "sql" => $query,
            "realVal" => $realVal,
            "lobField" => $lobField,
            "table" => $table) : $query;
    }
    /**
     * +----------------------------------------------------------
     * 更新记录
     * +----------------------------------------------------------
     * @public
     * @param SQL $datas SQL拼接数组
     * @param array $param 扩展参数
     * +----------------------------------------------------------
     * @return object
     * +----------------------------------------------------------
     */
    public function update($datas,$param=array()){
        if(isset($datas['sql']) && !isNull($datas['sql']) ){
            $this->sqlStr=" update ".$datas['sql'];
            return $this->execute($this->sqlStr);
        }
        if(isset($datas['table']) && $datas['fields']){
            $sqlUpdateData=$this->sqlUpdateData($datas['table'],$datas['fields'],$datas['where']);
            $this->sqlStr=($this->prepare == 1)?$sqlUpdateData['sql']:$sqlUpdateData;
            return $this->execute($this->sqlStr);
        }elseif(is_array($datas)){
            $sqlStr=$res=array();
            foreach($datas as $ks=>$vs){
                $sqlUpdateData=$this->sqlUpdateData($vs['table'],$vs['fields'],$vs['where']);
                $curSql=($this->prepare == 1)?$sqlUpdateData['sql']:$sqlUpdateData;
                $sqlStr[]=$curSql;
                $res[]=$this->execute($curSql);
            }
            $this->sqlStr=implode(";",$sqlStr);
            return $res;
        }
    }
    /**
     * +----------------------------------------------------------
     * 更新SQL 转换
     * +----------------------------------------------------------
     * @public
     * @param string $table ;array $data
     * +----------------------------------------------------------
     * @return string
     * +----------------------------------------------------------
     */
    private function sqlUpdateData($table, $data,$where='')
    {

        $fields = '';
        if (!$table || (is_array($data) && count($data)==0) || (!is_array($data) && isNull($data))  ) return '';
        if(isNull($where) ) return '';
        $realVal = array();
        $lobField = 0;
        if(!is_array($data) && !isNull($data)){
            $fields=$data;
        }else{
            foreach ($data as $key => $val) {
                $curF = strtolower(str_ireplace($this->prefixFrom,'', $table) . "_" . $key);
                if (in_array($curF, g('cfg_lobField'))) {
                    $lobField++;
                }
            }
            foreach ($data as $key => $val) {
                $fields = $fields . "" . ("`$key`") . "=" . ($this->prepare == 1 && $lobField > 0 ? ":" . strtolower($key) :
                        "'" . $val . "'") . ",";
                if ($this->prepare == 1 && $lobField > 0) {
                    $realVal[':' . strtolower($key)] = $this->sql_quote($val, $lobField);
                }
            }
            $fields = substr($fields, 0, -1);
        }
        $query = 'update ' . $table . ' set ' . $fields . ' where ' . $where;
        return $this->prepare == 1 ? array(
            "sql" => $query,
            "realVal" => $realVal,
            "lobField" => $lobField,
            "table" => $table) : $query;
    }
    /**
     * +----------------------------------------------------------
     * 删除表记录
     * +----------------------------------------------------------
     * @public
     * @param array $datas SQL拼接数组
     * @param array $param 扩展参数
     * +----------------------------------------------------------
     * @return object
     * +----------------------------------------------------------
     */
    public function delete($datas = array(),$param=array()){
        if(!isset($datas['table'])) return '';
        $this->sqlStr = 'delete from ' . $datas['table'].(isset($datas['where'])?' where '.$datas['where']:'');
        return $this->execute($this->sqlStr);
    }
    /**
     * +----------------------------------------------------------
     * 删除表结构
     * +----------------------------------------------------------
     * @public
     * @param string $table 表
     * @param array $param 扩展参数
     * +----------------------------------------------------------
     * @return object
     * +----------------------------------------------------------
     */
    public function dropTable($table,$param=array()){
        if(isNull($table) && !isset($param['sql']) ) return '';
        $this->sqlStr = isset($param['sql'])?$param['sql']:' drop table '.$table;
        return $this->execute($this->sqlStr);
    }
    /**
     * +----------------------------------------------------------
     * 新增字段
     * +----------------------------------------------------------
     * @public
     * @param array/SQL $datas SQL语句或SQL拼接数组
     * +----------------------------------------------------------
     * @return void
     * +----------------------------------------------------------
     */
    public  function  addField($datas){
        if(!isset($datas['sql'])) return '';
        $this->sqlStr = $datas['sql'];
        return $this->execute($this->sqlStr);
    }
    /**
     * +----------------------------------------------------------
     * 修改字段
     * +----------------------------------------------------------
     * @public
     * @param $datas SQL拼接数组
     * +----------------------------------------------------------
     * @return object
     * +----------------------------------------------------------
     */
    public function modifyField($datas){
        if(!isset($datas['sql'])) return '';
        $this->sqlStr = $datas['sql'];
        return $this->execute($this->sqlStr);
    }
    /**
     * +----------------------------------------------------------
     * 删除字段
     * +----------------------------------------------------------
     * @public
     * @param $datas SQL拼接数组
     * +----------------------------------------------------------
     * @return object
     * +----------------------------------------------------------
     */
    public function deleteField($datas){
        if(!isset($datas['sql'])) return '';
        $this->sqlStr = $datas['sql'];
        return $this->execute($this->sqlStr);
    }
    /**
     * +----------------------------------------------------------
     * 添加表
     * +----------------------------------------------------------
     * @public
     * @param $datas SQL拼接数组
     * +----------------------------------------------------------
     * @return object
     * +----------------------------------------------------------
     */
    public function addTable($datas){
        if(!isset($datas['sql'])) return '';
        $this->sqlStr = $datas['sql'];
        return $this->execute($this->sqlStr);
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
    public function query($datas='', $cache = false, $cacheTime = '',$param=array()){
        if(isNull($datas)) return array();
        $this->initConnect(true);
        if(!isset($this->_connID)) return array();
        $cfg_puccache_time = ($cacheTime != "") ? $cacheTime : g('cfg_puccache_time');
        if (is_array($datas)) {
            $sql = 'select '.$datas['field'].' from '.$datas['table'].' '.(!isNull($datas['where'])?' where '.$datas['where']:'');
        } else {
            $sql = $datas;
        }
        if ($cache == true) {
            $result = $this->cacheGet(md5($sql), $cfg_puccache_time,array("module"=>"dbConnection>>query"));
            if ($result == false) {
                $result=$this->queryExec($sql);
                $this->cacheSave(md5($sql), $result, $cfg_puccache_time);
            }
        } else {
            $result=$this->queryExec($sql);
        }
        return $result;
    }
    /**
     * +----------------------------------------------------------
     * 通用查询语句执行
     * +----------------------------------------------------------
     * @public
     * @param  SQL $sql
     * +----------------------------------------------------------
     * @return array
     * +----------------------------------------------------------
     */
    private function queryExec($sql){
        if(isNull($sql))  return array();
        $sql=$this->sqlReplace($sql);
        $this->sqlStr=$sql;
        $this->statement=$this->_connID->prepare($sql);
        try {
            $this->statement->execute(array());
        }
        catch (PDOException $e) {
            $this->error($e);
        }
        if (!$this->statement) return array();
        try {
            $this->statement->setFetchMode(PDO::FETCH_ASSOC);
            return  $this->statement->fetchAll();
        }catch (PDOException $e) {
            $this->error($e);
        }
    }
    /**
     * +----------------------------------------------------------
     * 分页查询方法
     * +----------------------------------------------------------
     * @public
     * @param string $sqlCount 统计行数语句
     * @param string $sqlList 查询结果语句
     * @param array $param 扩展参数
     * +----------------------------------------------------------
     * @return array
     * +----------------------------------------------------------
     */
    public function queryPage($sqlCount,$sqlList,$param =array())
    {
        $countRes=$this->query($sqlCount,$param['cache']);
        $result=$this->query($sqlList,$param['cache']);
        $groupby = isset($param['groupby']) && !empty($param['groupby']) ? true:false;
        $rowNum = $groupby ? count($countRes) : $countRes[0]['nums'];
        $pageCount =isset($param['pagesize']) && intval($param['pagesize'])>0?ceil($rowNum / $param['pagesize']):1;
        return array("rowNum"=>$rowNum,"result"=>$result,"pageCount"=>$pageCount);
    }
    /**
     * +----------------------------------------------------------
     * 写入缓存
     * +----------------------------------------------------------
     * @public
     * @param string $cachename 缓存识别ID
     * @param mixed $data
     * @param int  $time 缓存时间（单位：秒）
     * +----------------------------------------------------------
     * @return void
     * +----------------------------------------------------------
     */
    public function cacheSave($cachename, $data, $time = null)
    {
        if (g('cfg_cache_enable')!= "Y") return  false;
        if (g('cfg_memcache_enable') == "Y") {
            mcache::setmem($cachename, $data, $time);
        } else {
            filecache::writecache($cachename, $data);
        }
    }
    /**
     * +----------------------------------------------------------
     * 读取缓存
     * +----------------------------------------------------------
     * @public
     * @param string $cachename 缓存识别ID
     * @param int  $cachetime 缓存时间（单位：秒）
     * @param array $param 扩展参数
     * +----------------------------------------------------------
     * @return mixed
     * +----------------------------------------------------------
     */
    public function cacheGet($cachename, $cachetime,$param=array())
    {
        if (g('cfg_cache_enable')!= "Y") return  false;
        if (g('cfg_memcache_enable')  == "Y") {
            $res = mcache::getmem($cachename,$param);
        } else {
            $res = filecache::getcache($cachename, $cachetime);
        }
        return $res;
    }
    /**
     * +----------------------------------------------------------
     * 读取缓存
     * +----------------------------------------------------------
     * @public
     * @param string $cachename 缓存识别ID
     * @param int  $cachetime 缓存时间（单位：秒）
     * +----------------------------------------------------------
     * @return mixed
     * +----------------------------------------------------------
     */
    function getClob($t_name, $f_name, $id, $field = "id",$param=array())
    {

        if(isNull($t_name) || isNull($f_name) || isNull($id) ) return '';
        $this->initConnect(true);
        $content = "";
        $num = 0; //clob字段长度
        $this->statement = $this->_connID->prepare("select length($f_name) as num from $t_name where $field='$id' ");
        if ($this->statement->execute()) { //zjh为查询的条件
            $row = $this->statement->fetch();
            $num = $row['num'];
        }
        $start = 1; //初始化开始位置
        $len = 1000; //截取长度
        while ($start <= $num) {
            $ret = '';
            $sql = "begin get_clob(?,?,?,?,?,?); end;";
            $stmt = $this->_connID->prepare($sql);
            $stmt->bindParam(1, $t_name, PDO::PARAM_STR, 100);
            $stmt->bindParam(2, $f_name, PDO::PARAM_STR, 100);
            $stmt->bindParam(3, $id, PDO::PARAM_STR, 100);
            $stmt->bindParam(4, $start, PDO::PARAM_STR, 100);
            $stmt->bindParam(5, $len, PDO::PARAM_STR, 100);
            $stmt->bindParam(6, $ret, PDO::PARAM_STR, 5000);
            $stmt->execute();
            $content .= $ret;
            $start = $start + $len;
        }
        return $content;
    }
    /**
     * +----------------------------------------------------------
     * 开始事务
     * +----------------------------------------------------------
     * @public
     * +----------------------------------------------------------
     * @return void
     * +----------------------------------------------------------
     */
    public function beginTransaction(){
        $this->initConnect(true);
        if ( !$this->_connID ) return false;
        $this->_connID->beginTransaction();
        return ;
    }

    /**
     * +----------------------------------------------------------
     * 提交事务
     * +----------------------------------------------------------
     * @public
     * +----------------------------------------------------------
     * @return void
     * +----------------------------------------------------------
     */
    public function commit(){
        $result = $this->_connID->commit();
        if(!$result){
            return false;
        }
        return true;
    }

    /**
     * +----------------------------------------------------------
     * 事务回滚
     * +----------------------------------------------------------
     * @public
     * +----------------------------------------------------------
     * @return void
     * +----------------------------------------------------------
     */
    public function rollBack(){
        $result = $this->_connID->rollback();
        if(!$result){
            return false;
        }
        return true;
    }

    /**
     * +----------------------------------------------------------
     * SQL替换
     * +----------------------------------------------------------
     * @public
     * @param string $sql
     * +----------------------------------------------------------
     * @return strings
     * +----------------------------------------------------------
     */
    public function sqlReplace($sql, $lobField = 0){
        $this->replace[$this->prefixFrom]=$this->prefixTo;
        $sql=strtr($sql, $this->replace);
        return $sql;
    }
    /**
     * 释放查询结果
     * @access public
     */
    public function free() {
        $this->statement = null;
    }
    /**
     * +----------------------------------------------------------
     * SQL过滤
     * +----------------------------------------------------------
     * @public
     * @param mixed $s 值； $lobField 大字段标识 ;
     * +----------------------------------------------------------
     * @return strings
     * +----------------------------------------------------------
     */
    public function sql_quote($s,$lobField = 0)
    {
        $s = str_replace('\\"', '"', $s);
        if ($this->replaceQuote == "\\'" || ini_get('magic_quotes_sybase'))
            return $this->prepare == 1 && $lobField > 0 ? $s : "'$s'";
        else {
            $s = str_replace('\\\\', '\\', $s);
            return $this->prepare == 1 && $lobField > 0 ? $s : "'" . str_replace("\\'", $this->
                replaceQuote, $s) . "'";
        }
    }
    /**
     * +----------------------------------------------------------
     * 错误输出
     * +----------------------------------------------------------
     * @public
     * @param  PDOException $e
     * +----------------------------------------------------------
     * @return print
     * +----------------------------------------------------------
     */
    private function error($e){

        $code=$e->getCode();
        $msg=$e->getMessage().$e->getTraceAsString();
        $file=$e->getFile();
        $line=$e->getLine();
        //myHandler($code, $msg, $file, $line);
        exit;
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
    public   function  previewQuery($sql,$param=array()){
        $result =$this->query($sql, false);
        $fieldCfg = array();
        if (count($result) > 0) {
            foreach ($result[0] as $k => $v) {
                $fieldCfg[] = $k;
            }
        }
        return array("fieldCfg" => $fieldCfg, "listData" => $result);
    }
    /**
     * +----------------------------------------------------------
     * 获取写入ID
     * +----------------------------------------------------------
     * @public
     * @param string $table 表名
     * @param mixed $createby 内容
     * @param int $sequence 排序
     * @param string $ycode 识别码
     * +----------------------------------------------------------
     * @return int
     * +----------------------------------------------------------
     */
    public function entityid($table, $createby = 0, $sequence = 0, $ycode = '')
    {
        if (isNull($table)) return 0;
        $seqno = ($ycode != "") ? $ycode : strings::keyGen();
        $datas = array();
        $datas['table'] = $this->prefixFrom."z_entity";
        $datas['fields'] = array(
            "seqno" => $seqno,
            "tablename" => $table,
            "ctime" => "__curtime__",
            "createby" => $createby,
            "sequence" => $sequence);
        $this->insert($datas);
        return intval($this->getseqid($seqno));
    }
    /**
     * +----------------------------------------------------------
     * 获取写入ID
     * +----------------------------------------------------------
     * @public
     * @param string $seqno 识别码
     * @param string $table 表名
     * @param string $keyField ID字段列
     * +----------------------------------------------------------
     * @return int
     * +----------------------------------------------------------
     */
    public function getseqid($seqno, $table = '', $keyField = 'crmid')
    {
        $table = ($table == "") ? SETTING_TABLE_EXT.'entity' : $table;
        $keyField = ($keyField == "") ? 'crmid' : $keyField;
        $sql = "select $keyField as id from ".$this->prefixFrom."$table where seqno like '" . $seqno ."' ";
        $result = $this->query($sql, false);
        return count($result) > 0 ? $result[0]['id'] : 0;
    }
    /**
     * +----------------------------------------------------------
     * 关闭数据库
     * +----------------------------------------------------------
     * @public
     * @param
     * +----------------------------------------------------------
     * @return void
     * +----------------------------------------------------------
     */
    public function close() {
        $this->_connID = null;
    }
    /**
     * 析构方法
     * @access public
     */
    public function __destruct() {
        // 释放查询
        if ($this->statement){
            $this->free();
        }
        // 关闭连接
        $this->close();
    }

}
