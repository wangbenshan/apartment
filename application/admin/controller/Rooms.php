<?php

namespace app\admin\controller;

use app\Common\Service\RoomService;
use library\Controller;
use app\common\model\Rooms as RoomsModel;
use think\Db;

class Rooms extends Controller
{
    /**
     * 默认数据模型
     * @var string
     */
    public $table = 'Rooms';

    /**
     * 显示房间列表
     *
     * @return \think\Response
     */
    public function index()
    {
        if($this->request->isGet()){
            $this->title = '房间列表';
            // 获取房间规格
            $this->assign('beds_config', array_column(RoomService::getRoomType(), null, 'num'));
            // 获取校区
            $this->assign('campus', RoomService::getCampus()->column(null, 'id'));

            // 获取列表
            $query_obj = RoomsModel::with(['roomAdder']);
            $this->_query($query_obj)->order('add_time desc,id desc')
                ->equal('campus,bed_total')->like('name')->page();
        }
    }

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

    // 编辑
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

    public function forbid()
    {
        $this->applyCsrfToken();
        $this->_save($this->table, ['status' => '0']);
    }

    public function resume()
    {
        $this->applyCsrfToken();
        $this->_save($this->table, ['status' => '1']);
    }

    public function remove()
    {
        $this->applyCsrfToken();
        $this->_delete($this->table);
    }

    // 根据校区获取可入住房间
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
