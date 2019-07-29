<?php
/********************************************************************************************
 * redis配置文件
 * @copyright   Copyright(c) 2015
 * @author      iProg
 * @version     1.0
 ********************************************************************************************/

return [
    'master' => [
        'host'              => '172.19.95.178',
        'port'              => '6379',
        'auth'              => '',
        'pconnect'          => true,
        'pool_min_size'     => 10,                                // redis连接池最小实例数
        'pool_max_size'     => 500,                               // redis连接池最大实例数
        'pool_wait_time'    => 4,                                 // 从redis连接池取实例等待超时时间
        'player_match_pool' => 'findout_player_match_pool',       // 多人对战玩家匹配池的键名，存储于redis的set集合中
        'sub_channel_name'  => [                                  // 订阅渠道
            'host' => '172.19.95.178:13146',
            'all'  => 'findout_all_channel'
        ],  
    ],
    'hashRedis' => [
        ['host' => '10.0.0.1', 'port' => 6379],
        ['host' => '10.0.0.2', 'port' => 6379],
        ['host' => '10.0.0.3', 'port' => 6379],
        ['host' => '10.0.0.4', 'port' => 6379],
        ['host' => '10.0.0.5', 'port' => 6379]
    ],
];

