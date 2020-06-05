<?php

namespace app\admin\controller;

use app\common\model\Rooms as RoomsModel;
use app\common\model\Beds as BedsModel;
use app\Common\Service\RoomService;
use library\Controller;

class Beds extends Controller
{
    /**
     * 默认数据模型
     * @var string
     */
    public $table = 'Beds';

    // 编辑
    public function index()
    {
        if($this->request->isGet()){
            $this->title = '编辑床位';
            $room_id = $this->request->get('id');
            // 获取房间信息
            $room = RoomsModel::get($room_id);

            // 获取床位信息
            $beds = \app\common\model\Beds::where('room_id', $room_id)
                ->with(['bedAdder'])->select();
            $this->assign('list', $beds);
            $this->assign('room', $room);
            $this->assign('bed_total_name', RoomService::getBedConfig($room->bed_total));

            $this->fetch();
        }
    }

    // 新增
    public function add()
    {
        if($this->request->isGet()){
            $this->applyCsrfToken();
            $this->title = '新增床位';
            $this->assign('room_id', $this->request->get('room_id'));
            $this->fetch('form');
        }else{
            $this->applyCsrfToken();

            // 新增
            $data = $this->request->post();
            $bed_total = RoomsModel::where('id', $data['room_id'])->value('bed_total');
            $bed_count = BedsModel::where('room_id', $data['room_id'])->count();
            if($bed_count >= $bed_total){
                $this->error('房间床位已满，新增失败');
            }
            $data['adder'] = session('user.id');
            $data['add_time'] = date('Y-m-d H:i:s');
            $res = BedsModel::create($data);
            if($res->isEmpty()){
                $this->error(lang('think_library_save_error'));
            }else{
                $this->success(lang('think_library_save_success'), '');
            }
        }
    }

    // 批量新增
    public function patchAdd()
    {
        if($this->request->isGet()){
            $this->applyCsrfToken();
            $this->title = '新增床位';

            $room_id = $this->request->get('room_id');
            $this->assign('room_id', $room_id);

            $bed_total = RoomsModel::where('id', $room_id)->value('bed_total');
            $bed_count = BedsModel::where('room_id', $room_id)->count();

            $this->assign('add_max', $bed_total > $bed_count ? $bed_total - $bed_count : 0);

            $this->fetch('patch_add');
        }else{
            $this->applyCsrfToken();

            // 新增
            $data = $this->request->post();

            $room_id = $data['room_id'];

            $bed_total = RoomsModel::where('id', $room_id)->value('bed_total');

            $bed_count = BedsModel::where('room_id', $room_id)->count();

            $beds = $data['beds'];

            if($bed_total - $bed_count - count($beds) < 0) $this->error('房间床位已满，新增失败!');

            $data = [];

            foreach($beds as $bed){
                $bed['room_id'] = $room_id;
                $bed['adder'] = session('user.id');
                $bed['add_time'] = date('Y-m-d H:i:s');
                $data[] = $bed;
            }

            $res = (new BedsModel)->saveAll($data);
            if($res->isEmpty()){
                $this->error(lang('think_library_save_error'));
            }else{
                $this->success(lang('think_library_save_success'), '');
            }
        }
    }

    // 编辑
    public function edit()
    {
        if($this->request->isGet()){
            $this->applyCsrfToken();
            $this->title = '编辑床位';
            $this->assign('vo', \app\common\model\Beds::get($this->request->get('id')));
            $this->fetch('form');
        }else{
            $this->applyCsrfToken();
            $this->_form($this->table, 'form');
        }
    }

    // 禁用
    public function forbid()
    {
        $this->applyCsrfToken();
        $this->_save($this->table, ['status' => '0']);
    }

    // 禁用
    public function resume()
    {
        $this->applyCsrfToken();
        $this->_save($this->table, ['status' => '1']);
    }

    // 删除
    public function remove()
    {
        $this->applyCsrfToken();
        $this->_delete($this->table);
    }
}
