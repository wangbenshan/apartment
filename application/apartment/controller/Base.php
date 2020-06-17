<?php

namespace app\apartment\controller;

use library\Controller;
use think\Db;

class Base extends Controller
{
    // 教务老师
    const SYSTEM_AUTH_TEACHER = 1;

    // 业务员
    const SYSTEM_AUTH_SALESMAN = 2;

    public function initialize()
    {
        parent::initialize();

        $user = session('user');
        // 获取身份
        $auth = explode(',', session('user.authorize'));
        // 如果是教务老师
        if(in_array(self::SYSTEM_AUTH_TEACHER, $auth) && empty($user['bind_campus'])){
            // 检查教务老师是否绑定了校区
            $campus_id = Db::name('campus_users')->where([
                ['status', '=', 1],
                ['user_id', '=', $user['id']]
            ])->value('campus_id');
            if(empty($campus_id)) $this->error('您尚未绑定校区，请联系管理员进行绑定！');

            // 获取campus
            $campus = Db::name('campus')->get($campus_id);
            if(empty($campus)) $this->error('您尚未绑定校区，请联系管理员进行绑定！');

            $user['bind_campus'] = $campus;
            $this->app->session->set('user', $user);
        }
        // 如果是业务员
        elseif(in_array(self::SYSTEM_AUTH_SALESMAN, $auth)){

        }
    }
}
