<?php

namespace app\apartment\validate;

use think\Validate;

class RoomsValidate extends Validate
{ 
    protected $rule = [
        'name'              =>  'require|max:20',
        'campus'            =>  'require|number',
        'floor'             =>  'require|number',
        'bed_total'         =>  'require|number',
        'face'              =>  'require|number'
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
        'floor.require'          =>  '请输入楼层数',
        'floor.number'           =>  '楼层数是一个数字',
        'bed_total.require'      =>  '请选择房间规格',
        'bed_total.number'       =>  '请选择房间规格',
        'face.require'           =>  '请选择房间朝向',
        'face.number'            =>  '请选择房间朝向',
    ];

    public function sceneAddRoom()
    {
        return $this->only(['name', 'campus', 'floor', 'bed_total', 'face']);
    }
}
