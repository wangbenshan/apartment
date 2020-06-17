<?php

namespace app\apartment\controller;

use app\common\model\SystemUser;
use app\common\service\RoomService;
use library\Controller;
use app\common\model\Campus as CampusModel;
use think\Db;

/**
 * 校区管理
 * Class Campus
 * @package app\apartment\controller
 */
class Campus extends Controller
{
    /**
     * 默认数据模型
     * @var string
     */
    public $table = 'Campus';

    /**
     * 校区列表
     * @auth true
     */
    public function index()
    {
        if($this->request->isGet()){
            $this->title = '校区列表';

            $sub_query = Db::name('campus_users')->where('status', 1)
                ->group('campus_id')->fieldRaw('*, count(*) as count')->buildSql();

            $campus = Db::name('campus')->alias('c')
                ->leftJoin([$sub_query => 'cu'], 'c.id = cu.campus_id')
                ->where('c.status', 1)->fieldRaw('c.*, cu.count')->select();

            $this->assign('list', $campus);
            $this->fetch();
        }
    }

    /**
     * 绑定教务老师
     * @auth true
     */
    public function bindTeacher()
    {
        $this->applyCsrfToken();
        $id = $this->request->param('id');
        $campus = RoomService::getCampus($id);
        if($campus->isEmpty()) $this->error('校区不存在！');
        if($this->request->isGet()){
            $this->title = "绑定老师";

            // 获取未绑定的系统用户
            $sql = 'SELECT id, username from ap_system_user as su 
                    WHERE (SELECT count(1) as num from ap_campus_users as cu where su.id = cu.user_id and cu.status = 1) = 0
                    and `is_deleted` = 0 and `username` <> "admin"';
            $users = Db::query($sql);

            $this->assign('users', $users);
            $this->assign('campus', $campus);
            $this->fetch();
        }else{
            $data = [];
            $data['campus_id'] = $id;
            $data['user_id'] = $this->request->post('user_id');
            // 检查是否绑定
            $count = Db::name('campus_users')->where([
                ['campus_id', '=', $id],
                ['user_id', '=', $data['user_id']],
                ['status', '=', 1]
            ])->count();
            if($count != 0) $this->error('已经绑定过了');

            $res = Db::name('campus_users')->insert($data);
            if($res === false) $this->error('绑定失败，请重试！');
            $this->success('绑定成功！');
        }
    }

    /**
     * 查看绑定
     * @auth true
     */
    public function viewBind()
    {
        if($this->request->isGet()){
            $this->title = "绑定老师";

            $id = $this->request->get('id');
            $campus = RoomService::getCampus($id);
            if($campus->isEmpty()) $this->error('校区不存在！');

            // 获取已绑定的系统用户
            $users = Db::name('campus_users')->alias('cu')
                ->leftJoin(['ap_system_user' => 'su'], 'cu.user_id = su.id')
                ->where([
                    ['cu.status', '=', 1],
                    ['cu.campus_id', '=', $campus->id],
                    ['su.is_deleted', '=', 0],
                ])->field('cu.id, su.username')->select();

            $this->assign('list', $users);
            $this->fetch();
        }
    }

    /**
     * 解除绑定
     * @auth true
     */
    public function forbid()
    {
        $this->applyCsrfToken();
        $this->_save('CampusUsers', ['status' => '0'], '', ['id' => $this->request->param('id')]);
    }
}
