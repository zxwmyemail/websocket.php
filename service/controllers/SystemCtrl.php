<?php
namespace app\service\controllers;
/********************************************************************************************
 * 控制层类
 * @copyright   Copyright(c) 2015
 * @author      iProg
 * @version     1.0
 ********************************************************************************************/
use app\service\extend\Response;
use app\system\core\Config;
use app\system\core\BaseObject;

class SystemCtrl extends BaseObject {
    /**
     * 获取系统配置和时间
     */
    public function getConf() {
        $retMsg = Response::json(Response::SUCCESS, [
            'timestamp' => time(),
            'datetime'  => date('Y-m-d H:i:s')
        ]);
        $this->send($this->myFd, $retMsg);
    }

    /**
     * 【请求】处理心跳包
     */
    public function heartbeat() {
        $this->send($this->myFd, Response::heartbeat());
    }
}