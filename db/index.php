<?php
require_once ("config.php");
sdb::init($GLOBALS['_dbConfig']);
$datares=array();
$datares['seqno'] = md5(strtotime(time()));
$datares['createby'] = 1;
$datares['ctime'] = date("Y-m-d H:i:s");
$datas['table'] = "nt_m_gpsdata";
$datas['fields'] = $datares;
$newid=sdb::insert($datas);





