<?php

namespace app\apartment\controller;

use app\apartment\validate\OrdersValidate;
use app\common\model\SystemUser;
use app\common\service\RoomService;
use app\common\model\Orders as OrdersModel;
use app\common\model\Rooms;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use think\Db;
use think\Exception;

/**
 * 学生管理
 * Class Orders
 * @package app\apartment\controller
 */
class Orders extends Base
{
    /**
     * 默认数据模型
     * @var string
     */
    public $table = 'Orders';

    public $campus = [];

    public $is_salesman = false;

    public function initialize()
    {
        parent::initialize();
        $this->campus = session('user.bind_campus');
        $this->assign('bind_campus', $this->campus);

        // 获取身份
        $auth = explode(',', session('user.authorize'));
        // 如果是业务员
        if(in_array(self::SYSTEM_AUTH_SALESMAN, $auth)){
            $this->is_salesman = true;
        }

        $this->assign('is_salesman', $this->is_salesman);
    }

    /**
     * 订单列表
     * @auth true
     */
    public function index()
    {
        if ($this->request->isGet()) {
            $this->title = '订单列表';

            $where = [];
            $where[] = ['o.status', '<>', 40];
            $where[] = ['o.is_deleted', '=', 0];
            if(empty($this->campus)){
                // 校区列表
                $this->assign('campus', RoomService::getCampus());
            }else{
                $where[] = ['o.campus_id', '=', $this->campus['id']];
            }

            // 房间规格列表
            $this->assign('beds_config', RoomService::getRoomType());

            $query_obj = $this->_query($this->table)->alias('o')
                ->leftJoin(['ap_system_user' => 'su'], 'o.salesman_id = su.id')
                ->field('o.*, su.real_name as salesman')
                ->where($where)->order('o.add_time desc,o.id desc');

            if($this->request->has('actual_public_water_rate') && trim($this->request->param('actual_public_water_rate')) != ''){
                $map = [];
                $map[] = ['o.actual_public_water_rate', '<=', $this->request->param('actual_public_water_rate')];
                $map[] = ['o.actual_public_water_rate', '=', null];
                $query_obj->where(function($query) use ($map){
                    $query->whereOr($map);
                });
            }
            // 业务员
            if($this->is_salesman){
                $query_obj->where('o.salesman_id', session('user.id'))->like('room_name,stu_name,stu_phone');
            }else{
                $query_obj->like('room_name,stu_name,stu_phone,su.real_name#salesman');
            }
            $query_obj->equal('campus_id#campus,room_type_num#room_type,sex,o.status#status')->page();
        }
    }

    /**
     * 查看订单详情
     * @auth true
     */
    public function view()
    {
        if ($this->request->isGet()) {
            $this->title = '订单详情';
            $order = OrdersModel::get($this->request->get('id'));
            if($order->isEmpty()) $this->error('数据不存在！');
            if($order->is_deleted == 1) $this->error('已删除！');
            // salesman
            $salesman = SystemUser::where('id', $order['salesman_id'])->value('real_name');
            $order['salesman'] = $salesman ?: '';
            $this->assign('vo', $order);
            $this->fetch();
        }
    }

    /**
     * 删除订单
     * @auth true
     */
    public function remove()
    {
        $this->applyCsrfToken();
//        $order_id = $this->request->param('id');
        /*$order = OrdersModel::get($order_id);
        if($order->isEmpty()) $this->error('该订单不存在！');
        if($order->is_deleted == 1) $this->error('该订单已被删除，请不要重复操作！');*/

        $this->_save($this->table, ['is_deleted' => '1']);
    }

