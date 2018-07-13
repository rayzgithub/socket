<?php
use Workerman\Worker;
use Workerman\Lib\Timer;
require_once './Workerman/Autoloader.php';
require_once ("db/config.php");
sdb::init($GLOBALS['_dbConfig']);

$worker = new Worker('websocket://0.0.0.0:8690');
$worker->onConnect = function($connection)
{
    $data = array(
        'msg' => 'connect success'
    );
    send_to_client('connect',$connection,$data);
};

$worker->onClose = function($connection)use($worker){
    //从当前用户的会话集合中删除当前会话
    unset($worker->uidConnections[$connection->uuid]['connections'][$connection->con_index]);
    //若当前用户的所有连接已离线，则从用户列表中删除此用户
    if(count($worker->uidConnections[$connection->uuid]['connections']) == 0){
        remove_from_member_list($connection->uuid);
    }

    //给当前用户发送会话关闭的消息
//    foreach($worker->uidConnections[$connection->uuid]['connection'] as $con){
//        send_to_client('close',$con,array('index' => $connection->con_index));
//    }
};

$worker->onMessage = function($connection, $data)use($worker)
{

	try{
        logs('req',$data);
	    //解析json    每个请求必须包含三个参数  uuid  uname  action
//        logs('uid',json_encode($data));
        $req = json_decode($data,1);
        $action = $req['action'];
        $uuid = $req['data']['uuid'];
        $uname = $req['data']['uname'];
        $avatar = $req['data']['avatar'];

        if($action == 'init'){

            $new_user = false;

            if(!isset($worker->uidConnections[$uuid])){
                $worker->uidConnections[$uuid] = array(
                    'uname' => $uname,
                    'next_index' => 0,      //下一个连接的索引值
                    'connections' => array()
                );
                $new_user = true;
            }
            $next_index = $worker->uidConnections[$uuid]['next_index'];

            //当连接被关闭时可以根据 uuid、 con_index 属性值 unset对应的连接
            $connection->con_index = $next_index;
            $connection->uuid = $uuid;

            //当前用户连接数 +1
            $worker->uidConnections[$uuid]['connections'][$next_index] = $connection;

            //更新下一个连接的索引值
            $worker->uidConnections[$uuid]['next_index'] += 1;

            if($new_user){
                //新用户加入群聊
                $data = array(
                    'new_uuid' => $connection->uuid,
                    'new_uname' => $uname,
                    'new_avatar' => $avatar,
                    'sign' => $req['data']['avatar'],
                    'ucount' => count($worker->uidConnections)      //获取当前用户个数
                );

                //更新群成员列表
                update_member_list($data);

                $to_connections = [];
                //发送给除自己以为的所有其他人
                foreach($worker->connections as $v){
                    if($v->uuid != $connection->uuid){
                        $to_connections[] = $v;
                    }
                }
                logs('uuid',count($to_connections));

                //给所有用户发送当前用户人数 及 新用户信息
                send_to_client('new_user_join',$to_connections,$data);
            }

        }

        if($action == 'send_msg_all'){
            //发送消息给所有人
            $msg = $req['data']['msg'];

            $data = array(
                'from_uuid' => $uuid,
                'from_uname' => $uname,
                'from_avatar' => $avatar,
                'msg' => $msg,
                'time' => time()
            );

            $to_connections = [];
            //发送给除自己以为的所有其他人
            foreach($worker->connections as $v){
                if($v->uuid != $connection->uuid){
                    $to_connections[] = $v;
                }
            }

            send_to_client('server_msg',$to_connections,$data);

        }

        //监测心跳
        if($action == 'ping'){
            send_to_client('pong',$connection,array('msg' => 'pong'));
        }

	}catch (Exception $e){
		log("error",var_export($e));
		log('error','data error : ' . $data);
		send_to_client('error',array('msg' => 'data error!'));
	}
};

function sendAll($info){
    global $worker;
    foreach($worker->uidConnectionsPc as $connection){
        $connection->send($info);
    }
}

function formatSendData($data){
    return json_encode($data);
}

function send_to_client($type,$connections,$data){
    $send_data = array(
        'type' => $type,
        'data' => $data
    );
    logs('data',json_encode($send_data));
    if(is_array($connections)){
        foreach($connections as $con){
            $con->send(formatSendData($send_data));
        }
    }else{
        $connections->send(formatSendData($send_data));
    }

}

//更新成员列表
function update_member_list($data){

    $json_file = "/home/wwwroot/wordpress/memberList.json";

    $member_list = json_decode(file_get_contents($json_file),1);

    $member_list['data']['list'][] = [
        'username' => $data['new_uname'],
        'id' => $data['new_uuid'],
        'avatar' => $data['new_avatar'],
        'sign' => $data['sign']
    ];

    $member_json = json_encode($member_list);

    file_put_contents($json_file,$member_json);

}

//从成员列表中移除
function remove_from_member_list($uuid){
    $json_file = "/home/wwwroot/wordpress/memberList.json";

    $member_list = json_decode(file_get_contents($json_file),1);

    foreach($member_list['data']['list'] as $k => $v){
        if($v['id'] == $uuid){
            unset($member_list['data']['list'][$k]);
        }
    }

    $member_json = json_encode($member_list);

    file_put_contents($json_file,$member_json);
}

// function decode_json($data)
//     {
//         if (function_exists('json_decode'))
//         {
//             $data = json_decode($data, true);
//         } else
//         {
//             require_cache('Zend/Json.php');
//             $json = new Zend_Json();
//             $data = $json->decode($data);
//         }
//         return $data;
// }
/*$worker->onWorkerStart = function($worker)
{
    // 定时，每10秒一次
    Timer::add(10, function()use($worker)
    {
        // 遍历当前进程所有的客户端连接，发送当前服务器的时间
        foreach($worker->connections as $connection)
        {
            $connection->send(time());
        }
    });
};*/


// 运行worker
Worker::runAll();


?>