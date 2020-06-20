<?php
namespace app\common\service;

use app\common\model\Orders;
use think\Db;
use think\Exception;

class OrdersService extends CommonService
{
    // 更新过期订单状态
    public function updateExpiredOrderStatus()
    {
        $now = date('Y-m-d');

        Db::startTrans();

        try {
            // 过期的预定订单，自动取消
            $res = Orders::where([
                ['status', '=', 10],
                ['departure_time', '<', $now]
            ])->update(['status' => 0]);
            if($res === false) throw new Exception('更新预定订单状态时失败！');

            // 过期的已入住订单，自动退房
            $res = Orders::where([
                ['status', '=', '20'],
                ['departure_time', '<', $now]
            ])->update(['status' => 30]);
            if($res === false) throw new Exception('更新已入住订单状态失败！');

            Db::commit();

            return [
                'status' => 1,
                'msg'    => '更新过期订单状态成功！'
            ];
        }catch (Exception $e){
            Db::rollback();
            return [
                'status' => -1,
                'msg'    => $e->getMessage()
            ];
        }
    }
}