    /**
     * 安排房间
     * @auth true
     */
    public function add()
    {
        $this->applyCsrfToken();
        if ($this->request->isGet()) {
            $this->title = '立即下单';
            // 获取校区列表
            $this->assign('campus', RoomService::getCampus());
            // 获取房间规格
            $this->assign('type', RoomService::getRoomType());

            // 获取业务员
            $salesman = SystemUser::where([
                'status' => 1,
                'is_deleted' => 0
            ])->select();
            $this->assign('salesman', $salesman);

            return $this->fetch('form');
        } else {
            $data = $this->request->post();
            // 检查必填字段
            $validate = new OrdersValidate();
            if (!$validate->scene('add')->check($data)) {
                $this->error($validate->getError());
            }

            // 入住时间 和 离店时间 比较
            if (strtotime($data['book_in_time']) >= strtotime($data['departure_time'])) {
                $this->error('离店时间必须大于入住时间');
            }

            // 校区名称
            $campus = RoomService::getCampus($data['campus_id']);
            if (empty($campus)) $this->error('校区数据有误，请重试！');
            $data['campus'] = $campus['name'];

            // 房间规格数字
            $room_type = RoomService::getRoomType($data['room_type_num']);
            if (empty($room_type)) $this->error('房间规格数据有误，请重试！');
            $data['room_type'] = $room_type;

            // 房间名称
            $room = Rooms::where([
                ['id', '=', $data['room_id']],
                ['campus', '=', $data['campus_id']],
                ['bed_total', '=', $data['room_type_num']]
            ])->findOrEmpty();
            if ($room->isEmpty()) $this->error('房间数据有误，请重试！');
            $data['room_name'] = $room->name;

            if ($data['total_money'] <= 0) $this->error('学费总金额数据错误，请重试！');

            if ($data['actual_rest_money'] < 0) $this->error('实付金额数据错误，请重试！');

            if ($data['deposit'] < 0) $this->error('押金数据错误，请重试！');

            if ($data['actual_rest_money'] != $data['total_money']) $this->error('实付金额要与学费总金额一致！');

            $data['pay_money'] = $data['actual_rest_money'] + $data['deposit']; // 付款总金额 包含实付金额，押金 和 定金

            // 押金状态
            $data['deposit_status'] = $data['deposit'] > 0 ? 1 : 0;
            // 添加时间
            $data['add_time'] = date('Y-m-d H:i:s');
            // 付款时间
            $data['pay_time'] = date('Y-m-d H:i:s');
            // 订单状态
            $data['status'] = 20;   // 已付款，已入住

//            $data['salesman_id'] = session('user.id');
//            $data['salesman'] = session('user.real_name');

            $res = OrdersModel::create($data);
            if ($res->isEmpty()) $this->error('创建订单失败，请重试！');
            $this->success('下单成功，顾客可以入住房间啦！');
        }
    }

    /**
     * 编辑导入的订单
     * @auth true
     */
    public function edit()
    {
        $this->applyCsrfToken();
        $order = OrdersModel::get($this->request->param('id'));
        if($order->isEmpty()) $this->error('该订单不存在');
        if($order->status != 10 && $order->status != 20) $this->error('非预定和非入住订单暂不能修改！');
//        if($order->data_from != 1) $this->error('非导入的订单暂不能修改！');

        if ($this->request->isGet()) {
            $this->title = '修改订单';
            // 获取校区列表
            $this->assign('campus', RoomService::getCampus());
            // 获取房间规格
            $this->assign('type', RoomService::getRoomType());
            // 获取房间
            $rooms = (new RoomService())->getAvailableRoomsByCampus($order->campus_id, $order->room_type_num);
            $this->assign('av_rooms', $rooms);

            // 获取业务员
            $salesman = SystemUser::where([
                'status' => 1,
                'is_deleted' => 0
            ])->field('id, username, real_name')->select();
            $this->assign('salesman', $salesman);

            $this->assign('vo', $order);

            return $this->fetch('form');
        } else {
            $data = $this->request->only(['campus_id', 'room_type_num', 'room_id', 'stu_name', 'sex', 'stu_phone', 'stu_id_num',
                'project', 'school', 'application', 'native_place', 'lease_term', 'book_in_time', 'departure_time',
                'public_water_rate', 'actual_public_water_rate', 'power_rate_cycle', 'total_money', 'deposit', 'actual_rest_money', 'salesman_id', 'comment']);
            // 检查必填字段
            $validate = new OrdersValidate();
            if (!$validate->scene('edit')->check($data)) {
                $this->error($validate->getError());
            }

            // 入住时间 和 离店时间 比较
            if (strtotime($data['book_in_time']) >= strtotime($data['departure_time'])) {
                $this->error('离店时间必须大于入住时间');
            }

            // 校区名称
            $campus = RoomService::getCampus($data['campus_id']);
            if (empty($campus)) $this->error('校区数据有误，请重试！');
            $data['campus'] = $campus['name'];

            // 房间规格数字
            $room_type = RoomService::getRoomType($data['room_type_num']);
            if (empty($room_type)) $this->error('房间规格数据有误，请重试！');
            $data['room_type'] = $room_type;

            // 房间名称
            if(!empty($data['room_id'])){
                $room = Rooms::where([
                    ['id', '=', $data['room_id']],
                    ['campus', '=', $data['campus_id']],
                    ['bed_total', '=', $data['room_type_num']]
                ])->findOrEmpty();
                if ($room->isEmpty()) $this->error('房间数据有误，请重试！');
                $data['room_name'] = $room->name;
            }

            if ($data['total_money'] <= 0) $this->error('学费总金额数据错误，请重试！');

            if ($data['actual_rest_money'] < 0) $this->error('实付金额数据错误，请重试！');

            if ($data['deposit'] < 0) $this->error('押金数据错误，请重试！');


            // 检查 salesman
            $salesman = SystemUser::get($data['salesman_id']);
            if($salesman->isEmpty()) $this->error('业务员信息有误！');

            $res = Db::name('orders')->where('id', $order->id)->data($data)->update();
            if ($res === false) $this->error('修改订单失败，请重试！');
            $this->success('修改订单成功！');
        }
    }

