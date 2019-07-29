<?php
namespace app\service\controllers;
/********************************************************************************************
 * 邀请好友模式：控制层类
 * @author      iProg
 ********************************************************************************************/
use app\service\extend\Response;
use app\system\core\Config;
use app\system\core\BaseObject;

class RoomCtrl extends BaseObject{
    /**
     * 【请求】创建对战房间
     * 请求示例：
     * {
            "route": "roomCtrl@create", 
            "request": {
                "openid":  "1"
            }
        }
     */
    public function create() {
        $request = $this->request;
        if (!isset($request['openid'])) return;

        $systemConf = Config::get('config');
        $roomPrefix = $systemConf['fight_room_prefix'];
        $expireTime = $systemConf['redis_expire_time'];

        $redis = $this->websocket->redis->get();

        // 先把创建者放入到房间
        $roomPlayers[] = $request['openid'];

        // 将房间信息放入redis
        $roomKey = md5($roomPrefix . $request['openid']);
        $redis->set($roomKey, json_encode($roomPlayers), $expireTime); 

        $player = $redis->get($request['openid']); 
        $room[] = json_decode($player, true);
       
        $this->websocket->redis->back($redis);

        $retMsg = Response::json(Response::ROOM_PLAYERS, [
            'isOK' => 0,
            'battleInfo' => $room
        ]);
        $this->send($this->myFd, $retMsg);
    }

    /**
     * 【请求】撤销对战房间
     * 请求示例：
     * {
            "route": "roomCtrl@cancel", 
            "request": {
                "openid":  "1"
            }
        }
     */
    public function cancel() {
        $request = $this->request;
        if (!isset($request['openid'])) return;

        $systemConf = Config::get('config');
        $roomPrefix = $systemConf['fight_room_prefix'];
        $playerNum = $systemConf['online_player_num'];

        $redis = $this->websocket->redis->get();

        // 将房间信息放入redis
        $roomKey = md5($roomPrefix . $request['openid']);
        $roomPlayers = $redis->get($roomKey); 
        $roomPlayers = json_decode($roomPlayers, true);

        // 房间已经匹配好人数，不能撤销
        if (count($roomPlayers) >= $playerNum) {
            $this->websocket->redis->back($redis);
            return;
        }

        $ret = $redis->delete($roomKey); 
        if (!$ret) {
            $this->websocket->redis->back($redis);
            $retMsg = Response::json(Response::FAIL_CANCEL_ROOM);
            $this->send($this->myFd, $retMsg);
            return;
        }

        $playerInfo = $redis->mGet($roomPlayers);
        foreach ($playerInfo as $info) {
            if ($info) {
                $info = json_decode($info, true);
                $msg = json_encode([
                    'route' => 'serverCtrl@cancelRoom',
                    'request' => [
                        'fd'   => $info['fd']
                    ]
                ]);
                $redis->publish($info['channel'], $msg);
            }
        }

        $this->websocket->redis->back($redis);
    }

    /**
     * 【请求】进入房间参加对战
     * 请求示例：
     * {
            "route": "roomCtrl@joinByInvite", 
            "request": {
                "openid":  "1",
                "inviter" : "2"
            }
        }
     */
    public function joinByInvite() {
        $request = $this->request;
        if (!isset($request['openid']) || !isset($request['inviter'])) return;

        $systemConf = Config::get('config');
        $roomPrefix = $systemConf['fight_room_prefix'];
        $playerNum = $systemConf['online_player_num'];
        $expireTime = $systemConf['redis_expire_time'];

        $redis = $this->websocket->redis->get();

        $roomKey = md5($roomPrefix . $request['inviter']);
        $roomPlayers = $redis->get($roomKey); 

        // 房间不存在
        if (!$roomPlayers) {
            $this->websocket->redis->back($redis);
            $retMsg = Response::json(Response::ROOM_NOT_EXIST);
            $this->send($this->myFd, $retMsg);
            return;
        }

        // 房间已满
        $roomPlayers = json_decode($roomPlayers, true);
        if (count($roomPlayers) >= $playerNum) {
            $this->websocket->redis->back($redis);
            $retMsg = Response::json(Response::ROOM_NOT_EXIST);
            $this->send($this->myFd, $retMsg);
            return;
        }

        // 将房间信息放入redis
        $roomPlayers[] = $request['openid'];
        $redis->set($roomKey, json_encode($roomPlayers), $expireTime); 

        $battleInfo = [];
        $channelInfo = [];
        $playerInfo = $redis->mGet($roomPlayers);
        foreach ($playerInfo as $info) {
            if ($info) {
                $info = json_decode($info, true);
                $info['opponent'] = array_values(array_diff($roomPlayers, [$info['openid']]));
                $info['isFighting'] = count($roomPlayers) >= $playerNum ? 1 : 0;
                $battleInfo[] = $info;
                $channelInfo[] = [
                    'fd' => $info['fd'],
                    'channel' => $info['channel']
                ];
            }
        }

        // 向房间所有玩家推送房间信息
        foreach ($channelInfo as $info) {
            $msg = json_encode([
                'route' => 'serverCtrl@roomInfo',
                'request' => [
                    'fd'   => $info['fd'],
                    'data' => [
                        'isOK' => (count($roomPlayers) >= $playerNum) ? 1 : 0,
                        'battleInfo' => $battleInfo
                    ]
                ]
            ]);
            $redis->publish($info['channel'], $msg);
        }
       
        $this->websocket->redis->back($redis);
    }

}