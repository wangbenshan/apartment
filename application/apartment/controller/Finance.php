<?php

namespace app\apartment\controller;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use think\Db;

/**
 * 财务管理
 * Class Orders
 * @package app\apartment\controller
 */
class Finance extends Base
{
    /**
     * 财务管理
     * @auth true
     */
    public function index()
    {
        if ($this->request->isGet()) {
            $this->title = '财务管理';

            $where = [];
            $where[] = ['status', 'in', [10, 20, 30]];
            $where[] = ['is_deleted', '=', 0];
            $start_time = $this->request->param('start_time');
            $end_time = $this->request->param('end_time');
            if(!empty($start_time)) $where[] = ['pay_time', '>= time', $start_time];
            if(!empty($end_time)) $where[] = ['pay_time', '<= time', $end_time.' 23:59:59'];

            $total_amount = Db::name('orders')->where($where)
                ->fieldRaw('SUM(`total_money`) as total_money,
                 SUM(`front_money`) as total_front_money,
                 SUM(`pay_money`) as total_pay_money,
                 SUM(`deposit`) as total_deposit,
                 SUM(`public_water_rate`) as total_water_money,
                 SUM(`actual_public_water_rate`) as total_actual_water_money')->find();
            $this->assign('total_amount', $total_amount);

            // 应退总额，已预订和已入住的
            $total_back = Db::name('orders')->where($where)
                ->where('status', 'in', [10, 20])->sum('deposit');
            $this->assign('total_back', $total_back);

            $this->_query(Db::name('orders'))->where($where)
                ->field('id, stu_name, salesman, total_money, front_money, pay_time, pay_money, deposit, public_water_rate, actual_public_water_rate')
                ->like('stu_name,salesman')->page();
        }
    }

    /**
     * 导出财务报表
     * @auth true
     */
    public function export()
    {
        $this->applyCsrfToken();
        if($this->request->isGet()){
            $where = [];
            $where[] = ['status', 'in', [10, 20, 30]];
            $where[] = ['is_deleted', '=', 0];

            // 获取订单列表
            $orders = Db::name('orders')->where($where)
                ->field('id, stu_name, salesman, total_money, front_money, pay_time, pay_money, deposit, public_water_rate, actual_public_water_rate')
                ->select();
            if(empty($orders)) $this->error('暂无订单可统计，导出失败！');

            // 各项统计
            $total_amount = Db::name('orders')->where($where)
                ->fieldRaw('SUM(`total_money`) as total_money,
                 SUM(`front_money`) as total_front_money,
                 SUM(`pay_money`) as total_pay_money,
                 SUM(`deposit`) as total_deposit,
                 SUM(`public_water_rate`) as total_water_money,
                 SUM(`actual_public_water_rate`) as total_actual_water_money')->find();

            // 应退总额，已预订和已入住的
            $total_back = Db::name('orders')->where($where)
                ->where('status', 'in', [10, 20])->sum('deposit');

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // 设置默认样式
            $sheet->getDefaultRowDimension()->setRowHeight(15.6);
            $sheet->getDefaultColumnDimension()->setWidth(16);

            //设置各项统计标题
            $sheet->mergeCells('A1:C1');
            $sheet->setCellValueByColumnAndRow(1, 1, '各项统计');
            $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            // 合并1、2列，填充各项统计数据
            $sheet->mergeCells('A2:B2');
            $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->setCellValueByColumnAndRow(1, 2, '学费总额');
            $sheet->setCellValueByColumnAndRow(3, 2, $total_amount['total_money']);
            $sheet->mergeCells('A3:B3');
            $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->setCellValueByColumnAndRow(1, 3, '定金总额');
            $sheet->setCellValueByColumnAndRow(3, 3, $total_amount['total_front_money']);
            $sheet->mergeCells('A4:B4');
            $sheet->getStyle('A4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->setCellValueByColumnAndRow(1, 4, '实交费用总额(含定金)');
            $sheet->setCellValueByColumnAndRow(3, 4, $total_amount['total_pay_money']);
            $sheet->mergeCells('A5:B5');
            $sheet->getStyle('A5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->setCellValueByColumnAndRow(1, 5, '押金总额');
            $sheet->setCellValueByColumnAndRow(3, 5, $total_amount['total_deposit']);
            $sheet->mergeCells('A6:B6');
            $sheet->getStyle('A6')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->setCellValueByColumnAndRow(1, 6, '应补缴费用总额');
            $sheet->setCellValueByColumnAndRow(3, 6, $total_amount['total_money'] - $total_amount['total_pay_money']);
            $sheet->mergeCells('A7:B7');
            $sheet->getStyle('A7')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->setCellValueByColumnAndRow(1, 7, '公共水电费总额');
            $sheet->setCellValueByColumnAndRow(3, 7, $total_amount['total_water_money']);
            $sheet->mergeCells('A8:B8');
            $sheet->getStyle('A8')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->setCellValueByColumnAndRow(1, 8, '实交公共水电费总额');
            $sheet->setCellValueByColumnAndRow(3, 8, $total_amount['total_actual_water_money']);
            $sheet->mergeCells('A9:B9');
            $sheet->getStyle('A9')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->setCellValueByColumnAndRow(1, 9, '应退总额');
            $sheet->setCellValueByColumnAndRow(3, 9, $total_back);


            //设置标题行内容
            $sheet->setCellValueByColumnAndRow(1, 11, '姓名');
            $sheet->setCellValueByColumnAndRow(2, 11, '业务员');
            $sheet->setCellValueByColumnAndRow(3, 11, '学费总金额');
            $sheet->setCellValueByColumnAndRow(4, 11, '定金');
            $sheet->setCellValueByColumnAndRow(5, 11, '已交学费(含定金)');
            $sheet->setCellValueByColumnAndRow(6, 11, '押金');
            $sheet->setCellValueByColumnAndRow(7, 11, '应补缴费用');
            $sheet->setCellValueByColumnAndRow(8, 11, '应缴公共水电费');
            $sheet->setCellValueByColumnAndRow(9, 11, '实缴公共水电费');
            $sheet->setCellValueByColumnAndRow(10, 11, '付款时间');

            foreach ($orders as $k => $v){
                $row = $k + 12;
                // 设置内容
                $sheet->setCellValueByColumnAndRow(1, $row, $v['stu_name']);
                $sheet->setCellValueByColumnAndRow(2, $row, $v['salesman']);
                $sheet->setCellValueByColumnAndRow(3, $row, $v['total_money']);
                $sheet->setCellValueByColumnAndRow(4, $row, $v['front_money']);
                $sheet->setCellValueByColumnAndRow(5, $row, $v['pay_money']);
                $sheet->setCellValueByColumnAndRow(6, $row, $v['deposit']);
                $sheet->setCellValueByColumnAndRow(7, $row, $v['total_money'] - $v['pay_money']);
                $sheet->setCellValueByColumnAndRow(8, $row, $v['public_water_rate']);
                $sheet->setCellValueByColumnAndRow(9, $row, $v['actual_public_water_rate']);
                $sheet->setCellValueByColumnAndRow(10, $row, $v['pay_time']);
            }

            $count = count($orders);
            $sheet->getStyle('A1:J'.($count + 10))->getFont()->setName('宋体')->setSize(12);
            $sheet->getStyle('A10:J'.($count + 10))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('A10:J10')->getFont()->setBold(true);
            $sheet->getColumnDimension('E')->setWidth(18);
            $sheet->getColumnDimension('J')->setWidth(24);

            $filename = '财务报表_'.date('YmdHis').'.xls';
            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment;filename="'.$filename.'"');
            header('Cache-Control: max-age=0');
            $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xls');

            ob_clean();
            $writer->save('php://output');
            exit;
        }
    }
}
