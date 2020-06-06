<?php

namespace app\admin\controller;

use app\admin\validate\OrdersValidate;
use app\common\service\RoomService;
use library\Controller;
use app\common\model\Orders as OrdersModel;
use app\common\model\Rooms;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use think\Db;

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
            // 付款时间
            $data['pay_time'] = date('Y-m-d H:i:s');
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
            $this->applyCsrfToken();
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
        $this->applyCsrfToken();
        if($this->request->isGet()){
            $oid = $this->request->get('id');
            // 获取数据
            $order = OrdersModel::get($oid);
            if($order->isEmpty()) $this->error('数据错误，请重试！');
            if($order['status'] != 10) $this->error('该订单状态已变更，请确认！');
            $this->assign('vo', $order);

            // 如果未安排房间
            if(empty($order->room_name)){
                // 获取校区列表
                $this->assign('campus', RoomService::getCampus());
                // 获取房间规格
                $this->assign('type', RoomService::getRoomType());
                // 获取当前校区和房间规格下的可租房间
                $this->assign('av_rooms', (new RoomService())->getAvailableRoomsByCampus($order->campus_id, $order->room_type_num));
            }
            return $this->fetch();
        }else{
            $data = $this->request->only(['campus_id','room_type_num', 'room_id', 'deposit', 'actual_rest_money', 'id']);
            if(empty($data['id'])) $this->error('数据错误，请重试！');
            // 获取数据
            $order = OrdersModel::get($data['id']);
            if($order->isEmpty()) $this->error('数据错误，请重试！');
            if($order->status != 10) $this->error('订单为非预定状态，操作失败！');
            // 检查必填字段
            $validate = new OrdersValidate();
            // 未安排房间
            $scene = empty($order->room_name) ? 'handleReserve' : 'handleReserveForRoom';
            if(!$validate->scene($scene)->check($data)){
                $this->error($validate->getError());
            }

            if($scene == 'handleReserve'){
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
            }

            if($data['deposit'] < 0) $this->error('押金数据错误，请重试！');

            if($data['actual_rest_money'] != $order->rest_money) $this->error('实交尾款要和应该尾款一致，请重试！');

            // 订单状态
            $data['status'] = 20;   // 已付款，已入住
            // 付款时间
            $data['pay_time'] = date('Y-m-d H:i:s');
            // 实付总额 定金 + 押金 + 尾款
            $data['pay_money'] = $order['front_money'] + $data['deposit'] + $data['actual_rest_money'];

//            $data['salesman_id'] = session('user.id');
//            $data['salesman'] = session('user.username');

            $res = Db::name('orders')->update($data);
            if($res == 0) $this->error('预定订单处理失败，请重试！');
            $this->success('处理成功！');
        }
    }

    // 导出excel
    public function export()
    {
        if($this->request->isGet()){
            // 获取订单列表
            $orders = OrdersModel::select();
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
            $sheet->setCellValueByColumnAndRow(5, 1, '项目');
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
                $sheet->setCellValueByColumnAndRow(6, $row, $v->room_name);
                $sheet->setCellValueByColumnAndRow(7, $row, $v->lease_term);
                $sheet->setCellValueByColumnAndRow(8, $row, $v->book_in_time);
                $sheet->setCellValueByColumnAndRow(9, $row, $v->departure_time);
                $sheet->setCellValueByColumnAndRow(10, $row, $v->front_money);
                $sheet->setCellValueByColumnAndRow(11, $row, $v->rest_money);
                $sheet->setCellValueByColumnAndRow(12, $row, $v->actual_rest_money);
                $sheet->setCellValueByColumnAndRow(13, $row, $v->deposit);
                $sheet->setCellValueByColumnAndRow(14, $row, $v->total_money);
                $sheet->setCellValueByColumnAndRow(15, $row, $v->public_water_rate);
                $sheet->setCellValueByColumnAndRow(16, $row, $v->power_rate_cycle);
                $sheet->setCellValueByColumnAndRow(17, $row, $v->campus);
                $sheet->setCellValueByColumnAndRow(18, $row, $v->room_type);
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

            $filename = '订单列表_'.date('YmdHis').'xlsx';
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="'.$filename.'"');
            header('Cache-Control: max-age=0');

            $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->save('php://output');
        }
    }
}