    /**
     * 预定房间
     * @auth true
     */
    public function reserve()
    {
        $this->applyCsrfToken();
        if ($this->request->isGet()) {
            $this->title = '预定房间';
            // 获取校区列表
            $this->assign('campus', RoomService::getCampus());
            // 获取房间规格
            $this->assign('type', RoomService::getRoomType());

            // 获取业务员
            $salesman = SystemUser::where([
                'status' => 1,
                'is_deleted' => 0
            ])->select();
            $this->assign('salesman', $salesman);

            return $this->fetch();
        } elseif ($this->request->isPost()) {
            $data = $this->request->post();
            // 检查必填字段
            $validate = new OrdersValidate();
            if (!$validate->scene('reserve')->check($data)) {
                $this->error($validate->getError());
            }

            // 入住时间 和 离店时间 比较
            if (strtotime($data['book_in_time']) >= strtotime($data['departure_time'])) {
                $this->error('离店时间必须大于入住时间');
            }

            // 校区名称
            $campus = RoomService::getCampus($data['campus_id']);
            if (empty($campus)) $this->error('校区数据有误，请重试！');
            $data['campus'] = $campus['name'];

            // 房间规格数字
            $room_type = RoomService::getRoomType($data['room_type_num']);
            if (empty($room_type)) $this->error('房间规格数据有误，请重试！');
            $data['room_type'] = $room_type;

            // 房间有可能不安排
            if (isset($data['room_id']) && !empty($data['room_id'])) {
                // 房间名称
                $room = Rooms::where([
                    ['id', '=', $data['room_id']],
                    ['campus', '=', $data['campus_id']],
                    ['bed_total', '=', $data['room_type_num']]
                ])->findOrEmpty();
                if ($room->isEmpty()) $this->error('房间数据有误，请重试！');
                $data['room_name'] = $room->name;
            }

            if ($data['total_money'] <= 0) $this->error('学费总金额数据错误，请重试！');

            if ($data['front_money'] < 0) $this->error('定金数据错误，请重试！');

            // 应交尾款
            $data['rest_money'] = $data['total_money'] - $data['front_money'];
            if ($data['rest_money'] < 0) $this->error('应交尾款数据错误，请重试！');

            // 添加时间
            $data['add_time'] = date('Y-m-d H:i:s');
            // 订单状态
            $data['status'] = 10;   // 已预订
//
//            $data['salesman_id'] = session('user.id');
//            $data['salesman'] = session('user.real_name');

            $res = OrdersModel::create($data);
            if ($res->isEmpty()) $this->error('创建订单失败，请重试！');
            $this->success('预定房间成功！');
        }
    }

