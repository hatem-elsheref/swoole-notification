<?php

//https://www.swoole.co.uk/docs/modules/swoole-async-redis-client


const REDIS_HOSTNAME = "localhost";
const REDIS_PORT = 6379;
const WS_HOSTNAME = "localhost";
const WS_PORT = 9000;
const CHANNEL = 'notifications';



function broadcast(string $message, swoole_server $server){
    foreach ($server->connections as $fd){
        if ($server->isEstablished($fd)){
            $server->push($fd, $message);
        }
    }
}


function toUser(string $message, int $user , swoole_server $server){
    if ($server->isEstablished($user)){
        $server->push($user, $message);
    }
}


$server = new swoole_websocket_server(WS_HOSTNAME, WS_PORT, SWOOLE_PROCESS, SWOOLE_SOCK_TCP);



$server->on("workerStart", function (swoole_server $server, int $workerId = 0)  {

    if ($workerId === 0){
        $client = new Redis();
        $client->connect(REDIS_HOSTNAME, REDIS_PORT);
        $client->subscribe([CHANNEL], function ($redis, $channel, $message) use ($server){
            $data = ['type' => 'broadcast', 'body'=> 'hi user', 'time' => time(), 'to' => 1 ];

//            $message = json_encode($data);
//            $message = json_decode($message);


            $message = $data;

            switch ($message['type']){
                case 'private':
                    $client = new Redis();
                    $client->connect(REDIS_HOSTNAME, REDIS_PORT);
                    $connections = $client->sMembers("user_" . $data['to']);
                    foreach ($connections as $fd){
                        toUser($message['body'], $fd, $server);
                    }
                    break;
                case 'broadcast':
                default:
                    broadcast($message['body'], $server);
                    break;
            }
        });
    }
});



$server->on("start", function (swoole_websocket_server $server) {
    echo "WebSocket Server is started at " . WS_HOSTNAME . ":" . WS_PORT . PHP_EOL;
});

$server->on('open', function(swoole_websocket_server $server, swoole_http_request $request) {
    echo "connection open: {$request->fd}\n";
});

$server->on('close', function(swoole_websocket_server $server, int $fd)  {
    echo "connection close: {$fd}\n";
    $client = new Redis();
    $client->connect(REDIS_HOSTNAME, REDIS_PORT);
    $onlineUsers = $client->sMembers("online_users");

    foreach ($onlineUsers as $user){
        $client->sRem("user_" . $user, $fd);
    }
});

$server->on('message', function(swoole_websocket_server $server, swoole_websocket_frame $frame) use (&$onlineUsers) {
    echo "received message: {$frame->data}\n";

    $message = json_decode($frame->data, true);
    $client = new Redis();
    $client->connect(REDIS_HOSTNAME, REDIS_PORT);


    switch ($message['operation'] ?? 'message'){
        case 'open':
            if (filter_var($message['user_id'], FILTER_VALIDATE_INT) && $message['user_id'] > 0){
                $client->sAdd("online_users", $message['user_id']);
                $client->sAdd("user_" . $message['user_id'], $frame->fd);
            }
            break;
        case 'close':
            echo 'remove from redis';
             $client->sRem("user_" . $message['user_id'], $frame->fd);
             break;
        default:
            $server->push($frame->fd, $frame->data);
    }

});

$server->start();
