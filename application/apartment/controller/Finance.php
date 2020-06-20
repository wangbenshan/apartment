<?php

namespace app\apartment\controller;

use app\common\service\RoomService;
use think\Db;

/**
 * 财务管理
 * Class Orders
 * @package app\apartment\controller
 */
class Finance extends Base
{
    /**
     * 财务管理
     * @auth true
     */
    public function index()
    {
        if ($this->request->isGet()) {
            $this->title = '财务管理';

            $where = [];
            $start_time = $this->request->param('start_time');
            $end_time = $this->request->param('end_time');
            if(!empty($start_time)) $where[] = ['pay_time', '>= time', $start_time];
            if(!empty($end_time)) $where[] = ['pay_time', '<= time', $end_time.' 23:59:59'];

            $total_amount = Db::name('orders')->where($where)
                ->fieldRaw('SUM(`total_money`) as total_money,
                 SUM(`front_money`) as total_front_money,
                 SUM(`pay_money`) as total_pay_money,
                 SUM(`deposit`) as total_deposit,
                 SUM(`public_water_rate`) as total_water_money,
                 SUM(`actual_public_water_rate`) as total_actual_water_money')->find();
            $this->assign('total_amount', $total_amount);

            $this->_query(Db::name('orders'))->where($where)
                ->field('id, stu_name, salesman, total_money, front_money, pay_time, pay_money, deposit, public_water_rate, actual_public_water_rate')->page();
        }
    }
}
