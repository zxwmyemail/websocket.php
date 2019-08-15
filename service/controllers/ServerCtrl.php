<?php
namespace app\service\controllers;
/********************************************************************************************
 * 控制层类
 * @author      iProg
 ********************************************************************************************/
use app\service\extend\Response;
use app\system\core\Config;
use app\system\core\BaseObject;

class ServerCtrl extends BaseObject{
    /**
     * 【推送】玩家聊天
     * 推送示例：
     * {
            "route": "serverCtrl@chat", 
            "request": {
                "fd": 2,
                "data": {
                    "from": {"openid": "1","nickname": "张三","avatarUrl": "http://"}, 
                    "msg": "你好，private"
                }
            }
        }
     */
    public function chat() {
        $request = $this->request;
        if (!isset($request['fd']) || !isset($request['data'])) return;

        $retMsg = Response::json(Response::PLAYER_CHAT, $request['data']);
        $this->send($request['fd'], $retMsg);
    }

    /**
     * 【推送】对战房间玩家信息
     * 推送示例：
     * {
            "route": "serverCtrl@roomInfo", 
            "request": {
                "fd": 2,
                "data": {
                    "isOk": 1,   // 是否人数已满，打开开始对战条件
                    "battleInfo": [
                        {"openid": "1","nickname": "张三","avatarUrl": "http://", ...}, 
                        {"openid": "2","nickname": "李四","avatarUrl": "http://", ...}
                        ...
                    ]
                }
            }
        }
     */
    public function roomInfo() {
        $request = $this->request;
        if (!isset($request['fd']) || !isset($request['data'])) return;

        $retMsg = Response::json(Response::ROOM_PLAYERS, $request['data']);
        $this->send($request['fd'], $retMsg);
    }

    /**
     * 【推送】取消房间消息
     * 推送示例：
     * {
            "route": "serverCtrl@cancelRoom", 
            "request": {
                "fd": 2,
            }
        }
     */
    public function cancelRoom() {
        $request = $this->request;
        if (!isset($request['fd'])) return;

        $retMsg = Response::json(Response::SUCCESS_CANCEL_ROOM);
        $this->send($request['fd'], $retMsg);
    }

    /**
     * 【推送】断线重连玩家重新上线的数据
     * 推送示例：
     * {"route": "serverCtrl@reconnect", "request": {"fd": 2,"data": {"openid": "2","findElem": [1,2,3]}}
     */
    public function reconnect() {
        $request = $this->request;
        if (!isset($request['fd']) || !isset($request['data'])) return;

        $retMsg = Response::json(Response::REONLINE, $request['data']);
        $this->send($request['fd'], $retMsg);
    }

    /**
     * 【推送】同步对战数据
     * 推送示例：
     * {"route": "serverCtrl@findElem", "request": {"fd": 2,"data": {"openid": "2","findElem": [1,2,3]}}
     */
    public function findElem() {
        $request = $this->request;
        if (!isset($request['fd']) || !isset($request['data'])) return;

        $retMsg = Response::json(Response::SYNC_ELEM_MESSAGE, $request['data']);
        $this->send($request['fd'], $retMsg);
    }

    /**
     * 【推送】同步对战数据
     * 推送示例：
     * {"route": "serverCtrl@gameOver", "request": {"fd": 2}}
     */
    public function gameOver() {
        $request = $this->request;
        if (!isset($request['fd'])) return;

        $retMsg = Response::json(Response::GAME_OVER);
        $this->send($request['fd'], $retMsg);
    }

    /**
     * 【推送】开始对战数据
     */
    public function startBattle() {
        $request = $this->request;
        if (!isset($request['battleInfo']) || !isset($request['fd'])) return;

        $retMsg = Response::json(Response::BATTLE_PACKET, [
            'stageId'      => $request['stageId'],
            'stageMessage' => $request['stageMessage'],
            'battleInfo'   => $request['battleInfo']
        ]);
        $this->send($request['fd'], $retMsg);
    }

    /**
     * 【推送】玩家离开消息
     */
    public function close() {
        $request = $this->request;
        if (!isset($request['fd']) || !isset($request['offData'])) return;

        $retMsg = Response::json(Response::OFF_LINE, $request['offData']);
        $this->send($request['fd'], $retMsg);
    }

}


