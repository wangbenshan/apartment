<?php

namespace app\apartment\validate;

use think\Validate;

class OrdersValidate extends Validate
{
    protected $rule = [
        'campus_id'                 =>  'require|number',
        'room_type_num'             =>  'require|number',
        'room_id'                   =>  'require|number',
        'stu_name'                  =>  'require|max:50',
        'sex'                       =>  'require|number',
        'stu_phone'                 =>  'require|mobile',
        'stu_id_num'                =>  'require|idCard',
        'book_in_time'              =>  'require|date',
        'departure_time'            =>  'require|date',
        'public_water_rate'         =>  'float',
        'actual_public_water_rate'  =>  'float',
        'total_money'               =>  'require|float',

        'front_money'               =>  'require|float',

        'deposit'                   =>  'require|float',
        'pay_money'                 =>  'require|float',

        'back_reposit'              =>  'require|float',
        'back_study_money'          =>  'require|float',
        'back_public_money'         =>  'require|float',
    ];

    /**
     * 定义错误信息
     * 格式：'字段名.规则名'	=>	'错误信息'
     *
     * @var array
     */
    protected $message = [
        'campus_id.require'      =>  '请选择校区',
        'campus_id.number'       =>  '请选择校区',
        'room_type_num.require'  =>  '请选择房间规格',
        'room_type_num.number'   =>  '请选择房间规格',
        'room_id.require'        =>  '请选择房间',
        'room_id.number'         =>  '请选择房间',
        'stu_name.require'       =>  '请输入学生姓名',
        'stu_name.max'           =>  '学生姓名最多50个字符',
        'sex.require'            =>  '请选择性别',
        'sex.number'             =>  '请选择性别',
        'stu_phone.require'      =>  '请输入身份证号',
        'stu_phone.mobile'       =>  '手机号格式错误',
        'stu_id_num.require'     =>  '请输入身份证号',
        'stu_id_num.idCard'      =>  '身份证号格式错误',
        'book_in_time.require'   =>  '请选择入住时间',
        'book_in_time.date'      =>  '入住时间格式错误',
        'departure_time.require' =>  '请选择离店时间',
        'departure_time.date'    =>  '离店时间格式错误',
        'public_water_rate.float'             =>  '请输入正确的公共水电费金额',
        'actual_public_water_rate.float'      =>  '请输入正确的公共水电费实缴金额',
        'total_money.require'    =>  '请输入订单金额',
        'total_money.float'      =>  '订单金额格式错误',
        'front_money.require'    =>  '请输入定金',
        'front_money.float'      =>  '定金格式错误',
        'deposit.require'        =>  '请输入押金',
        'deposit.float'          =>  '押金格式错误',
        'pay_money.require'      =>  '请输入实付金额',
        'pay_money.float'        =>  '实付总额格式错误',

        'back_deposit.require'         =>  '请输入所退押金',
        'back_deposit.float'           =>  '押金格式错误',
        'back_study_money.require'     =>  '请输入所退学费',
        'back_study_money.float'       =>  '学费格式错误',
        'back_public_money.require'    =>  '请输入所退公共水电费',
        'back_public_money.float'      =>  '水电费格式错误',
    ];

    public function sceneReserve()
    {
        return $this->only(['campus_id', 'room_type_num', 'room_id', 'stu_name', 'sex', 'stu_phone',
            'book_in_time', 'departure_time', 'public_water_rate', 'actual_public_water_rate', 'total_money', 'front_money'])
            ->remove('room_id', 'require');
    }

    public function sceneAdd()
    {
        return $this->only(['campus_id', 'room_type_num', 'room_id', 'stu_name', 'sex', 'stu_phone',
            'stu_id_num', 'book_in_time', 'departure_time', 'public_water_rate', 'actual_public_water_rate', 'total_money', 'deposit', 'pay_money']);
    }

    public function sceneEdit()
    {
        return $this->only(['campus_id', 'room_type_num', 'stu_name', 'sex', 'stu_phone',
            'book_in_time', 'departure_time', 'public_water_rate', 'actual_public_water_rate', 'total_money', 'pay_money']);
    }

    public function sceneHandleReserve()
    {
        return $this->only(['campus_id', 'room_type_num', 'room_id', 'public_water_rate', 'actual_public_water_rate', 'power_rate_cycle', 'deposit', 'actual_rest_money']);
    }

    public function sceneHandleReserveForRoom()
    {
        return $this->only(['public_water_rate', 'actual_public_water_rate', 'power_rate_cycle', 'deposit', 'actual_rest_money']);
    }

    public function sceneChangeRoom()
    {
        return $this->only(['campus_id', 'room_type_num', 'room_id', 'book_in_time', 'departure_time', 'total_money']);
    }

    public function sceneCheckout()
    {
        return $this->only(['back_deposit', 'back_study_money', 'back_public_money']);
    }
}