    /**
     * 房间预定处理
     * @auth true
     */
    public function handleReserve()
    {
        $this->applyCsrfToken();
        if ($this->request->isGet()) {
            $oid = $this->request->get('id');
            // 获取数据
            $order = OrdersModel::get($oid);
            if ($order->isEmpty()) $this->error('数据错误，请重试！');
            if ($order['status'] != 10) $this->error('该订单状态已变更，请确认！');
            $this->assign('vo', $order);

            // 如果未安排房间
            if (empty($order->room_id)) {
                // 获取校区列表
                $this->assign('campus', RoomService::getCampus());
                // 获取房间规格
                $this->assign('type', RoomService::getRoomType());

                $roomService = new RoomService();
                // 获取当前校区和房间规格下的可租房间
                $this->assign('av_rooms', $roomService->getAvailableRoomsByCampus($order->campus_id, $order->room_type_num));

                if(!empty($order->room_id)){
                    // 获取床位总数，生成床位列表
                    $result = $roomService->getAvailableBeds($order->room_id);
                    $rest_beds = $result['status'] == 1 ? $result['data']['rest_beds'] : [];
                    $this->assign('rest_beds', $rest_beds);
                }
            }
            return $this->fetch();
        } else {
            $data = $this->request->only(['campus_id', 'room_type_num', 'room_id',
                'public_water_rate', 'actual_public_water_rate', 'power_rate_cycle',
                'deposit', 'actual_rest_money', 'id']);
            if (empty($data['id'])) $this->error('数据错误，请重试！');
            // 获取数据
            $order = OrdersModel::get($data['id']);
            if ($order->isEmpty()) $this->error('数据错误，请重试！');
            if ($order->status != 10) $this->error('订单为非预定状态，操作失败！');
            // 检查必填字段
            $validate = new OrdersValidate();

            $scene = empty($order->room_id) ? 'handleReserve' : 'handleReserveForRoom';
            if(!is_numeric($data['deposit'])) $this->error('押金格式错误');
            $data['deposit'] = floatval($data['deposit']);
            if (!$validate->scene($scene)->check($data)) {
                $this->error($validate->getError());
            }

            // 未预定房间的情况
            if ($scene == 'handleReserve') {
                // 校区名称
                $campus = RoomService::getCampus($data['campus_id']);
                if (empty($campus)) $this->error('校区数据有误，请重试！');
                $data['campus'] = $campus['name'];

                // 房间规格数字
                $room_type = RoomService::getRoomType($data['room_type_num']);
                if (empty($room_type)) $this->error('房间规格数据有误，请重试！');
                $data['room_type'] = $room_type;

                // 房间名称
                $room = Rooms::where([
                    ['id', '=', $data['room_id']],
                    ['campus', '=', $data['campus_id']],
                    ['bed_total', '=', $data['room_type_num']]
                ])->findOrEmpty();
                if ($room->isEmpty()) $this->error('房间数据有误，请重试！');
                $data['room_name'] = $room->name;
            }

            // 未交押金
            if ($data['deposit'] < 0) $this->error('押金数据错误，请重试！');
            $data['deposit_status'] = 1;

            // 应交尾款为 0
            $rest_money = $order->rest_money == 0 ? $order->total_money - $order->front_money : $order->rest_money;
//            if ($data['actual_rest_money'] != $rest_money) $this->error('实交尾款要和应该尾款一致，请重试！');

            // 订单状态
            $data['status'] = 20;   // 已付款，已入住
            // 付款时间
            $data['pay_time'] = date('Y-m-d H:i:s');
            // 实付总额 定金 + 押金 + 尾款
            $data['pay_money'] = $order['front_money'] + $data['deposit'] + $data['actual_rest_money'];

//            $data['salesman_id'] = session('user.id');
//            $data['salesman'] = session('user.real_name');

            $res = Db::name('orders')->update($data);
            if ($res == 0) $this->error('预定订单处理失败，请重试！');
            $this->success('处理成功！');
        }
    }

