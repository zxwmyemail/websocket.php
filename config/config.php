<?php
/********************************************************************************************
 * 系统配置文件
 * @copyright   Copyright(c) 2015
 * @author      iProg
 * @version     1.0
 ********************************************************************************************/

return [
	'listen_ws_ip'      => '0.0.0.0',  // websocket监听地址
	'listen_ws_port'    => 13146,      // websocket监听端口

	'fight_room_prefix' => 'findout_room_', 
	'online_player_num' => 2,
	'online_count_down' => 600,        // 游戏倒计时时间，秒
	'redis_expire_time' => 7200,       // redis中数据生存时间，秒
	'stage_elem_url'    => 'https://game.elloworld.cn/findout/web/index.php/home/getRandStageElem',
];

