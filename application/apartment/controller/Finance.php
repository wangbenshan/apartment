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

            $query_db = Db::name('Orders');

            $total_amount = $query_db->where($where)->sum('total_money');
            $this->assign('total_amount', number_format($total_amount, 2, '.', ','));

            $this->_query($query_db)->where($where)
                ->field('id, stu_name, salesman, total_money, front_money, pay_time, pay_money, deposit, public_water_rate, actual_public_water_rate')->page();
        }
    }
}
