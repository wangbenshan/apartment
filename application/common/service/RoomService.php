<?php
namespace app\common\service;

use app\admin\validate\RoomsValidate;
use app\common\model\Campus;
use app\common\model\Rooms;
use think\Db;
use think\Exception;

class RoomService extends CommonService
{
    // 获取校区列表
    public static function getCampus($id = null)
    {
        return $id ? Campus::get($id) : Campus::select();
    }

    // 获取房间床位设置
    public static function getBedConfig($num = null)
    {
        if($num){
            switch ($num){
                case 1:  return '一人间';
                case 2:  return '二人间';
                case 4:  return '四人间';
                case 8:  return '八人间';
                case 10: return '十人间';
                default: return 'Error';
            }
        }
        return [
            ['num' => 1, 'text' => '一人间'],
            ['num' => 2, 'text' => '二人间'],
            ['num' => 4, 'text' => '四人间'],
            ['num' => 8, 'text' => '八人间'],
            ['num' => 10, 'text' => '十人间']
        ];
    }

    public function addRooms($data)
    {
        $this->startTrans();
        try {
            $validate = new RoomsValidate();
            if(!$validate->scene('addRoom')->check($data)){
                throw new Exception($validate->getError());
            }

            $room_data = [
                'name'          =>  $data['name'],
                'campus'        =>  $data['campus'],
                'bed_total'     =>  $data['bed_total']
            ];

            if($data['upload_pic']){
                if(count(explode('|', $data['upload_pic'])) > 5) throw new Exception('最多上传5张图片！');
                $room_data['pictures'] = $data['upload_pic'];
            }

            $room_data['facilities'] = json_encode([
                'has_wifi'      => $data['has_wifi'] ? 1 : 0,
                'has_toilet'    => $data['has_toilet'] ? 1 : 0,
                'has_window'    => $data['has_window'] ? 1 : 0,
                'has_drink'     => $data['has_drink'] ? 1 : 0
            ]);

            $room_data['adder'] = session('user.id');

            $room_data['add_time'] = date('Y-m-d H:i:s');

            // 添加数据
            $room = Rooms::create($room_data);
            if($room->isEmpty() || $room->id <= 0){
                throw new Exception('添加房间失败，请重试！');
            }

            $this->commit();

            return ['status' => 1];

        }catch (Exception $e){
            $this->rollback();
            return ['status' => -1., 'msg' => $e->getMessage()];
        }
    }

    public function editRooms($data)
    {
        $this->startTrans();
        try {
            $validate = new RoomsValidate();
            if(!$validate->scene('addRoom')->check($data)){
                throw new Exception($validate->getError());
            }

            $room_data = [
                'name'          =>  $data['name'],
                'campus'        =>  $data['campus'],
                'bed_total'     =>  $data['bed_total']
            ];

            if($data['upload_pic']){
                if(count(explode('|', $data['upload_pic'])) > 5) throw new Exception('最多上传5张图片！');
                $room_data['pictures'] = $data['upload_pic'];
            }

            $room_data['facilities'] = json_encode([
                'has_wifi'      => $data['has_wifi'] === '1' ? 1 : 0,
                'has_toilet'    => $data['has_toilet'] === '1' ? 1 : 0,
                'has_window'    => $data['has_window'] === '1' ? 1 : 0,
                'has_drink'     => $data['has_drink'] === '1' ? 1 : 0
            ]);

            $room_data['adder'] = session('user.id');
            $room_data['id'] = $data['id'];

            // 修改房间数据
            $res = Db::name('rooms')->update($room_data);
            if($res == 0) throw new Exception('未作修改！');

            $this->commit();

            return ['status' => 1];

        }catch (Exception $e){
            $this->rollback();
            return ['status' => -1., 'msg' => $e->getMessage()];
        }
    }

    // 获取room列表
    public function getRooms()
    {
        $rooms = Rooms::with([
            'roomAdder' => function($query){
                $query->field('id, username');
            }
        ])->select();
        return $rooms;
    }

}