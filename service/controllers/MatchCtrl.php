<?php
namespace app\service\controllers;
/********************************************************************************************
 * 快速匹配模式：控制层类
 * @author      iProg
 ********************************************************************************************/
use app\service\extend\Response;
use app\system\core\Config;
use app\system\core\BaseObject;

class MatchCtrl extends BaseObject{
    /**
     * 【请求】匹配在线对战玩家
     * 将玩家的openid放入到匹配池中，然后后台匹配进程，进行自动匹配
     * 请求示例：
     * {"route": "userCtrl@start", "request": {"openid": "1", "stageId": 1}}
     */
    public function start() {
        $request = $this->request;
        if (!isset($request['openid']) || !isset($request['stageId'])) {
            $retMsg = Response::json(Response::ERROR_MATCH_PARAMS);
            $this->send($this->myFd, $retMsg);
            return;
        }

        $redisConf = Config::get('redis', 'master');
        $matchPoolName = $redisConf['player_match_pool'] . '_' . $request['stageId'];
        $redis = $this->websocket->redis->get();
        $player = $redis->get($request['openid']); 
        if (!$player) {
            $this->websocket->redis->back($redis);
            return;
        }

        $player = json_decode($player, true);
        $player['isFighting'] = 0;
        $player['opponent'] = [];
        $player['findElem'] = [];
        $redis->set($request['openid'], json_encode($player));
        $redis->sAdd($matchPoolName, $request['openid']);
        $this->websocket->redis->back($redis);
    }

    /**
     * 【请求】取消在线匹配
     * 请求示例：
     * {"route": "userCtrl@cancel", "request": {"openid": "1"}}
     */
    public function cancel() {
        $request = $this->request;
        $message = Response::FAIL_CANCEL_MATCH;
        if (!isset($request['openid'])) {
            $this->send($this->myFd, Response::json($msg));
            return;
        }

        $redisConf = Config::get('redis', 'master');
        $matchPoolName = $redisConf['player_match_pool'];
        $redis = $this->websocket->redis->get();
        $player = $redis->get($request['openid']);
        if (!$player) {
            $this->websocket->redis->back($redis);
            return;
        }

        $player = json_decode($player, true);
        if ($player['isFighting'] == 0 || $player['isFighting'] == 2) {
            $ret = $redis->sRemove($matchPoolName, $request['openid']);
            $message = $ret ? Response::SUCCESS_CANCEL_MATCH : Response::FAIL_CANCEL_MATCH;
        }
        $this->websocket->redis->back($redis);
        $this->send($this->myFd, Response::json($msg));
    }
}