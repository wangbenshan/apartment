<?php

namespace app\apartment\controller;

use app\Common\Service\RoomService;
use app\common\model\Rooms as RoomsModel;
use think\Db;

/**
 * 房间管理
 * Class Rooms
 * @package app\apartment\controller
 */
class Rooms extends Base
{
    /**
     * 默认数据模型
     * @var string
     */
    public $table = 'Rooms';

    public $campus = [];

    public function initialize()
    {
        parent::initialize();
        $this->campus = session('user.bind_campus');
        $this->assign('bind_campus', $this->campus);
    }

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

            $where1 = [];       // orders表条件
            $where2 = [];       // rooms表条件
            $where3 = [];       // campus表条件
            if(empty($this->campus)){
                // 获取校区
                $this->assign('campus', RoomService::getCampus()->column(null, 'id'));
            }else{
                $where1[] = ['campus_id', '=', $this->campus['id']];
                $where2[] = ['campus', '=', $this->campus['id']];
                $where3[] = ['id', '=', $this->campus['id']];
            }

            // 各校区入住人数、已预订未入住人数
            $campus_query = Db::name('orders')->group('campus_id')
                ->fieldRaw('campus_id, COUNT(*) AS total,
                SUM(IF (`status` = 10, 1, 0) ) AS reserved, 
                SUM(IF (`status` = 20, 1, 0 ) ) as book_in')
                ->where($where1)->buildSql();

            // 各校区房间总床位数
            $campus_bed_total = Db::name('rooms')->where('status', 1)->where($where2)
                ->fieldRaw('campus, SUM(`bed_total`) as bed_count')
                ->group('campus')->select();
            $campus_bed_total = array_column($campus_bed_total, null, 'campus');

            $campus_beds = Db::name('campus')->alias('c')
                ->leftJoin([$campus_query => 'co'], 'c.id = co.campus_id')
                ->field('c.id, c.name, co.total, co.reserved, co.book_in')
                ->where($where3)->select();
            foreach($campus_beds as $key => $val){
                $campus_beds[$key]['bed_count'] = isset($campus_bed_total[$val['id']]) ? $campus_bed_total[$val['id']]['bed_count'] : 0;
            }
            $this->assign('campus_beds', $campus_beds);


            $sub_query = Db::name('orders')->group('room_id')
                ->fieldRaw('room_id, COUNT(*) AS total,
                SUM(IF (`status` = 10, 1, 0) ) AS reserved, 
                SUM(IF (`status` = 20, 1, 0 ) ) as book_in')
                ->where($where1)->buildSql();

            $query_obj = RoomsModel::with(['roomAdder'])->alias('r')
                ->leftJoin([$sub_query => 'o'], 'r.id = o.room_id')
                ->field('r.*, o.reserved, o.book_in, total')->where($where2);
            $this->_query($query_obj)->order('add_time desc,id desc')
                ->equal('campus,bed_total,face')->like('name,facilities')->page();
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
             book_in_time, departure_time, campus, room_id, status, room_name')->select();
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
}
