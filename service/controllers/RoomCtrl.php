<?php
namespace app\service\controllers;
/********************************************************************************************
 * 快速匹配模式：控制层类
 * @author      iProg
 ********************************************************************************************/
use app\service\extend\Response;
use app\system\core\Config;
use app\system\core\BaseObject;
use app\system\library\BaseLog;
use app\system\library\HttpCurl;

class RoomCtrl extends BaseObject{
    /**
     * 【请求】创建对战房间
     * 请求示例：
     * {
            "route": "roomCtrl@create", 
            "request": {
                "openid":  "1",
                "stageId": 1
            }
        }
     */
    public function create() {
        $request = $this->request;
        if (!isset($request['openid']) || !isset($request['stageId'])) return;

        BaseLog::error(json_encode($request));

        $systemConf = Config::get('config');
        $roomPrefix = $systemConf['fight_room_prefix'];
        $expireTime = $systemConf['redis_expire_time'];

        $redis = $this->websocket->redis->get();

        // 先把创建者放入到房间
        $roomPlayersInfo = [
            'stageId' => $request['stageId'],
            'players' => [$request['openid']]
        ];

        // 将房间信息放入redis
        $roomKey = md5($roomPrefix . $request['openid']);
        $redis->set($roomKey, json_encode($roomPlayersInfo), $expireTime); 

        $player = $redis->get($request['openid']); 
        $me = json_decode($player, true);
        $me['stageId'] = $request['stageId'];
        $room[] = $me;
       
        $this->websocket->redis->back($redis);

        $retMsg = Response::json(Response::ROOM_PLAYERS, [
            'isOK' => 0,
            'stageId' => (int)$request['stageId'],
            'stageMessage' => [],
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
        $roomPlayersInfo = $redis->get($roomKey); 
        $roomPlayersInfo = json_decode($roomPlayersInfo, true);

        // 房间已经匹配好人数，不能撤销
        if (count($roomPlayersInfo['players']) >= $playerNum) {
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
        WssUtil::publish($redis, 'cancelRoom', $roomPlayersInfo['players']);
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
        $roomPlayersInfo = $redis->get($roomKey); 

        // 房间不存在
        if (!$roomPlayersInfo) {
            $this->websocket->redis->back($redis);
            $retMsg = Response::json(Response::ROOM_NOT_EXIST);
            $this->send($this->myFd, $retMsg);
            return;
        }

        // 房间已满
        $roomPlayersInfo = json_decode($roomPlayersInfo, true);
        if (count($roomPlayersInfo['players']) >= $playerNum) {
            $this->websocket->redis->back($redis);
            $retMsg = Response::json(Response::ROOM_NOT_EXIST);
            $this->send($this->myFd, $retMsg);
            return;
        }

        // 将房间信息放入redis
        $roomPlayersInfo['players'][] = $request['openid'];
        $redis->set($roomKey, json_encode($roomPlayersInfo), $expireTime); 

        $battleInfo = [];
        $channelInfo = [];
        $playerInfo = $redis->mGet($roomPlayersInfo['players']);
        foreach ($playerInfo as $info) {
            if ($info) {
                $info = json_decode($info, true);
                $info['opponent'] = array_values(array_diff($roomPlayersInfo['players'], [$info['openid']]));
                $info['isFighting'] = count($roomPlayersInfo['players']) >= $playerNum ? 1 : 0;
                $info['stageId'] = $roomPlayersInfo['stageId'];
                $battleInfo[] = $info;
                $channelInfo[] = [
                    'fd' => $info['fd'],
                    'channel' => $info['channel']
                ];
            }
        }

        $stageMessage = [];
        $isOK = (count($roomPlayersInfo['players']) >= $playerNum) ? 1 : 0;
        if ($isOK) {
            $result = HttpCurl::post($systemConf['stage_elem_url'], [
                'stage_id' => $roomPlayersInfo['stageId']
            ]);
            $result = json_decode($result, true);
            if ($result && isset($result['success']) && $result['success'] == 1) {
                $stageMessage = $result['data']['stage_message'];
            }
            unset($result);
        }

        // 向房间所有玩家推送房间信息
        foreach ($channelInfo as $info) {
            $msg = json_encode([
                'route' => 'serverCtrl@push',
                'request' => [
                    'fd'   => $info['fd'],
                    'type' => 'roomInfo',
                    'data' => [
                        'isOK'         => $isOK,
                        'stageId'      => (int)$roomPlayersInfo['stageId'],
                        'stageMessage' => $stageMessage,
                        'battleInfo'   => $battleInfo
                    ]
                ]
            ]);
            $redis->publish($info['channel'], $msg);
        }
       
        $this->websocket->redis->back($redis);
    }

}