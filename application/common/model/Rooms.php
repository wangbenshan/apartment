<?php

namespace app\common\model;

use think\Model;

class Rooms extends Model
{
    protected $name = 'rooms';

    protected $pk = 'id';

    protected $json = ['facilities'];

    // 房间的添加者
    public function roomAdder()
    {
        return $this->belongsTo(SystemUser::class, 'adder', 'id');
    }
}
