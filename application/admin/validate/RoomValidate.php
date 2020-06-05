<?php

namespace app\admin\validate;

use think\Validate;

class RoomValidate extends Validate
{ 
    protected $rule = [
        'name'              =>  'require|max:20',
        'campus'            =>  'require|number',
        'bed_total'         =>  'require|number'
    ];
    
    /**
     * 定义错误信息
     * 格式：'字段名.规则名'	=>	'错误信息'
     *
     * @var array
     */	
    protected $message = [
        'name.require'           =>  '请输入房间名称',
        'name.max'               =>  '房间名称最多不超过20个字符',
        'campus.require'         =>  '请选择校区',
        'campus.number'          =>  '请选择校区',
        'bed_total.require'      =>  '请选择房间规格',
        'bed_total.max'          =>  '请选择房间规格',
    ];

    public function sceneAddRoom()
    {
        return $this->only(['name', 'campus', 'bed_total']);
    }
}
