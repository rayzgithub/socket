<?php if (!defined('JYSYSINC'))
    exit('JYSYS:No direct script access allowed');
/**
 * 数据库接口类
 *
 * @version        $Id: dbConnectionMysql.class.php 3 20:57 2012年5月13日 $
 * @package        Jysys.Libraries
 * @copyright      Copyright (c) 2012, REY, Inc.
 * @link           http://www.joyql.com
 */
 class dbConnectionMysql  extends  dbConnection
{
     //数据库连接参数配置
     protected $config=array(
         "params"=>"",
         "prefixFrom"=>"",
         "prefixTo"=>"",
         "linkStr"=>"",
         "username"=>"",
         "password"=>"",
         "execDefault"=>array(),
         "connParamSet"=>array()
     );
     //当前查询语句
     protected  $sqlStr='';
     //sql 拼接参数
     protected  $sqlParam=array();
     //sql 类型
     protected  $sqlExecType='';
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
    public function __construct($dbConfig){
        $this->config   =   array_merge($this->config,$dbConfig);
        $this->config['linkStr']='mysql:host=' . $this->config['host'] . ';dbname=' . $this->config['dbName'] . ";port=" .$this->config['port'];
        $this->config['execDefault']=array(
            "SET NAMES utf8",
            "SET SESSION wait_timeout = 28800",
            "SET SESSION interactive_timeout = 28800"
        );
        $this->config['connParamSet']=array(
        );
        $this->config['replace']=$this->sqlReplaceParam();
        $params=array();
        $params[PDO::ATTR_ERRMODE]=PDO::ERRMODE_EXCEPTION;
        $params[PDO::ATTR_CASE]=PDO::CASE_LOWER;
        $params[PDO::ATTR_PERSISTENT]=true;
        $this->config['params']=$params;
        parent::__construct($this->config);
    }
     /**
      * +----------------------------------------------------------
      * 替换参数
      * +----------------------------------------------------------
      * @public
      * @param
      * +----------------------------------------------------------
      * @return array
      * +----------------------------------------------------------
      */
     public  function  sqlReplaceParam(){
            return array(
                "'__curtime__'"=>"'" . date("Y-m-d H:i:s") . "'",
                "'__curdate__'"=>"'" . date("Y-m-d") . "'",
                "to_char("=>"DATE_FORMAT(",
                "'yyyy-mm-dd')"=>"'%Y-%m-%d')",
                "'yyyy-mm')"=>"'%Y-%m')",
                "{dbprefix}"=>$this->config['prefixTo']
            );
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
         return parent::query($datas,$cache, $cacheTime,$param);
     }
     /**
      * +----------------------------------------------------------
      * 分页查询方法
      * +----------------------------------------------------------
      * @public
      * @param string $tablename 表
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
     public function indexquerylist($tablename, $fields = '*', $where, $pages, $pagesizes =
     '', $lkurl = '', $sort, $fenye = 'fenyejs', $cache = true, $showTotal = true, $param =
                                    array())
     {

         //当前页数
         $pages = (intval($pages) > 0) ? intval($pages) : intval(request('page'));
         $pages = ($pages == 0) ? 1 : $pages;
         //每页行数
         $pagesize = ($pagesizes > 0) ? $pagesizes : request("rows", 1); //每页行数
         $pagesize = (intval($pagesize) == 0) ? 15 : $pagesize;
         //分页起始
         $pagetListStart=($pages - 1) * $pagesize;
         $pageListNum=$pagesize;
         //group by
         $groupby = isset($param['groupby']) && !empty($param['groupby']) ? " group by " .
             $param['groupby'] : "";
         $having = isset($param['having']) && !empty($param['having']) ? " having " . $param['having'] :
             "";
         //order by
         $orderBy="";
         if ($sort != "") {
             $orderBy = " order by  " . $sort;
         } else {
             $sidx = request("sidx");
             $sord = request("sord");
             if ($sidx != "") {
                 $orderBy = " order by  " . $sidx . " $sord";
             }
         }
         //统计行数语句
         $sqlCount='select count(1) as nums from '.$tablename;
         $sqlCount.=' '.$where;
         $sqlCount.=' '.$groupby;
         $sqlCount.=' '.$having;
         //查询结果语句
         $sqlList=' select '.$fields.' from '.$tablename;
         $sqlList.=' '.$where;
         $sqlList.=' '.$groupby;
         $sqlList.=' '.$having;
         $sqlList.=' '.$orderBy;
         if(isset($param['returnAll']) && $param['returnAll']==1 ){
             $sqlList.=' limit '.$pageListNum.' ';
         }else{
             $sqlList.=' limit '.$pagetListStart.','.$pageListNum.' ';
         }
         $paramExt=$param;
         $paramExt['pagesize']=$pagesize;
         $paramExt['cache']=$cache;
         $_queryPage=parent::queryPage($sqlCount,$sqlList,$paramExt);
         //生成分页元素
         $pagemsgs = $fenye($pages, $_queryPage['pageCount'], $lkurl, 9, $_queryPage['rowNum']);
         $Totalnum=$_queryPage['pageCount'];
         $pglst =$strs=$pgmsgGto= '';
         if ($Totalnum) {
             $gotoEid = md5($lkurl);
             $pgmsgGto = '第 <input name="pageto' . $gotoEid .
                 '" value="1" size="1" style="height:auto" type="text" id="pageto' . $gotoEid . '" size="4" /> 页
            <input type="button" style="height:auto"  onclick=\'' . $lkurl . ',$("#pageto' . $gotoEid .
                 '").val())\' name="button" id="button" value="跳转" />';
         }
         if($fenye != 'bootstrapFenye'){
          $strs = $strs . '<tr><td colspan="50" height="32"  >';
          $strs = $strs . '<table class="infolist pagestab" width="100%" border="0" cellspacing="0" cellpadding="0"><tr><td nowrap="nowrap" width="12%">总数：' .
              $_queryPage['rowNum'] . '&nbsp;&nbsp;共' . $_queryPage['pageCount'] . '页&nbsp;&nbsp;' . $pglst . '</td>';
          $strs = $strs . '<td width="68%"><div id="page">' . $pagemsgs .
              '</div></td><td width="20%">' . $pgmsgGto . '</td></tr></table>';
          $strs = $strs . '</td></tr>';
        }else if(!empty($pagemsgs)){
          $strs .= "<ul class='pagination pull-left' style='margin:0;'>{$pagemsgs}</ul>";
          $strs .= "<div class='pull-left' style='margin:10px 0;'>总数:{$_queryPage['rowNum']},共{$_queryPage['pageCount']}页</div>";
          $strs .= "<div class='pull-left' style='margin:10px 0;'>{$pgmsgGto}</div>";
        }else if (empty($pagemsgs)) {
          $strs = '';
        }

         $result = array();
         $result['result'] = $_queryPage['result'];
         $result['pages'] = $strs;
         $result['pagesnums'] = $_queryPage['pageCount'];
         $result['pagesdiv'] = '<div id="page">' . $pagemsgs . '</div>';
         $result['pagesli'] = '' . $pagemsgs . '';
         $result['count'] = $_queryPage['rowNum'];
         return $result;
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
     public  function query($datas=null, $cache = false, $cacheTime = '',$param=array())
     {


         if(isNull($datas)){
             $this->sqlExecType="query";
             if(isNull($this->sqlParam['table'])) return array();
             $sql="select ".(isset($this->sqlParam['field'])?$this->sqlParam['field']:"*")." from  ".$this->sqlParam['table'];
             if(isset($this->sqlParam['where']) && $this->sqlParam['where']!="") $sql.=" where  ".$this->sqlParam['where'];
             if(isset($this->sqlParam['group']) && $this->sqlParam['group']!="") $sql.=" group by ".$this->sqlParam['group'];
             if(isset($this->sqlParam['group']) && $this->sqlParam['group']!="" && isset($this->sqlParam['having']) && $this->sqlParam['having']!="" ) $sql.=" having ".$this->sqlParam['having'];
             if(isset($this->sqlParam['order']) && $this->sqlParam['order']!="") $sql.=" order by  ".$this->sqlParam['order'];
             if(isset($this->sqlParam['limit']) && $this->sqlParam['limit']!="") $sql.="  ".$this->sqlParam['limit'];
             return parent::query($sql, $cache, $cacheTime,$param);
         }

         return parent::query($datas, $cache, $cacheTime,$param);
     }
     /**
      * +----------------------------------------------------------
      * 更新语句-连贯操作
      * +----------------------------------------------------------
      * @public
      * @param array/SQL $datas SQL语句或SQL拼接数组
      * @param array $param 扩展参数
      * +----------------------------------------------------------
      * @return array/mixed
      * +----------------------------------------------------------
      */
     public  function update($datas=null,$param=array())
     {

         if(isNull($datas)){
             $this->sqlExecType="update";
             if(isNull($this->sqlParam['table'])) return false;
             if(!isset($this->sqlParam['set']) ||  isNull($this->sqlParam['set'])) return false;
             $datas['sql']=$this->sqlParam['table']." set ".$this->sqlParam['set']." ".(!isNull($this->sqlParam['where'])?" where ".$this->sqlParam['where']:"");
             return parent::update($datas,$param);
         }
         return parent::update($datas,$param);
     }


