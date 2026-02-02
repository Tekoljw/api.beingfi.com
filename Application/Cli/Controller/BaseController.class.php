<?php

namespace Cli\Controller;

use \think\Controller;
use Think\Log;

/**
 * @author guotian
 * @date   2018-06-06
 */
class BaseController extends Controller
{
    /**
     * 初始化控制器
     * BaseController constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }
}