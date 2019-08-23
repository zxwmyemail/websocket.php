<?php
namespace app\service\controllers;
/********************************************************************************************
 * 快速匹配模式：控制层类
 * @author      iProg
 ********************************************************************************************/
use app\service\extend\Response;
use app\service\models\WssUtil;
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

        $isOK = (count($roomPlayersInfo['players']) >= $playerNum) ? 1 : 0;
        $stageInfo = [];
        if ($isOK) {
            $result = HttpCurl::post($systemConf['stage_elem_url'], [
                'stage_id' => $roomPlayersInfo['stageId']
            ]);
            $result = json_decode($result, true);
            if ($result && isset($result['success']) && $result['success'] == 1) {
                $stageInfo = $result['data'];
            }
            unset($result);
        }

        $battleInfo = [];
        $playerInfo = $redis->mGet($roomPlayersInfo['players']);
        foreach ($playerInfo as $info) {
            if ($info) {
                $info = json_decode($info, true);
                $info['opponent'] = array_values(array_diff($roomPlayersInfo['players'], [$info['openid']]));
                $info['isFighting'] = $isOK;
                $info['startTime'] = time();
                $info['totalTime'] = isset($stageInfo['counting']) ? (int)$stageInfo['counting'] : 600;
                $info['stageId'] = $roomPlayersInfo['stageId'];
                // 设置玩家信息
                $redis->set($info['openid'], json_encode($info), $expireTime);
                $battleInfo[] = $info;
            }
        }

        WssUtil::publish($redis, 'roomInfo', $roomPlayersInfo['players'], [
            'isOK'         => $isOK,
            'stageId'      => (int)$roomPlayersInfo['stageId'],
            'stageMessage' => isset($stageInfo['stage_message']) ? $stageInfo['stage_message'] : [],
            'counting'     => isset($stageInfo['counting']) ? $stageInfo['counting'] : 600,
            'battleInfo'   => $battleInfo
        ]);

        $this->websocket->redis->back($redis);
    }


    /**
     * 【请求】同步对战开始时间
     * 请求示例：
     * {
            "route": "roomCtrl@syncTime", 
            "request": {
                "openid": "1",
                "opponent": ["oxf2323ddfdfdfdfdfd"],
            }
        }
     */
    public function syncTime() {
        $request = $this->request;
        if (!isset($request['openid']) || !isset($request['opponent'])) return;

        $curTime = time();
        $systemConf = Config::get('config');
        $expireTime = $systemConf['redis_expire_time'];

        $redis = $this->websocket->redis->get();

        $players = [$request['openid']];
        if (is_array($request['opponent'])) {
            $players = array_merge($players, $request['opponent']);
        }

        $playerInfo = $redis->mGet($players);
        foreach ($playerInfo as $info) {
            if ($info) {
                $info = json_decode($info, true);
                $info['startTime'] = $curTime;
                // 设置玩家信息
                $redis->set($info['openid'], json_encode($info), $expireTime);
            }
        }
        $this->websocket->redis->back($redis);
    }

}