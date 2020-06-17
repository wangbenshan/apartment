<?php

namespace app\apartment\validate;

use think\Validate;

class OrdersValidate extends Validate
{
    protected $rule = [
        'campus_id'                 =>  'require|number',
        'room_type_num'             =>  'require|number',
        'room_id'                   =>  'require|number',
        'bed_num'                   =>  'require|number',
        'stu_name'                  =>  'require|max:50',
        'sex'                       =>  'require|number',
        'stu_phone'                 =>  'require|mobile',
        'stu_id_num'                =>  'require|idCard',
        'book_in_time'              =>  'require|date',
        'departure_time'            =>  'require|date',
        'public_water_rate'         =>  'number',
        'actual_public_water_rate'  =>  'number',
        'total_money'               =>  'require|number',

        'front_money'               =>  'require|number',

        'deposit'                   =>  'require|number',
        'pay_money'                 =>  'require|number',
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
        'bed_num.require'        =>  '请选择床位',
        'bed_num.number'         =>  '请选择床位',
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
        'public_water_rate.number'             =>  '请输入正确的公共水电费金额',
        'actual_public_water_rate.number'      =>  '请输入正确的公共水电费实缴金额',
        'total_money.require'    =>  '请输入订单金额',
        'total_money.number'     =>  '订单金额格式错误',
        'front_money.require'    =>  '请输入定金',
        'front_money.number'     =>  '定金格式错误',
        'deposit.require'        =>  '请输入押金',
        'deposit.number'         =>  '押金格式错误',
        'pay_money.require'      =>  '请输入实付金额',
        'pay_money.number'       =>  '实付总额格式错误',
    ];

    public function sceneReserve()
    {
        return $this->only(['campus_id', 'room_type_num', 'room_id', 'stu_name', 'sex', 'stu_phone',
            'stu_id_num', 'stu_id_num', 'book_in_time', 'departure_time', 'public_water_rate', 'actual_public_water_rate', 'total_money', 'front_money'])
            ->remove('room_id', 'require');
    }

    public function sceneAdd()
    {
        return $this->only(['campus_id', 'room_type_num', 'room_id', 'bed_num', 'stu_name', 'sex', 'stu_phone',
            'stu_id_num', 'stu_id_num', 'book_in_time', 'departure_time', 'public_water_rate', 'actual_public_water_rate', 'total_money', 'deposit', 'pay_money']);
    }

    public function sceneHandleReserve()
    {
        return $this->only(['campus_id', 'room_type_num', 'room_id', 'bed_num', 'deposit', 'actual_rest_money']);
    }

    public function sceneHandleReserveForBed()
    {
        return $this->only(['deposit', 'actual_rest_money']);
    }

    public function sceneChangeRoom()
    {
        return $this->only(['campus_id', 'room_type_num', 'room_id', 'book_in_time', 'departure_time', 'total_money']);
    }
}
