<?php
namespace app\service\models;

use app\system\library\BaseLog;

class WssUtil {

    /**
     * 发布消息
     * @param array $codeInfo 返回码值信息
     * @param array $data 返回数据信息
     * @param string $replaceDesc 替换码值信息中的描述
     */
    public static function publish($redis, $type, $openids = [], $data = []) {
        if (empty($redis) || empty($type) || empty($openids)) return;

        // 记录对战玩家信息
        $battleInfo = [];

        $playerInfo = $redis->mGet($openids);
        foreach ($playerInfo as $info) {
            if (!$info) continue;

            $info = json_decode($info, true);
            $battleInfo[$info['openid']] = $info;
            // 向其他玩家推送该玩家的数据
            $redis->publish($info['channel'], json_encode([
                'route' => 'serverCtrl@push',
                'request' => [
                    'fd'   => $info['fd'],
                    'type' => $type,
                    'data' => $data
                ]
            ]));
        }

        return ['battleInfo' => $battleInfo];
    }

    /**
     * 发布对战消息
     * @param array $codeInfo 返回码值信息
     * @param array $data 返回数据信息
     * @param string $replaceDesc 替换码值信息中的描述
     */
    public static function publishBattleInfo($redis, $type, $openids = [], $data = []) {
        if (empty($redis) || empty($type) || empty($openids)) return;

        // 记录对战玩家信息
        $battleInfo = [];

        $playerInfo = $redis->mGet($openids);
        foreach ($playerInfo as $info) {
            if (!$info) continue;
            
            $info = json_decode($info, true);
            $battleInfo[$info['openid']] = $info;
            // 向正在对战的其他玩家推送该玩家的数据
            if ($info['isFighting'] == 1) {
                // 判断是否发送，对战状态是需要发送的
                $isNeedSend = true;
                if ($info['startTime'] > 0 && $diff > $info['totalTime']) {
                    $isNeedSend = false;
                }

                if ($isNeedSend) {
                    $redis->publish($info['channel'], json_encode([
                        'route' => 'serverCtrl@push',
                        'request' => [
                            'fd'   => $info['fd'],
                            'type' => $type,
                            'data' => $data
                        ]
                    ]));
                }
            }
        }

        return ['battleInfo' => $battleInfo];
    }

}
