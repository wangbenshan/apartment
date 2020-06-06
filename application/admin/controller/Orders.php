<?php

namespace app\admin\controller;

use app\admin\validate\OrdersValidate;
use app\common\service\RoomService;
use library\Controller;
use app\common\model\Orders as OrdersModel;
use app\common\model\Rooms;

class Orders extends Controller
{
    /**
     * 默认数据模型
     * @var string
     */
    public $table = 'Orders';

    // 列表
    public function index()
    {
        if($this->request->isGet()){
            $this->title = '订单列表';
            $query = $this->_query($this->table);
            $query->order('add_time desc,id desc')->page();
        }
    }

    // 列表
    public function view()
    {
        if($this->request->isGet()){
            $this->title = '订单详情';
            $order = OrdersModel::get($this->request->get('id'));
            $this->assign('vo', $order);
            $this->fetch();
        }
    }

    // 导入学生excel
    public function import()
    {
        if($this->request->isGet()){
            $this->applyCsrfToken();
            $this->title = '导入学生';

            $this->fetch('import');
        }else{
            $this->applyCsrfToken();

            $data = $this->request->post('data');
            if(empty($data)){
                $this->error('导入的数据为空，请导入Excel文件后上传！');
            }
            foreach($data as &$val){
                $val['add_time'] = date('Y-m-d H:i:s');
                // 没有押金
                if(!isset($val['deposit']) || $val['deposit'] <= 0){
                    $val['deposit_status'] = 0;
                }elseif(isset($val['status'])){
                    // 已退房，押金已退
                    if($val['status'] == 30){
                        $val['deposit_status'] = 2;
                    }else{
                        // 未退押金
                        $val['deposit_status'] = 1;
                    }
                }
            }
            // 导入学生信息
            $res = (new OrdersModel)->saveAll($data);
            if($res->isEmpty()){
                $this->error('导入失败，请重试');
            }else{
                $this->success('导入成功！');
            }
        }
    }

    // 立即下单
    public function add()
    {
        if($this->request->isGet()){
            $this->title = '立即下单';
            $this->applyCsrfToken();
            // 获取校区列表
            $this->assign('campus', RoomService::getCampus());
            // 获取房间规格
            $this->assign('type', RoomService::getRoomType());
            return $this->fetch('form');
        }else{
            $this->applyCsrfToken();

            $data = $this->request->post();
            // 检查必填字段
            $validate = new OrdersValidate();
            if(!$validate->scene('add')->check($data)){
                $this->error($validate->getError());
            }
            // 房间名称
            $room = Rooms::get($data['room_id']);
            if($room->isEmpty()) $this->error('房间数据有误，请重试！');
            $data['room_name'] = $room->name;

            // 校区名称
            $campus = RoomService::getCampus($data['campus_id']);
            if(empty($campus)) $this->error('校区数据有误，请重试！');
            $data['campus'] = $campus['name'];

            // 房间规格数字
            $room_type = RoomService::getRoomType($data['room_type_num']);
            if(empty($room_type)) $this->error('房间规格数据有误，请重试！');
            $data['room_type'] = $room_type;

            if($data['total_money'] <= 0) $this->error('订单总额数据错误，请重试！');

            if($data['pay_money'] < 0) $this->error('实付金额数据错误，请重试！');

            if($data['deposit'] < 0) $this->error('押金数据错误，请重试！');
            // 押金状态
            $data['deposit_status'] = $data['deposit'] > 0 ? 1 : 0;
            // 添加时间
            $data['add_time'] = date('Y-m-d H:i:s');
            // 订单状态
            $data['status'] = 20;   // 已付款，已入住

            $data['salesman_id'] = session('user.id');
            $data['salesman'] = session('user.username');

            $res = OrdersModel::create($data);
            if($res->isEmpty()) $this->error('创建订单失败，请重试！');
            $this->success('预定房间成功！');
        }
    }

    // 预定房间
    public function reserve()
    {
        if($this->request->isGet()){
            $this->title = '预定房间';
            $this->applyCsrfToken();
            // 获取校区列表
            $this->assign('campus', RoomService::getCampus());
            // 获取房间规格
            $this->assign('type', RoomService::getRoomType());
            return $this->fetch();
        }elseif($this->request->isPost()){
            $data = $this->request->post();
            // 检查必填字段
            $validate = new OrdersValidate();
            if(!$validate->scene('reserve')->check($data)){
                $this->error($validate->getError());
            }

            if(!empty($data['room_id'])){
                // 房间名称
                $room = Rooms::get($data['room_id']);
                if($room->isEmpty()) $this->error('房间数据有误，请重试！');
                $data['room_name'] = $room->name;
            }


            // 校区名称
            $campus = RoomService::getCampus($data['campus_id']);
            if(empty($campus)) $this->error('校区数据有误，请重试！');
            $data['campus'] = $campus['name'];

            // 房间规格数字
            $room_type = RoomService::getRoomType($data['room_type_num']);
            if(empty($room_type)) $this->error('房间规格数据有误，请重试！');
            $data['room_type'] = $room_type;

            if($data['total_money'] <= 0) $this->error('订单总额    数据错误，请重试！');

            if($data['front_money'] < 0) $this->error('定金数据错误，请重试！');

            // 应交尾款
            $data['rest_money'] = $data['total_money'] - $data['front_money'];
            if($data['rest_money'] < 0) $this->error('应交尾款数据错误，请重试！');

            // 添加时间
            $data['add_time'] = date('Y-m-d H:i:s');
            // 订单状态
            $data['status'] = 10;   // 已预订

            $data['salesman_id'] = session('user.id');
            $data['salesman'] = session('user.username');

            $res = OrdersModel::create($data);
            if($res->isEmpty()) $this->error('创建订单失败，请重试！');
            $this->success('预定房间成功！');
        }
    }

    // 处理预定
    public function handleReserve()
    {
        if($this->request->isGet()){
            $oid = $this->request->get('id');
            // 获取数据
            $order = OrdersModel::get($oid);
            if($order->isEmpty()) $this->error('数据错误，请重试！');
            if($order['status'] != 10) $this->error('该订单状态已变更，请确认！');

            $this->assign('order', $order);
            return $this->fetch();
        }else{

        }
    }
}
