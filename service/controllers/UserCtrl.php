<?php
namespace app\service\controllers;
/********************************************************************************************
 * 控制层类
 * @author      iProg
 ********************************************************************************************/
use app\service\extend\Response;
use app\system\core\Config;
use app\system\core\BaseObject;
use app\system\library\BaseLog;
use app\service\models\WssUtil;

class UserCtrl extends BaseObject{
    /**
     * 【请求】用户注册
     * 请求示例：
     * {"route": "userCtrl@register", "request": {"openid": "1","nickname": "张三","avatarUrl": "http://"}}
     */
    public function register() {
        $request = $this->request;
        if (!isset($request['openid'])) {
            $retMsg = Response::json(Response::ERROR_REGISTER_PARAMS);
            $this->send($this->myFd, $retMsg);
            return;
        }

        $systemConf = Config::get('config');
        $redisConf = Config::get('redis', 'master');
        $playerData = [
            'fd'         => $this->myFd,
            'openid'     => $request['openid'],
            'nickname'   => isset($request['nickname']) ? $request['nickname'] : '',
            'avatarUrl'  => isset($request['avatarUrl']) ? $request['avatarUrl'] : '',
            'channel'    => $redisConf['sub_channel_name']['host'],
            'isFighting' => 0,
            'startTime'  => 0,
            'endTime'    => 0,
            'totalTime'  => 0,
            'opponent'   => [],
            'foundElem'  => [],
        ];
        $redis = $this->websocket->redis->get();
        $player = $redis->get($request['openid']); 
        if ($player) {
            $playerData = json_decode($player, true);
            $playerData['fd']      = $this->myFd;
            $playerData['channel'] = $redisConf['sub_channel_name']['host'];
        }
        // 设置fd和openid的对应关系
        $redis->set(md5($this->myFd), $request['openid'], $systemConf['redis_expire_time']); 
        // 设置玩家信息
        $redis->set($request['openid'], json_encode($playerData), $systemConf['redis_expire_time']); 

        $retMsg = Response::json(Response::REGISTER_SUCCESS);
        $this->send($this->myFd, $retMsg);
        
        // 判断如果用户是断线重连，则向该玩家同步所有其它玩家数据
        $diff = time() - $playerData['startTime'];
        if ($playerData['isFighting'] == 1) {
            $isNeedReconnet = true;
            if ($playerData['startTime'] > 0 && $diff > $playerData['totalTime']) {
                $isNeedReconnet = false;
            }

            if ($isNeedReconnet) {
                $result = WssUtil::publishBattleInfo($redis, 'reconnect', $playerData['opponent'], $playerData);
                $battleInfo = $result['battleInfo'];
                $battleInfo[$playerData['openid']] = $playerData;

                // 向该玩家推送其它玩家的数据
                $retMsg = Response::json(Response::RECONNECTION, [
                    'curTimestamp' => time(),
                    'battleInfo'   => $battleInfo
                ]);
                $this->send($this->myFd, $retMsg);
            }
        }
        $this->websocket->redis->back($redis);
    }

    /**
     * 【请求】找到元素后，发送元素，同步到其他玩家
     * 请求示例：
     * {"route": "userCtrl@findElem", "request": {"openid": "2","findElem": [1,2,3], "opponent": ["1"]}}
     */
    public function findElem() {
        $request = $this->request;
        if (!isset($request['openid']) || !isset($request['findElem']) || !isset($request['opponent'])) return;

        $redis = $this->websocket->redis->get();
        $player = $redis->get($request['openid']);
        if ($player) {
            $player = json_decode($player, true);
            $findElem = empty($player['findElem']) ? [] : $player['findElem'];
            $player['findElem'] = array_merge($findElem, $request['findElem']);
            $redis->set($request['openid'], json_encode($player)); 
        }

        // 向每个玩家所在服务器的订阅频道发送对战消息
        WssUtil::publishBattleInfo($redis, 'findElem', $request['opponent'], [
            'openid'   => $request['openid'],
            'findElem' => $request['findElem']
        ]);
        $this->websocket->redis->back($redis);
    }

    /**
     * 【请求】游戏结束
     * 请求示例：
     * {"route": "userCtrl@gameOver", "request": {"openid": "2", "type": 2}}
     */
    public function gameOver() {
        $request = $this->request;
        if (!isset($request['openid']) || !isset($request['type']) || !in_array($request['type'], [2,4])) return;

        $systemConf = Config::get('config');
        $expireTime = $systemConf['redis_expire_time'];

        $redis = $this->websocket->redis->get();
        $player = $redis->get($request['openid']); 
        if (!$player) {
            $this->websocket->redis->back($redis);
            return;
        }

        $endTime = time();
        $player = json_decode($player, true);
        $player['isFighting'] = $request['type'];
        $player['endTime'] = $endTime;
        $redis->set($request['openid'], json_encode($player), $expireTime);

        // 推送离线消息给对战所有方
        if ($request['type'] == 4) {
            // 推送主动退出消息
            WssUtil::publishBattleInfo($redis, 'quitBattle', $player['opponent'], [
                'openid'    => $player['openid'],
                'nickname'  => $player['nickname'],
                'avatarUrl' => $player['avatarUrl'],
                'msg'       => '你的对手【' . $player['nickname'] . '】主动退出了游戏' 
            ]);

            // 然后更新主动退出状态信息
            $opponentInfo = $redis->mGet($player['opponent']);
            $opponentInfo = $opponentInfo ? $opponentInfo : [];
            foreach ($opponentInfo as $info) {
                if ($info) {
                    $info = json_decode($info, true);
                    $info['isFighting'] = 2;
                    $info['endTime'] = $endTime;
                    // 设置玩家信息
                    $redis->set($info['openid'], json_encode($info), $expireTime);
                }
            }
        }
        
        $this->websocket->redis->back($redis);
    }

    /**
     * 【请求】玩家聊天
     * 请求示例：
     * {
            "route": "userCtrl@chat", 
            "request": {
                "from": {"openid": "1","nickname": "张三","avatarUrl": "http://"}, 
                "msg": "你好，private"
            }
        }
     */
    public function chat() {
        $request = $this->request;
        if (!isset($request['from']) || empty($request['msg'])) {
            return;
        }

        $redis = $this->websocket->redis->get();
        $player = $redis->get($request['from']['openid']); 
        $player = json_decode($player, true);
        WssUtil::publishBattleInfo($redis, 'chat', $player['opponent'], [
            'from' => $request['from'],
            'msg'  => $request['msg']
        ]);
        $this->websocket->redis->back($redis);
    }

    /**
     * 【请求】广播消息
     */
    public function broadcast() {
        $request = $this->request;
        $retMsg = Response::json(Response::SUCCESS, $request);
        $this->emit($retMsg);
    }

}