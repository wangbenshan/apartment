<?php

namespace app\apartment\controller;

use app\Common\Service\RoomService;
use library\Controller;
use app\common\model\Rooms as RoomsModel;
use think\Db;

/**
 * 房间管理
 * Class Rooms
 * @package app\apartment\controller
 */
class Rooms extends Controller
{
    /**
     * 默认数据模型
     * @var string
     */
    public $table = 'Rooms';

    /**
     * 房间列表
     * @auth true
     */
    public function index()
    {
        if($this->request->isGet()){
            $this->title = '房间列表';
            // 获取房间规格
            $this->assign('beds_config', array_column(RoomService::getRoomType(), null, 'num'));
            // 获取校区
            $this->assign('campus', RoomService::getCampus()->column(null, 'id'));

            $sub_query = Db::name('orders')->group('room_id')
                ->fieldRaw('room_id, COUNT(*) AS total,
                SUM(IF (`status` = 10, 1, 0) ) AS reserved, 
                SUM(IF (`status` = 20, 1, 0 ) ) as book_in')->buildSql();

            $query_obj = RoomsModel::with(['roomAdder'])->alias('r')
                ->leftJoin([$sub_query => 'o'], 'r.id = o.room_id')
                ->field('r.*, o.reserved, o.book_in, total');
            $this->_query($query_obj)->order('add_time desc,id desc')
                ->equal('campus,bed_total')->like('name')->page();
        }
    }

    /**
     * 查看房间详情
     * @auth true
     */
    public function view()
    {
        if($this->request->isGet()){
            $this->title = '查看房间';

            $room_id = $this->request->get('id');
            $room = RoomsModel::get($room_id);
            $this->assign('vo', $room);

            $this->assign('campus', RoomService::getCampus($room->campus));
            $this->assign('bed_total_text', RoomService::getRoomType($room->bed_total));

            $this->fetch();
        }
    }

    /**
     * 添加房间
     * @auth true
     */
    public function add()
    {
        if($this->request->isGet()) {
            $this->applyCsrfToken();
            $this->title = '添加房间';
            $this->assign('campus', RoomService::getCampus());
            $this->assign('beds', RoomService::getRoomType());
            $this->fetch('form');
        }else{
            $token_check = $this->applyCsrfToken(true);
            if(!$token_check) $this->error($this->csrf_message);

            $res = (new RoomService())->addRooms($this->request->post());
            if($res['status'] == 1){
                $this->success('添加成功！');
            }else{
                $this->error($res['msg']);
            }
        }
    }

    /**
     * 编辑房间
     * @auth true
     */
    public function edit()
    {
        if($this->request->isGet()) {
            $this->applyCsrfToken();
            $this->title = '添加房间';
            $this->assign('campus', RoomService::getCampus());
            $this->assign('beds', RoomService::getRoomType());

            // 获取room 数据
            $room_id = $this->request->get('id');
            $room = RoomsModel::where('id', $room_id)->findOrEmpty();

            $this->assign('vo', $room);
            $this->fetch('form');
        }else{
            $token_check = $this->applyCsrfToken(true);
            if(!$token_check) $this->error($this->csrf_message);

            $res = (new RoomService())->editRooms($this->request->post());
            if($res['status'] == 1){
                $this->success('添加成功！');
            }else{
                $this->error($res['msg']);
            }
        }
    }

    /**
     * 禁用房间
     * @auth true
     */
    public function forbid()
    {
        $this->applyCsrfToken();
        if(!$this->checkIsEmpty($this->request->param('id'))) $this->error('此房间已被预定或入住，不能删除！');
        $this->_save($this->table, ['status' => '0']);
    }

    /**
     * 启用房间
     * @auth true
     */
    public function resume()
    {
        $this->applyCsrfToken();
        $this->_save($this->table, ['status' => '1']);
    }

    /**
     * 删除房间
     * @auth true
     */
    public function remove()
    {
        $this->applyCsrfToken();
        if(!$this->checkIsEmpty($this->request->param('id'))) $this->error('此房间已被预定或入住，不能删除！');
        $this->_delete($this->table);
    }

    /**
     * 查看房间内学生列表
     * @auth true
     */
    public function viewStuList()
    {
        if($this->request->isGet()){
            $status = $this->request->get('status');
            $id = $this->request->get('id');
            $str = '';
            if($status == 10) $str = '预定';
            if($status == 20) $str = '入住';
            $this->title = '查看'.($str).'学生列表';

            $list = \app\common\model\Orders::where([
                ['room_id', '=', $id],
                ['status', '=', $status]
            ])->field('id, stu_name, stu_phone, native_place, stu_id_num, school, application,
             book_in_time, departure_time, campus, room_name, bed_num')->select();
            $this->assign('list', $list);
            $this->fetch();
        }
    }

    /**
     * 检查房间是否有空床位
     */
    private function checkIsEmpty($id)
    {
        $orders = \app\common\model\Orders::where([
            ['status', 'in', [10, 20]],
            ['room_id', '=', $id]
        ])->select();
        return $orders->isEmpty();
    }

    /**
     * 根据校区获取可入住房间
     */
    public function getAvailableRoomsByCampus()
    {
        if($this->request->isPost()){
            $data = $this->request->post();
            $campus_id = empty($data['campus']) ? '' : $data['campus'];
            $room_type = empty($data['type']) ? '' : $data['type'];
            $rooms = (new RoomService())->getAvailableRoomsByCampus($campus_id, $room_type);
            return json($rooms);
        }
    }

    /**
     * 获取房间总床位数
     */
    public function getRoomDetails()
    {
        if($this->request->isPost()){
            $room_id = $this->request->post('room_id');
            $room = RoomsModel::get($room_id);
            if($room->isEmpty()) $this->error('房间未设置！');
            $this->success('获取房间详情成功！', $room);
        }
    }
}