    /**
     * 调换房间
     * @auth true
     */
    public function change()
    {
        $this->applyCsrfToken();
        $id = $this->request->param('id');
        // 获取当前订单信息
        $order = OrdersModel::get($id);
        if ($order->isEmpty()) $this->error('未发现当前存在订单，操作失败！');
        if ($order->status != 10 && $order->status != 20) $this->error('当前为非预定和入住情况，不能调换房间');

        if ($this->request->isGet()) {
            $this->title = '调换房间';

            $this->assign('campus', RoomService::getCampus());
            $this->assign('type', RoomService::getRoomType());
            // 获取当前校区和房间规格下的可租房间
            $this->assign('av_rooms', (new RoomService())->getAvailableRoomsByCampus($order->campus_id, $order->room_type_num));

            $this->assign('vo', $order);
            $this->fetch();
        } else {
            // 检查必填字段
            $data = $this->request->only(['id', 'campus_id', 'room_type_num', 'room_id', 'lease_term', 'book_in_time', 'departure_time', 'total_money']);
            $validate = new OrdersValidate();
            if (!$validate->scene('changeRoom')->check($data)) {
                $this->error($validate->getError());
            }

            // 入住时间 和 离店时间 比较
            if (strtotime($data['book_in_time']) >= strtotime($data['departure_time'])) {
                $this->error('离店时间必须大于入住时间');
            }

            // 调换房间
            Db::startTrans();
            try {
                if ($data['room_id'] == $order->room_id) throw new Exception('这个房间不想住了，那就换个房间吧~');

                // 保存原有订单状态，以便转移到新订单
                $order_status = $order->status;

                // 废除原有订单
                $order->status = 40;
                $res = $order->save();
                if ($res == 0) throw new Exception('操作失败，请重试！');

                // 获取房间信息
                $room = Rooms::get($data['room_id']);
                if ($room->isEmpty()) throw new Exception('房间不存在！');
                if ($room->id == $order->room_id) throw new Exception('这个房间不想住了，那就换个房间吧~');

                // 是否还有空床位
                $count = OrdersModel::where([
                    ['status', 'in', [10, 20]],
                    ['is_deleted', '=', 0],
                    ['room_id', '=', $room->id],
                    ['id', '<>', $order->id]
                ])->count();
                if ($count >= $room->bed_total) throw new Exception('选择的房间已满员了');

                // 移植数据，添加新订单
                $new = [
                    'room_id' => $data['room_id'],
                    'room_name' => $room->name,
                    'room_type_num' => $room->bed_total,
                    'room_type' => RoomService::getRoomType($room->bed_total),
                    'campus_id' => $room->campus,
                    'campus' => (RoomService::getCampus($room->campus))->name,
                    'stu_name' => $order->stu_name,
                    'stu_id_num' => $order->stu_id_num,
                    'sex' => $order->sex,
                    'stu_phone' => $order->stu_phone,
                    'native_place' => $order->native_place,
                    'school' => $order->school,
                    'application' => $order->application,
                    'project' => $order->project,
                    'lease_term' => $data['lease_term'] ? $data['lease_term'] : $order->lease_term,
                    'book_in_time' => $data['book_in_time'],
                    'departure_time' => $data['departure_time'],
                    'add_time' => date('Y-m-d H:i:s'),
                    'pay_time' => date('Y-m-d H:i:s'),
                    'front_money' => $order->front_money,
                    'rest_money' => 0,
                    'actual_rest_money' => 0,
                    'deposit' => $order->deposit,
                    'deposit_status' => $order->deposit_status,
                    'total_money' => $data['total_money'],
                    'pay_money' => $data['total_money'],
                    'public_water_rate' => $order->public_water_rate,
                    'power_rate_cycle' => $order->power_rate_cycle,
                    'status' => $order_status,
                    'comment' => $order->comment,
                    'salesman_id' => session('user.id'),
//                    'salesman' => session('user.real_name'),
                    'change_from' => $order->id,
                    'diff_money' => $data['total_money'] - $order->pay_money
                ];
                $new_order = OrdersModel::create($new);
                if ($new_order->isEmpty()) throw new Exception('创建新订单失败，请重试！');

                Db::commit();
                $this->success('调换新房间成功！', 'javascript:history.back()');
            } catch (Exception $exception) {
                Db::rollback();
                $this->error($exception->getMessage());
            }
        }
    }

