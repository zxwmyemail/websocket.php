<?php
namespace app\service\models;

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

}
