<?php

namespace app\common\model;

use think\Model;

class Beds extends Model
{
    protected $name = 'beds';

    protected $pk = 'id';

    public function bedAdder()
    {
        return $this->belongsTo(SystemUser::class, 'adder', 'id');
    }
}
