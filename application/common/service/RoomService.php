<?php
namespace app\common\service;

use app\apartment\validate\RoomsValidate;
use app\common\model\Campus;
use app\common\model\Orders;
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
    public static function getRoomType($type = null, $text = false)
    {
        if($type){
            if($text){
                switch ($type){
                    case '一人间':
                    case '1人间':
                        return 1;
                    case '二人间':
                    case '2人间':
                        return 2;
                    case '四人间':
                    case '4人间':
                        return 4;
                    case '六人间':
                    case '6人间':
                        return 6;
                    case '八人间':
                    case '8人间':
                        return 8;
                    case '十人间':
                    case '10人间':
                        return 10;
                }
            }else{
                switch ($type){
                    case 1:  return '一人间';
                    case 2:  return '二人间';
                    case 4:  return '四人间';
                    case 6:  return '六人间';
                    case 8:  return '八人间';
                    case 10: return '十人间';
                }
            }
        }else{
            return [
                ['num' => 1,    'text' => '一人间', 'alias' => '1人间'],
                ['num' => 2,    'text' => '二人间', 'alias' => '2人间'],
                ['num' => 4,    'text' => '四人间', 'alias' => '4人间'],
                ['num' => 6,    'text' => '六人间', 'alias' => '6人间'],
                ['num' => 8,    'text' => '八人间', 'alias' => '8人间'],
                ['num' => 10,   'text' => '十人间', 'alias' => '10人间']
            ];
        }
    }

    public function addRooms($data)
    {
        $this->startTrans();
        try {
            $validate = new RoomsValidate();
            if(!$validate->scene('addRoom')->check($data)){
                throw new Exception($validate->getError());
            }

            // 检查是否已设置
            $count = Rooms::where([
                ['campus', '=', $data['campus']],
                ['name', '=', $data['name']]
            ])->findOrEmpty();
            if(!$count->isEmpty()) throw new Exception('该校区的这个房间名已被占用');

            $room_data = [
                'name'          =>  $data['name'],
                'campus'        =>  $data['campus'],
                'floor'         =>  $data['floor'],
                'bed_total'     =>  $data['bed_total'],
                'face'          =>  $data['face'],
//                'price'         =>  $data['price'],
                'facilities'    =>  $data['facilities'],
                'adder'         =>  session('user.id'),
                'add_time'      =>  date('Y-m-d H:i:s'),
            ];

            if($data['upload_pic']){
                if(count(explode('|', $data['upload_pic'])) > 5) throw new Exception('最多上传5张图片！');
                $room_data['pictures'] = $data['upload_pic'];
            }

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
                'floor'         =>  $data['floor'],
                'bed_total'     =>  $data['bed_total'],
                'face'          =>  $data['face'],
//                'price'         =>  $data['price'],
                'facilities'    =>  $data['facilities'],
                'adder'         =>  session('user.id')
            ];

            if($data['upload_pic']){
                if(count(explode('|', $data['upload_pic'])) > 5) throw new Exception('最多上传5张图片！');
                $room_data['pictures'] = $data['upload_pic'];
            }

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

    public function getAvailableRoomsByCampus($campus_id = '', $room_type = '')
    {
        $where_str = 'r.`status` = 1';
        if($campus_id) $where_str .= ' AND r.campus = '.$campus_id;
        if($room_type) $where_str .= ' AND r.bed_total = '.$room_type;
        $sql = 'SELECT rr.*,oo.o_count from ap_rooms as rr
                LEFT JOIN (
                    SELECT r.id as room_id,count(o.id) o_count from ap_rooms as r
                    LEFT JOIN (SELECT id,room_id from ap_orders WHERE `status` in (10,20) ) as o on r.id = o.room_id
                    WHERE '.$where_str.' GROUP BY r.id
                ) as oo on rr.id = oo.room_id WHERE rr.bed_total > oo.o_count';
        $rooms = Db::query($sql);
        return empty($rooms) ? [] : $rooms;
    }
}