    /**
     * 退房
     * @auth true
     */
    public function checkout()
    {
        $this->applyCsrfToken();
        $id = $this->request->param('id');
        $order = OrdersModel::get($id);
        if($order->isEmpty()) $this->error('订单信息错误，请重试！');
        if($order->status != 20) $this->error('非入住状态不能退房！');

        if($this->request->isGet()){
            $this->title = '退房';

            $this->assign('vo', $order);
            $this->fetch();
        }else{
            // 检查必填字段
            $data = $this->request->only(['back_deposit', 'back_study_money', 'back_public_money']);
            $validate = new OrdersValidate();
            if (!$validate->scene('checkout')->check($data)) {
                $this->error($validate->getError());
            }
            if(floatval($data['back_deposit']) > $order->deposit) $this->error('实退押金不能大于所交押金');
            if(floatval($data['back_study_money']) > $order->total_money) $this->error('实退学费不能大于总金额');
            if(floatval($data['back_public_money']) > $order->actual_public_water_rate) $this->error('实退水电费不能大于实缴金额');
            $order->back_study_money = $data['back_study_money'];
            $order->back_public_money = $data['back_public_money'];
            $order->back_deposit = $data['back_deposit'] ?: 0;
            if($order->deposit == $data['back_deposit']) $order->deposit_status = 2;
            $order->status = 30;
            $res = $order->save();
            if($res === true) $this->success('退房成功');
            $this->error('退房失败');
        }
    }

    /**
     * 修改退费
     * @auth true
     */
    public function editCheckOut()
    {
        $this->applyCsrfToken();
        $id = $this->request->param('id');
        $order = OrdersModel::get($id);
        if($order->isEmpty()) $this->error('订单信息错误，请重试！');
        if($order->status != 30) $this->error('非已退房状态不能修改退费！');

        if($this->request->isGet()){
            $this->title = '修改退费';

            $this->assign('vo', $order);
            $this->fetch();
        }else{
            $data = $this->request->only(['edit_back_study_money', 'edit_back_public_money', 'edit_back_deposit']);
            if($data['edit_back_study_money'] !== ''){
                if(floatval($data['edit_back_study_money']) > $order->total_money) $this->error('实退学费不能大于总金额');
                $order->back_study_money = $data['edit_back_study_money'];
            }
            if($data['edit_back_public_money'] !== ''){
                if(floatval($data['edit_back_public_money']) > $order->actual_public_water_rate) $this->error('实退水电费不能大于实缴金额');
                $order->back_public_money = $data['edit_back_public_money'];
            }
            if($data['edit_back_deposit'] !== ''){
                if(floatval($data['edit_back_deposit']) > $order->deposit) $this->error('实退水电费不能大于实缴金额');
                $order->back_deposit = $data['edit_back_deposit'];
            }
            $res = $order->save();
            if($res === true) $this->success('修改退费成功');
            $this->error('修改退费失败');
        }
    }

