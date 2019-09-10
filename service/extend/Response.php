<?php
namespace app\service\extend;

class Response {

    const SUCCESS               = ['code' => 0,   'msg' => '请求成功'];
    const HEARTBEAT_PACKET      = ['code' => 201, 'msg' => '心跳包']; 
    const BATTLE_PACKET         = ['code' => 202, 'msg' => '开始对战']; 
    const SUCCESS_CANCEL_MATCH  = ['code' => 203, 'msg' => '取消匹配成功']; 
    const SYNC_ELEM_MESSAGE     = ['code' => 204, 'msg' => '同步玩家找到元素数据'];
    const GAME_OVER             = ['code' => 205, 'msg' => '游戏结束']; 
    const RECONNECTION          = ['code' => 206, 'msg' => '断线重连']; 
    const OFF_LINE              = ['code' => 207, 'msg' => '离线消息']; 
    const REONLINE              = ['code' => 208, 'msg' => '重新上线']; 
    const PLAYER_CHAT           = ['code' => 209, 'msg' => '玩家聊天'];
    const ROOM_PLAYERS          = ['code' => 210, 'msg' => '对战房间玩家信息'];
    const ROOM_NOT_EXIST        = ['code' => 211, 'msg' => '对战房间不存在或已满'];
    const SUCCESS_CANCEL_ROOM   = ['code' => 212, 'msg' => '房间已取消'];
    const BATTLE_TIME           = ['code' => 213, 'msg' => '获取对战时间'];
    const REGISTER_SUCCESS      = ['code' => 214, 'msg' => '注册成功'];

    const ERROR_ROUTE_PARAMS    = ['code' => 500, 'msg' => '路由参数有误'];
    const ERROR_REGISTER_PARAMS = ['code' => 501, 'msg' => '用户注册参数有误'];
    const ERROR_MATCH_PARAMS    = ['code' => 502, 'msg' => '匹配玩家参数有误'];
    const FAIL_CANCEL_MATCH     = ['code' => 503, 'msg' => '取消匹配失败'];
    const FAIL_CANCEL_ROOM      = ['code' => 504, 'msg' => '取消房间失败'];
    const PLAYER_QUIT_BATTLE    = ['code' => 505, 'msg' => '玩家主动退出对战'];
    
    const ERROR_TYPE_UNKNOWN    = ['code' => 951, 'msg' => '未知消息类型！'];
    const ERROR_OTHER_UNKNOWN   = ['code' => 952, 'msg' => '返回数据错误，需服务端检查！'];

    /**
     * 返回信息
     * @param array $codeInfo 返回码值信息
     * @param array $data 返回数据信息
     * @param string $replaceDesc 替换码值信息中的描述
     */
    public static function json($codeInfo = [], $data = [], $replaceDesc = '')
    {
        if (!is_array($codeInfo) || empty($codeInfo))
            $codeInfo = self::ERROR_OTHER_UNKNOWN;

        if (!empty($replaceDesc)) {
            $codeInfo['msg'] = is_array($replaceDesc) ? json_encode($replaceDesc) : $replaceDesc;
        }

        return json_encode([
            'retcode' => $codeInfo['code'],
            'message' => $codeInfo['msg'],
            'reqtime' => date('Y-m-d H:i:s'),
            'data'    => $data
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * 返回心跳包信息
     */
    public static function heartbeat()
    {
        return json_encode([
            'retcode' => self::HEARTBEAT_PACKET['code'],
            'message' => self::HEARTBEAT_PACKET['msg'],
            'reqtime' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_UNICODE);
    }

}
