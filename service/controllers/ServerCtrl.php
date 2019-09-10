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
            "route": "serverCtrl@push", 
            "request": {
                "fd": 2,
                "type": "chat",
                "data": {
                    "from": {"openid": "1","nickname": "张三","avatarUrl": "http://"}, 
                    "msg": "你好，private"
                }
            }
        }
     */

    /**
     * 【推送】对战房间玩家信息
     * 推送示例：
     * {
            "route": "serverCtrl@push", 
            "request": {
                "fd": 2,
                "type": "roomInfo",
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

    /**
     * 【推送】取消房间消息
     * 推送示例：
     * {
            "route": "serverCtrl@push", 
            "request": {
                "fd": 2,
                "type": "cancelRoom"
            }
        }
     */

    /**
     * 【推送】断线重连玩家重新上线的数据
     * 推送示例：
     * {
            "route": "serverCtrl@push", 
            "request": {
                "fd": 2,
                "type": "reconnect",
                "data": {"openid": "2","findElem": [1,2,3]}
            }
     */

    /**
     * 【推送】同步对战数据
     * 推送示例：
     * {
            "route": "serverCtrl@push", 
            "request": {
                "fd": 2,
                "type": "findElem",
                "data": {"openid": "2","findElem": [1,2,3]}
            }
     */
    public function push() {
        $request = $this->request;
        if (!isset($request['fd']) || !isset($request['type'])) return;

        $msgType = Response::ERROR_TYPE_UNKNOWN;
        switch ($request['type']) {
            case 'chat':
                $msgType = Response::PLAYER_CHAT;
                break;
            case 'roomInfo':
                $msgType = Response::ROOM_PLAYERS;
                break;
            case 'cancelRoom':
                $msgType = Response::SUCCESS_CANCEL_ROOM;
                break;
            case 'reconnect':
                $msgType = Response::REONLINE;
                break;
            case 'findElem':
                $msgType = Response::SYNC_ELEM_MESSAGE;
                break;
            case 'startBattle':
                $msgType = Response::BATTLE_PACKET;
                break;
            case 'offline':
                $msgType = Response::OFF_LINE;
                break;
            case 'quitBattle':
                $msgType = Response::PLAYER_QUIT_BATTLE;
                break;
            default:
                return false;
                break;
        }

        $data = isset($request['data']) ? $request['data'] : [];
        $this->send($request['fd'], Response::json($msgType, $data));
    }

}