    /**
     * 导入学生Excel
     * @auth true
     */
    public function import()
    {
        $this->applyCsrfToken();
        if($this->request->isGet()){
            $this->title = '导入学生';

            $this->fetch('import');
        }else{
            $data = $this->request->post('data');
            if(empty($data)){
                $this->error('导入的数据为空，请导入Excel文件后上传！');
            }

            // 获取校区
            $campus = RoomService::getCampus()->toArray();
            $campus_names = array_column($campus, 'name');
            $campus = array_column($campus, 'name', 'id');

            $orderModel = new OrdersModel();

            $phone_arr = [];
            $update_data = [];
            $start = date('Y-m-d H:i:s', strtotime('-1 month'));

            // 查询姓名不重复的业务员信息
            $salesman_info = SystemUser::where([
                'is_deleted' => 0,
                'status' => 1
            ])->field('id, username, real_name, count(*) as count')
                ->group('real_name')->having('count = 1')->select();

            // 以 real_name 分组
            $salesman_info_rn = $salesman_info->column(null, 'real_name');

            foreach($data as &$val){
                // 获取房间规格数字
                $room_type_num = RoomService::getRoomType($val['room_type'], true);
                if(!$room_type_num) $this->error('房间规格【'.$val['room_type'].'】格式有误，应该如“四人间”或“4人间”');
                $val['room_type_num'] = $room_type_num;

                if(!in_array($val['campus'], $campus_names)) $this->error('校区名称【'.$val['campus'].'】有误，请确认');
                $val['campus_id'] = array_search($val['campus'], $campus);
                if(empty($val['campus_id'])) $this->error('获取校区数据有误，请重试！');

                // excel中 已安排房间
                if(isset($val['room_name']) && $val['room_name'] != '未安排'){
                    // 验证房间
                    $room = Rooms::where([
                        ['name', '=', $val['room_name']],
                        ['campus', '=', $val['campus_id']],
                        ['bed_total', '=', $room_type_num]
                    ])->findOrEmpty();
                    if($room->isEmpty()) $this->error('房间【'.$val['room_name'].'】暂未添加到系统当中！');
                    if($room->status == 0) $this->error('房间【'.$val['room_name'].'】处于不可用状态！');
                    $val['room_id'] = $room->id;
                }else{
                    // 未安排房间
                    $val['room_name'] = '';
                    if($val['status'] != '10') $this->error($val['stu_name'].'同学的订单状态错误，订单状态【预定未安排】与实际不符！');
                }

                $deposit = 0;
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
                    $deposit = $val['deposit'];
                }

                // 实付金额
                $front_money = isset($val['front_money']) ? $val['front_money'] : 0;
                $actual_rest_money = isset($val['actual_rest_money']) ? $val['actual_rest_money'] : 0;
                // 应交金额
                $val['rest_money'] = isset($val['rest_money']) ? $val['rest_money'] : ($val['total_money'] - $front_money);
                $val['pay_money'] = $front_money + $actual_rest_money + $deposit;

                // 数据来源：excel导入
                $val['data_from'] = 1;

                $phone_arr[] = $val['stu_phone'];

                $val['add_time'] = date('Y-m-d H:i:s');

                if(isset($salesman_info_rn[$val['salesman']])){
                    $val['salesman_id'] = $salesman_info_rn[$val['salesman']]['id'];
                }else{
                    unset($val['salesman']);
                }
            }

            Db::startTrans();
            try{
                // TODO 查重，一个月内同手机号算重复
                $res = $orderModel->where([
                    ['is_deleted', '<>', 1],
                    ['status', '<>', 20],   // 已入住的不覆盖
                    ['stu_phone', 'in', $phone_arr]
                ])->whereTime('add_time', '>=', $start)->data(['is_deleted' => 1])->update();
                if($res === false) throw new Exception('覆盖旧数据失败，请重试！');

                // 新增数据
                $res = $orderModel->saveAll($data);
                if($res->isEmpty()) throw new Exception('导入数据失败，请重试！');

                Db::commit();
                $this->success('导入数据成功'.(empty($update_data) ? '' : '，共有'.count($update_data).'条数据被覆盖！'));

            }catch(Exception $e){
                Db::rollback();
                $this->error($e->getMessage());
                $this->error('导入失败，请重试！');
            }
        }
    }

    /**
     * 导出学生excel
     * @auth true
     */
    public function export()
    {
        $this->applyCsrfToken();
        if($this->request->isGet()){
            // 获取订单列表
            $orders = OrdersModel::alias('o')
                ->leftJoin(['ap_system_user' => 'su'], 'o.salesman_id = su.id')
                ->where('o.is_deleted', 0)->field('o.*, su.real_name as salesman')->select();
            if($orders->isEmpty()) $this->error('暂无订单，导出失败！');

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // 设置默认样式
            $sheet->getDefaultRowDimension()->setRowHeight(15.6);
            $sheet->getDefaultColumnDimension()->setWidth(10);

            //设置标题行内容
            $sheet->setCellValueByColumnAndRow(1, 1, '姓名');
            $sheet->setCellValueByColumnAndRow(2, 1, '联系方式');
            $sheet->setCellValueByColumnAndRow(3, 1, '性别');
            $sheet->setCellValueByColumnAndRow(4, 1, '学校');
            $sheet->setCellValueByColumnAndRow(5, 1, '班型');
            $sheet->setCellValueByColumnAndRow(6, 1, '房间');
            $sheet->setCellValueByColumnAndRow(7, 1, '租期');
            $sheet->setCellValueByColumnAndRow(8, 1, '入住时间');
            $sheet->setCellValueByColumnAndRow(9, 1, '到期时间');
            $sheet->setCellValueByColumnAndRow(10, 1, '定金');
            $sheet->setCellValueByColumnAndRow(11, 1, '应交尾款');
            $sheet->setCellValueByColumnAndRow(12, 1, '实交尾款');
            $sheet->setCellValueByColumnAndRow(13, 1, '押金');
            $sheet->setCellValueByColumnAndRow(14, 1, '总费用');
            $sheet->setCellValueByColumnAndRow(15, 1, '公共区域水电费');
            $sheet->setCellValueByColumnAndRow(15, 1, '区域水电费实缴');
            $sheet->setCellValueByColumnAndRow(16, 1, '电费周期');
            $sheet->setCellValueByColumnAndRow(17, 1, '校区');
            $sheet->setCellValueByColumnAndRow(18, 1, '房间');
            $sheet->setCellValueByColumnAndRow(19, 1, '状态');
            $sheet->setCellValueByColumnAndRow(20, 1, '备注');
            $sheet->setCellValueByColumnAndRow(21, 1, '业务员');

            foreach ($orders as $k => $v){
                $row = $k + 2;
                // 设置内容
                $sheet->setCellValueByColumnAndRow(1, $row, $v->stu_name);
                $sheet->setCellValueByColumnAndRow(2, $row, $v->stu_phone);
                $sheet->setCellValueByColumnAndRow(3, $row, $v->sex == 1 ? '女' : '男');
                $sheet->setCellValueByColumnAndRow(4, $row, $v->school);
                $sheet->setCellValueByColumnAndRow(5, $row, $v->project);
                $sheet->setCellValueByColumnAndRow(6, $row, $v->room_type);
                $sheet->setCellValueByColumnAndRow(7, $row, $v->lease_term);
                $sheet->setCellValueByColumnAndRow(8, $row, $v->book_in_time);
                $sheet->setCellValueByColumnAndRow(9, $row, $v->departure_time);
                $sheet->setCellValueByColumnAndRow(10, $row, $v->front_money);
                $sheet->setCellValueByColumnAndRow(11, $row, $v->rest_money);
                $sheet->setCellValueByColumnAndRow(12, $row, $v->actual_rest_money);
                $sheet->setCellValueByColumnAndRow(13, $row, $v->deposit);
                $sheet->setCellValueByColumnAndRow(14, $row, $v->total_money);
                $sheet->setCellValueByColumnAndRow(15, $row, $v->public_water_rate);
                $sheet->setCellValueByColumnAndRow(15, $row, $v->actual_public_water_rate);
                $sheet->setCellValueByColumnAndRow(16, $row, $v->power_rate_cycle);
                $sheet->setCellValueByColumnAndRow(17, $row, $v->campus);
                $sheet->setCellValueByColumnAndRow(18, $row, $v->room_name);
                switch($v->status){
                    case 0: $status = '已取消';break;
                    case 10: $status = '已预定';break;
                    case 20: $status = '已入住';break;
                    case 30: $status = '已退房';break;
                    default: $status = '未知';
                }
                $sheet->setCellValueByColumnAndRow(19, $row, $status);
                $sheet->setCellValueByColumnAndRow(20, $row, $v->comment);
                $sheet->setCellValueByColumnAndRow(21, $row, $v->salesman);
            }

            $count = count($orders);
            $sheet->getStyle('A1:U'.($count + 1))->getFont()->setName('宋体')->setSize(12);
            $sheet->getStyle('A1:U'.($count + 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('A1:U1')->getFont()->setBold(true);

//            $filename = '订单列表_'.date('YmdHis').'.xlsx';
//            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
//            header('Content-Disposition: attachment;filename="'.$filename.'"');
//            header('Cache-Control: max-age=0');
//            $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');

            $filename = '订单列表_'.date('YmdHis').'.xls';
            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment;filename="'.$filename.'"');
            header('Cache-Control: max-age=0');
            $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xls');

            ob_clean();
            $writer->save('php://output');
            exit;
        }
    }

    /**
     * 根据学生姓名检索学生
     */
    public function queryStudents()
    {
        if($this->request->isGet()){
            $this->title = '查询学生';
            $this->fetch();
        }elseif($this->request->isPost()){
            $name = $this->request->post('name');
            $where = [];
            $where[] = ['status', '=', '10'];
            if(!empty($name)) $where[] = ['stu_name', 'like', '%'.$name.'%'];
            $students = OrdersModel::where($where)
                ->where('is_deleted', 0)
                ->field('id, stu_name, stu_id_num, sex, stu_phone')->select();
            return json($students->isEmpty() ? [] : $students);
        }
    }
}
