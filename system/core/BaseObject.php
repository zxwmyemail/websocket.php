<?php
namespace app\system\core;
/********************************************************************************************
 * 控制层和model层父类
 * @copyright   Copyright(c) 2015
 * @author      iProg
 * @version     1.0
 ********************************************************************************************/

class BaseObject {

    // websocket链接实例
    public $websocket;

    // 当前用户连接句柄
    public $myFd;

    // 请求参数
    public $request;

    // 路由参数
    public $route;

     /**
     * 返回消息
     */
    public function send($fd, $data) {
        if ($this->websocket->isEstablished($fd)) {
            $this->websocket->push($fd, $data);
        }
    }


    /**
     * 广播数据
     */
    public function emit($data) {
        foreach ($this->websocket->connections as $fd) {
            // 需要先判断是否是正确的websocket连接，否则有可能会push失败
            if ($this->websocket->isEstablished($fd)) {
                $this->websocket->push($fd, $data);
            }
        }
    }
    
}


