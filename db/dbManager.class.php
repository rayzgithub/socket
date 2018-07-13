<?php if (!defined('JYSYSINC'))
    exit('JYSYS:No direct script access allowed');
require_once ("dbInterface.class.php");
require_once ("dbConnection.class.php");
/**
 * 数据库链接管理
 *
 * @version        $Id: dbManager.class.php 3 20:57 2012年5月13日 $
 * @package        Jysys.Libraries
 * @copyright      Copyright (c) 2012, REY, Inc.
 * @link           http://www.joyql.com
 */
class dbManager {

     private  $connections   =  array();     //  数据库连接实例
     private  $_connections  =  null;   //  当前数据库连接实例
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
    public function __construct($config=array()){

    }
    /**
     * +----------------------------------------------------------
     * 初始化数据库类实例
     * +----------------------------------------------------------
     * @public
     * @param array $config
     * +----------------------------------------------------------
     * @return Object
     * +----------------------------------------------------------
     */
     public  function getInstance($config=array()){
        $config=(count($config)>0)?$config:g('_dbConfig',true)->main;
        $dbType=$config['dbType'];
        $className='dbConnection'.ucfirst($dbType);
        foreach($config['list'] as $k=>$v){
            require_once "driver/$className.class.php";
            $dbClass=new $className($v);
            $this->connections[($v['id']!=""?$v['id']:$k)]   =$dbClass;
        }
        return $this->connections;
    }

}