       /**   
       * +----------------------------------------------------------   
      * 插入记录    
      * +----------------------------------------------------------   
      * @public   
      * @param array/SQL $datas SQL语句或SQL拼接数组   
      * @param array $param 扩展参数    
      * +----------------------------------------------------------   
      * @return array/mixed   
      * +----------------------------------------------------------   
      */    
    public function insert($datas=null,$param=array())    
    {   
      $param['returnLastInsertId']=" select LAST_INSERT_ID() as id";    
      return parent::insert($datas,$param);   
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
     public function getfields($table, $where = '')
     {
         $sql = "SELECT
            information_schema.`COLUMNS`.TABLE_NAME,
            information_schema.`COLUMNS`.COLUMN_NAME as field_name,
            information_schema.`COLUMNS`.DATA_TYPE  as field_type,
            information_schema.`COLUMNS`.COLUMN_TYPE as column_type,
            information_schema.`COLUMNS`.COLUMN_KEY as field_iskey,
            information_schema.`COLUMNS`.COLUMN_COMMENT as field_comment,
            information_schema.`COLUMNS`.CHARACTER_MAXIMUM_LENGTH as field_length,
            information_schema.`COLUMNS`.CHARACTER_OCTET_LENGTH,
            information_schema.`COLUMNS`.IS_NULLABLE as field_isnull
            from information_schema.columns
            where table_schema = '" . $this->config['dbName'] . "' and table_name ='" . $this->config['prefixTo'].$table .
             "' $where";
         return sdb::query($sql, false);
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
     public  function fieldtype($param=array())
     {

         $res = array(
             "varchar",
             "char",
             "text",
             "date",
             "datetime",
             "int",
             "tinyint",
             "bigint",
             "float",
             "double",
             "decimal",
             //"time",
             //"year",
             "timestamp",
             "tinytext",
             "longtext"
             );
         return $res;

     }
     /**
      * +----------------------------------------------------------
      * 是否已存在字段表中
      * +----------------------------------------------------------
      * @public
      * @param string $table 表名
      * @param string $columnname 字段名
      * +----------------------------------------------------------
      * @return void
      * +----------------------------------------------------------
      */
     public  function  isExsitDbField($table,$columnname){
         if(isNull($table) || isNull($columnname)) return '';
         $sql = "select count(*) as nums from " . $this->config['prefixTo'] .
             SETTING_TABLE_EXT."field where tablename like '" . $table . "' and columnname like '" . $columnname ."' and deleted='0' ";
         $result = sdb::query($sql, false);
         return  $result[0]['nums'] > 0 ? 1 : 0;
     }
     /**
      * +----------------------------------------------------------
      * 获取库表结构字段加入库表
      * +----------------------------------------------------------
      * @public
      * @param string $table 表名
      * @param array $param 扩展参数
      * +----------------------------------------------------------
      * @return void
      * +----------------------------------------------------------
      */
     public  function  tableFieldSaveToTab($table,$param=array()){
         if(isNull($table)) return '';
         $result = '';
         $sqlw = ($table != '') ? "where tablename like '" . $table . "'" : "";
         $tablesarr = sdb::query("select tablename,moduleid,tableid from ".$this->config['prefixTo'].SETTING_TABLE_EXT."table " . $sqlw);
         $hideField = array(
             "createby",
             "modifyby",
             "ctime",
             "mtime",
             "vtime",
             "vtime",
             "opcode",
             "seqno",
             "deleted",
             "no");
         if (count($tablesarr) > 0) {
             foreach ($tablesarr as $key => $val) {
                 $tablename = $val['tablename'];
                 $fieldarr = $this->getfields($val['tablename']);
                 foreach ($fieldarr as $keys => $vals) {
                     $fieldexistid = 0;
                     $fieldexistid = $this->isExsitDbField($tablename, $vals['field_name']);
                     $column_type = $vals['column_type'];
                     $column_type = str_replace($vals['field_type'] . "(", "", $column_type);
                     $column_type = str_replace(")", "", $column_type);
                     $field_length = $column_type;
                     $field_isnull = ($vals['field_isnull'] == "NO") ? 1 : 0;
                     $field_comment = ($vals['field_comment'] != "") ? $vals['field_comment'] : "未定义";
                     if ($fieldexistid == 0) {
                         $fieldNum = in_array($vals['field_name'], $hideField) ? $keys + 100 :
                             $keys + 1;
                         $seqno = strings::keyGen();
                         $isid = ($vals['field_iskey'] == "PRI") ? 1 : 0;
                         $dataArr = array(
                             "seqno" => $seqno,
                             "id" => $seqno,
                             "moduleid" => $val['moduleid'],
                             "tableid" => $val['tableid'],
                             "tablename" => $tablename,
                             "columntype" => $vals['column_type'],
                             "columnname" => $vals['field_name'],
                             "uitype" => $vals['field_type'],
                             "fieldlabel" => $field_comment,
                             "readonly" => "0",
                             "selected" => "0",
                             "masseditable" => "0",
                             "maximumlength" => $field_length,
                             "sequence" => $fieldNum,
                             "bsequence" => $fieldNum,
                             "displaytype" => "0",
                             "ishidden" => "0",
                             "isid" => $isid,
                             "isedit" => "0",
                             "ctime" => date("Y-m-d H:i:s"),
                             "createby" => sessions::get('jysys_userid'),
                             "isselectid" => $field_isnull);
                         if ($vals['field_name'] == "deleted") {
                             $dataArr['typeofdata'] = "checkbox";
                             $dataArr['info_type'] = "checkbox";
                             $dataArr['checkboxval'] = 1;
                         } else {
                             $dataArr['typeofdata'] = "text";
                             $dataArr['info_type'] = "text";
                         }
                         if ($isid == 1 or $vals['field_name'] == "id") {
                             $dataArr['typeofdata'] = "id";
                             $dataArr['info_type'] = "key";
                         }
                         if (in_array(strtolower($vals['field_name']), $hideField)) {
                             $dataArr['displaytype'] = 1;
                         }
                         if ($vals['field_type'] == "date") {
                             $dataArr['typeofdata'] = "date";
                             $dataArr['info_type'] = "date";
                         }
                         $datas['table'] = $this->config['prefixTo'].SETTING_TABLE_EXT."field";
                         $datas['fields'] = $dataArr;
                         sdb::insert($datas);
                     }
                 }
             }
         }
         return $result;
     }
     /**
      * +----------------------------------------------------------
      * 获取库所有表
      * +----------------------------------------------------------
      * @public
      * @param string $where 条件
      * @param array $param 扩展参数
      * +----------------------------------------------------------
      * @return array
      * +----------------------------------------------------------
      */
     public function getTables($where = '',$param=array())
     {
         $where=str_replace('{dbprefix}',$this->config['prefixTo'],$where);
         if(isset($param['tablename'])) $where.=" and `TABLES`.TABLE_NAME='".$param['tablename']."' ";
         if(isset($param['field'])){
             $selectField=!isNull($param['field'])?$param['field']:"information_schema.`TABLES`.*";
         }else{
             $selectField=(isset($param['all']) && $param['all']==1?'information_schema.`TABLES`.TABLE_NAME as table_name,
            information_schema.`TABLES`.TABLE_COMMENT as comments':'information_schema.`TABLES`.*');
         }

         return sdb::query("SELECT  ".$selectField."  from information_schema.tables
            where table_schema = '" . $this->config['dbName'] . "' $where", false);
     }
     /**
      * +----------------------------------------------------------
      * 所有数据表入库
      * +----------------------------------------------------------
      * @public
      * @param array $param 扩展参数
      * +----------------------------------------------------------
      * @return array
      * +----------------------------------------------------------
      */
     function tablesavetotab($param=array())
     {
         $result = '';
         $tablesarr = $this->getTables();
         foreach ($tablesarr as $key => $val) {
             $tablename = ($this->config['prefixTo'] != "") ? strtr(strtolower($val['table_name']), array(strtolower
             ($this->config['prefixTo']) => "")) : strtolower($val['table_name']);
             $comments = isset($val['comments'])?$val['comments']:(isset($val['table_comment'])?$val['table_comment']:"");
             $commentsarr = explode(";", $comments);
             $comments = $commentsarr[0];
             if ($tablename != "") {
                 if ($this->isExsitDbTable($tablename) == 0) {
                     if (strstr($val['table_name'], $this->config['prefixTo'])) {
                         $seqno = strings::keyGen();
                         $tablenameArr=explode("_",$tablename);
                         $ext=$tablenameArr[0];
                         $moduleid='';
                         if($ext!=""){
                             $tmp=sdb::query("select moduleid from __dbprefix__".SETTING_TABLE_EXT."module where deleted='0' and tabext='$ext'",false);
                             if(count($tmp)>0) $moduleid=$tmp[0]['moduleid'];
                         }
                         $datas['table'] = "__dbprefix__".SETTING_TABLE_EXT."table";
                         $datas['fields'] = array(
                             "tableid" => $seqno,
                             "moduleid"=>$moduleid,
                             "seqno" => $seqno,
                             "title" => $comments,
                             "tablename" => $tablename,
                             "description" => $comments);
                         sdb::insert($datas);
                         $result .= "新增表=》表名：" . $tablename . " 名称：" . $comments . "<br>";
                     } else {
                         $result .= "非正式表=》表名：" . $tablename . " 名称：" . $comments . "<br>";
                     }
                 } else {
                     $result .= "已存在表=》表名：" . $tablename . " 名称：" . $comments . "<br>";
                 }
             }

         }
         return $result;
     }

     /**
      * +----------------------------------------------------------
      * 表是否已存在
      * +----------------------------------------------------------
      * @public
      * @param string $table 表名称
      * +----------------------------------------------------------
      * @return array
      * +----------------------------------------------------------
      */
     public function isExsitDbTable($table)
     {
         $sql = "select count(*) as nums from " . $this->config['prefixTo'] .
             SETTING_TABLE_EXT."table where tablename like '" . $table . "'";
         $result = sdb::query($sql, false);
         return $result[0]['nums'];
     }
     /**
      * +----------------------------------------------------------
      * 新增字段
      * +----------------------------------------------------------
      * @public
      * @access default
      * +----------------------------------------------------------
      * @return void
      * +----------------------------------------------------------
      */
     public function addfields($datas)
     {
         $table = $datas['table'];
         $field = $columnname =  $datas['field'];
         $type = $datas['type'];
         $default = $datas['default'];
         $length = intval($datas['length']);
         $primarykey = $datas['primarykey']; //PRIMARY KEY  (`id`)
         $notnull = $datas['notnull'];
         $notnull = ($notnull == "Y") ? 'NOT NULL' : '';
         $dieOnError = ($datas['dieOnError'] == "") ? false : $datas['dieOnError'];
         $msg = $datas['msg'];
         switch ($type) {
             case 'key':
                 $length = $length;
                 $field = $field . " int(11) NOT NULL  COMMENT '$msg'"; //auto_increment
                 break;
             case 'varchar':
                 $length = ($length<0)?50:$length;
                 $field = $field . " varchar(" . $length . ") $notnull default '" . $default .
                     "'  COMMENT '$msg'";
                 break;
             case 'tinyint':
                 $length = ($length <= 6) ? $length : 6;
                 $field = $field . " tinyint($length) $notnull default '0' COMMENT '$msg'";
                 break;
             case 'text':
                 $length = 0;
                 $field = $field . " text $notnull default '" . $default . "' COMMENT '$msg'";
                 break;
             case 'date':
                 $length = 0;
                 $field = $field . " date $notnull  COMMENT '$msg'";
                 break;
             case 'smallint':
                 $length = 6;
                 $field = $field . " smallint(6) $notnull default '0' COMMENT '$msg'";
                 break;
             case 'mediumint':
                 $length = 9;
                 $field = $field . " smallint(9) $notnull default '0' COMMENT '$msg'";
                 break;
             case 'int':
                 $length = ($length <= 11) ? $length : 11;
                 $field = $field . " int($length) $notnull default '0' COMMENT '$msg'";
                 break;
             case 'bigint':
                 $length = 20;
                 $field = $field . " int(20) $notnull default '0' COMMENT '$msg'";
                 break;
             case 'float':
                 $length = 0;
                 $field = $field . " float $notnull default '0' COMMENT '$msg'";
                 break;
             case 'double':
                 $length = 0;
                 $field = $field . " double $notnull default '0' COMMENT '$msg'";
                 break;
             case 'decimal':
                 $length = 10;
                 $field = $field . " decimal(10) $notnull default '0' COMMENT '$msg'";
                 break;
             case 'datetime':
                 $length = 0;
                 $field = $field . " datetime $notnull COMMENT '$msg'";
                 break;
             case 'timestamp':
                 $length = 0;
                 $field = $field . " timestamp $notnull COMMENT '$msg'";
                 break;
             case 'time':
                 $length = 0;
                 $field = $field . " time $notnull COMMENT '$msg'";
                 break;
             case 'year':
                 $length = 4;
                 $field = $field . " year(4) $notnull COMMENT '$msg'";
                 break;
             case 'char':
                 $length = $length;
                 $field = $field . " char(" . $length . ") $notnull default '" . $default .
                     "' COMMENT '$msg'";
                 break;
             case 'tinyblob':
                 $length = 0;
                 $field = $field . "  tinyblob $notnull COMMENT '$msg'";
                 break;
             case 'mediumtext':
                 $length = 0;
                 $field = $field . "  mediumtext $notnull  COMMENT '$msg'";
                 break;
             case 'longblob':
                 $length = 0;
                 $field = $field . " longblob $notnull COMMENT '$msg'";
                 break;
             case 'longtext':
                 $length = 0;
                 $field = $field . " longtext $notnull COMMENT '$msg'";
                 break;
             case 'enum':
                 $length = 0;
                 $field = $field . " enum $notnull COMMENT '$msg'";
                 break;
             default:
                 $field = $field;
         }

         $query = " alter table " . $table . " add " .$field;
         $res=parent::addField(array("sql"=>$query));
         self::tableStructureHis($query,array("type"=>0,"tablename"=>$table));
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
     public  function dropTable($table,$param=array())
     {
         $param['sql']='drop table if exists '.$table;
         return parent::dropTable($table,$param);
     }
     /**
      * +----------------------------------------------------------
      * 创建表-表结构
      * +----------------------------------------------------------
      * @public
      * @param array $datas 参数
      * @param array $param 扩展参数
      * +----------------------------------------------------------
      * @return void
      * +----------------------------------------------------------
      */
     public  function addTable($datas,$param=array())
     {
         if(!is_array($datas)) return false;
         $newTableName=$datas['table'];
         $relateKeyField=$datas['relateKeyField'];
         $relateKeyFieldIndex=$datas['relateKeyFieldIndex'];
         $query = "CREATE TABLE $newTableName (
                          id int(11) NOT NULL AUTO_INCREMENT COMMENT '编号',
                          $relateKeyField
                          createby int(11) DEFAULT NULL COMMENT '创建者',
                          modifyby int(11) DEFAULT NULL COMMENT '修改者',
                          ctime datetime DEFAULT NULL COMMENT '创建时间',
                          mtime datetime DEFAULT NULL COMMENT '修改时间',
                          vtime datetime DEFAULT NULL COMMENT '最后查看时间',
                          opcode char(32) DEFAULT NULL COMMENT '操作批次',
                          seqno char(32) DEFAULT NULL COMMENT '识别码',
                          no varchar(50) DEFAULT NULL COMMENT '系统编号',
                          deleted tinyint(4) DEFAULT '0' COMMENT '删除标示',
                          PRIMARY KEY (id),
                          $relateKeyFieldIndex
                          KEY " . $newTableName . "_seqno_idx (seqno)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='" . $datas['title'] .
             "'";
            parent::addTable(array("sql"=>$query));
            self::tableStructureHis($query,array("type"=>0,"tablename"=>$newTableName));
     }
     /**
      * +----------------------------------------------------------
      * 表结构变更记录
      * +----------------------------------------------------------
      * @public
      * @access default
      * @param sql  $query SQL语句
      * @param array $param 扩展参数
      * +----------------------------------------------------------
      * @print bool
      * +----------------------------------------------------------
      */
     function tableStructureHis($query,$param=array()){
         if(isNull($query) || !isset($param['tablename'])) return false;
         $query=str_replace('__dbprefix__',$this->config['prefixTo'],$query);
         $dataresTab=array();
         $dataresTab['createby'] = sessions::get('jysys_userid');
         $dataresTab['ctime'] = date("Y-m-d H:i:s");
         $dataresTab['ip'] = get_client_ip();
         $dataresTab['querystr'] = base64_encode($query);
         $dataresTab['seqno'] = strings::keyGen();
         $dataresTab['tablename'] = $param['tablename'];
         $dataresTab['type'] = $param['type'];
         $datasTab['table'] = "__dbprefix__".SETTING_TABLE_EXT."table_history";
         $datasTab['fields'] = $dataresTab;
         return sdb::insert($datasTab);
     }

     /**
      * +----------------------------------------------------------
      * 数据库导入
      * +----------------------------------------------------------
      * @public
      * @param array $param 扩展参数
      * array(
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
     public function import($param = array())
     {
         $ar = array();
         $fileName = getArrVal($param, "fileName");//路径
         $h = getArrVal($param, "h");
         $h = ($h == "") ? $this->config['host'] : $h;
         $u = getArrVal($param, "u");
         $u = ($u == "") ? $this->config['username'] : $u;
         $p = getArrVal($param, "p");
         $p = ($p == "") ? $this->config['password'] : $p;
         $port = getArrVal($param, "port");
         $port = ($port == "") ? $this->config['port'] : $port;
         $db = getArrVal($param, "db");
         $db = ($db == "") ? $this->config['dbName'] : $db;
         $res = '';
         if ($h != "" and $u != "" and $p != "" and $port != "" and $db != "" and $fileName !=
             "") {
             try {
                 exec("mysql  -u" . $u . " -p" . $p . " -h " . $h . " -P" . $port . " " . $db .
                     "  < " . $fileName, $ar, $res);
             }
             catch (exception $e) {

             }
             return $res;
         }
     }
     /**
      * +----------------------------------------------------------
      * 根据查询语句生成sql结构语句
      * 适用于联合查询条件的数据，单一查询条件或大数据量建议用sdb::dump($param=array());
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
     public function queryToSql($param=array())
     {
         $res=parent::query($param['sql']);
         $createTable=isset($param['createTable'])?$param['createTable']:false;

         $sql='';
         if(count($res)>0){
             $fieldCfg=array();
             foreach(array_keys($res[0]) as $ks=>$vs){
                 $fieldCfg[]='`'.$vs.'`';
             }
             foreach($res as $ks=>$vs){
                 $valData=array();
                 foreach($vs as $k=>$v){
                     $valData[]="'".$v."'";
                 }
                 $sql.="REPLACE INTO `".$param['table']."` (".implode(',',$fieldCfg).") VALUES (".implode(',',$valData)."); \r\n";

             }
         }
         if($createTable){
             $paramEx=array();
             $paramEx['dumpParam']=" ".$param['table']." --no-data ";
             $paramEx['savePath']='data/temp/';
             $paramEx['fileName']=$param['table'].strings::keyGen().'.sql';
             sdb::dump($paramEx);
             ext('files');
             $sql.=files::getfile($paramEx['savePath'].$paramEx['fileName'])." \r\n";
             files::deletefiles($paramEx['savePath'].$paramEx['fileName']);
             if(isset($param['savePath']) && isset($param['fileName'])){
                 file_put_contents($param['savePath'].$param['fileName'], $sql);
             }
         }
         return $sql;


     }
     /**
      * +----------------------------------------------------------
      * 数据库导出为SQL文件
      * +----------------------------------------------------------
      * @public
      * @param array $param 扩展参数
      * +----------------------------------------------------------
      * @return mixed
      * +----------------------------------------------------------
      */
     public function dump($param = array())
     {
         $ar = array();
         $mysqlPath = $this->mysqlPath();
         if (!is_dir($mysqlPath)) {
             $pathArr=array();
             if(isset($_SERVER["PATH"])){
                 $pathArr = explode(";", $_SERVER["PATH"]);
             }else{
                 if(isset($_SERVER["Path"])) $pathArr = explode(";", $_SERVER["Path"]);
             }
             foreach ($pathArr as $ks => $vs) {
                 if (strstr(strtolower($vs), "mysql") != "") {
                     $mysqlPath = (substr($vs, 0, -1) != "\\") ? $vs . "\\" : $vs;
                 }
             }
         }
         if(!is_dir($mysqlPath))  return '';
         ext('files');
         files::mkdirs(DB_BACKUP);
         $savePath=isset($param['savePath'])?$param['savePath']:'';
         if($savePath!="") files::mkdirs($savePath);
         $table = getArrVal($param, "table");
         $fileName = getArrVal($param, "fileName");
         $fileName = ($fileName == "") ? date("Ymdhisms") . ".sql" : $fileName;
         $h = getArrVal($param, "h");
         $h = ($h == "") ? $this->config['host'] : $h;
         $u = getArrVal($param, "u");
         $u = ($u == "") ? $this->config['username'] : $u;
         $p = getArrVal($param, "p");
         $p = ($p == "") ? $this->config['password'] : $p;
         $port = getArrVal($param, "port");
         $port = ($port == "") ? $this->config['port'] : $port;
         $db = getArrVal($param, "db");
         $db = ($db == "") ? $this->config['dbName'] : $db;
         $extParam = '';
         $extParam .= (getArrVal($param, "compact") == true) ? " --compact " : " ";
         $extParam .= (getArrVal($param, "noCreate") == true) ? " --no-create-info " : " ";
         $extParam .= (getArrVal($param, "noData") == true) ? " --no-data " : " ";
         $extParam .= getArrVal($param, "dumpParam");
         $res = '';
         if ($h != "" and $u != "" and $p != "" and $port != "" and $db != "") {
             try {
                 $execStr = $mysqlPath . "mysqldump -h " . $h . " -u" . $u . " -p" . $p . " -P" .
                     $port . " " . $db . "  $table --skip-comments $extParam > " .(is_dir($savePath)?$savePath:DB_BACKUP)  . $fileName;
                 exec($execStr, $ar, $res);
             }
             catch (exception $e) {

             }

         }
         return $res;
     }
     /**
      * +----------------------------------------------------------
      * mysql 安装目录
      * +----------------------------------------------------------
      * @public
      * @param array $param 扩展参数
      * +----------------------------------------------------------
      * @return string
      * +----------------------------------------------------------
      */
     public function mysqlPath($param=array())
     {
         $res = sdb::query("select @@basedir as basePath from dual", false);
         return IS_WIN == 1 ? $res[0]['basepath'] . "bin\\" : $res[0]['basepath'];
     }
     /**
      * +----------------------------------------------------------
      * 查询表设定-连贯操作
      * +----------------------------------------------------------
      * @public
      * @param string $str 表名称
      * @param array $param 扩展参数
      * +----------------------------------------------------------
      * @return object
      * +----------------------------------------------------------
      */
     public function table($str){
         $this->sqlParam['table']=$str;
         return $this;
     }
     /**
      * +----------------------------------------------------------
      * 更新-连贯操作
      * +----------------------------------------------------------
      * @public
      * @param array $arr 更新字段
      * +----------------------------------------------------------
      * @return object
      * +----------------------------------------------------------
      */
     public function set($arr=array()){
         $this->sqlParam['set']=$arr;
         return $this;
     }
     /**
      * +----------------------------------------------------------
      * 查询字段设定-连贯操作
      * +----------------------------------------------------------
      * @public
      * @param string $str 字段
      * +----------------------------------------------------------
      * @return object
      * +----------------------------------------------------------
      */
     public function field($str='*'){
         $this->sqlParam['field']=$str;
         return $this;
     }
     /**
      * +----------------------------------------------------------
      * 查询条件设定-连贯操作
      * +----------------------------------------------------------
      * @public
      * @param string $str 条件
      * +----------------------------------------------------------
      * @return object
      * +----------------------------------------------------------
      */
     public function where($str=''){
         $this->sqlParam['where']=$str;
         return $this;
     }
     /**
      * +----------------------------------------------------------
      * 查询排序设定-连贯操作
      * +----------------------------------------------------------
      * @public
      * @param string $str 排序
      * +----------------------------------------------------------
      * @return object
      * +----------------------------------------------------------
      */
     public function order($str=''){
         $this->sqlParam['order']=$str;
         return $this;
     }
     /**
      * +----------------------------------------------------------
      * 查询分组设定-连贯操作
      * +----------------------------------------------------------
      * @public
      * @param string $str 分组
      * +----------------------------------------------------------
      * @return object
      * +----------------------------------------------------------
      */
     public function group($str=''){
         $this->sqlParam['group']=$str;
         return $this;
     }
     /**
      * +----------------------------------------------------------
      * 查询分组条件设定-连贯操作
      * +----------------------------------------------------------
      * @public
      * @param string $str 分组条件
      * +----------------------------------------------------------
      * @return object
      * +----------------------------------------------------------
      */
     public function having($str=''){
         $this->sqlParam['having']=$str;
         return $this;
     }
     /**
      * +----------------------------------------------------------
      * 查询限定条数设定-连贯操作
      * +----------------------------------------------------------
      * @public
      * @param $start int 限定条数或起始索引
      * @param $count int 条数
      * +----------------------------------------------------------
      * @return object
      * +----------------------------------------------------------
      */
     public function limit($start='',$count=1){
         if(is_int($start)) $this->sqlParam['limit']=" limit $start";
         if(is_int($start) && $count>1 ) $this->sqlParam['limit'].=",$count";
         return $this;
     }
     /**
      * +----------------------------------------------------------
      * 日期格式化
      * +----------------------------------------------------------
      * @public
      * @param date/time $date 日期
      * @param string $format 格式化
      * %Y %m %d
      * +----------------------------------------------------------
      * @return mixed
      * +----------------------------------------------------------
      */
     public static function dateFormat($date,$format,$param=array())
     {
         return 'date_format('.$date.',\''.$format.'\')';
     }








}
