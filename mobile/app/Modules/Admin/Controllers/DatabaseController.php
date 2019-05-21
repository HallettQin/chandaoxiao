<?php

namespace App\Modules\Admin\Controllers;

use Think\Db;
use App\Modules\Base\Controllers\BackendController;

class DatabaseController extends BackendController
{

    /**
     * 更新字段默认值页面
     * @return
     */
    public function actionIndex()
    {
        /* 检查权限 */
        $this->admin_priv('db_backup');

        $obj = Db::getInstance(C()); // $obj = M()->db();

        $cache_id = md5('tables' . C('DB_NAME'));
        $tables = S($cache_id);
        if ($tables === false) {
            $tables = $obj->getTables();
            S($cache_id, $tables);
        }

        foreach ($tables as $key => $table_name) {
            $status = 0;
            $fields = $obj->getFields($table_name);
            foreach ($fields as $val) {
                // 排除自增主键, 有NOT NULL 且 没有default默认值 的字段
                if ($val['primary'] == false && $val['autoinc'] == false && $val['notnull'] == true && $val['default'] === null) {
                    $status = 1;
                }
            }
            $new_tables[] = ['table_name' => $table_name, 'status' => $status];
        }

        $this->assign('tables', $new_tables);
        $this->display();
    }

    /**
     * 更新所有数据表的字段默认值
     * @param 排除自增主键 , 有NOT NULL 且 没有default默认值 的字段
     * @return
     */
    public function actionAll()
    {
        $table_num = 0;
        $field_num = 0;

        $obj = Db::getInstance(C()); // $obj = M()->db();

        $cache_id = md5('tables' . C('DB_NAME'));
        $tables = S($cache_id);
        if ($tables === false) {
            $tables = $obj->getTables();
            S($cache_id, $tables);
        }

        foreach ($tables as $table_name) {
            $fields = $obj->getFields($table_name);
            $field_info = $this->getFieldInfo($table_name);
            $fields = array_merge_recursive($field_info, $fields);
            // echo '<hr>' . $table_name . '<hr>';
            foreach ($fields as $val) {
                // 排除自增主键, 有NOT NULL 且 没有default默认值 的字段
                if ($val['primary'] == false && $val['autoinc'] == false && $val['notnull'] == true && $val['default'] === null) {
                    $this->editField($table_name, $val);
                    $field_num++;
                }
            }
            $table_num++;
        }
        $message = $field_num > 0 ? '成功更新 ' . $table_num . '个表，共' . $field_num . '条字段！' : '没有字段需要更新';
        $this->message($message, url('index')); //目前最新2.0版本数据库统计大约共有254个表，453个字段
    }

    /**
     * 更新指定一个数据表的字段默认值
     * @param 排除自增主键 , 有NOT NULL 且 没有default默认值 的字段
     * @return
     */
    public function actionOne()
    {
        $table_name = I('get.table', '', 'trim');

        $field_num = 0;

        $obj = Db::getInstance(C()); // $obj = M()->db();

        $fields = $obj->getFields($table_name);
        $field_info = $this->getFieldInfo($table_name);
        $fields = array_merge_recursive($field_info, $fields);
        // echo '<hr>' . $table_name . '<hr>';
        foreach ($fields as $val) {
            // 排除自增主键, 有NOT NULL 且 没有default默认值 的字段
            if ($val['primary'] == false && $val['autoinc'] == false && $val['notnull'] == true && $val['default'] === null) {
                $this->editField($table_name, $val);
                $field_num++;
            }
        }
        $message = $field_num > 0 ? '成功更新 ' . $table_name . '表' . $field_num . '条字段！' : '没有字段需要更新';
        $this->message($message, url('index'));
        // exit($message);
    }

    /**
     * 修改字段默认值
     * @param  table  表名
     * @param  val   字段信息
     * @return
     */
    private function editField($table, $val)
    {
        $sql = "ALTER TABLE `$table` MODIFY COLUMN `$val[name]` ";
        // 拼接sql
        $sql .= $this->filterFieldInfo($val);
        // return $sql;
        return M()->execute($sql);
    }

    /**
     * 字段信息数组处理
     * @param  val ['type']   字段类型
     * @param  val ['comment']   字段备注
     * @return
     */
    private function filterFieldInfo($val)
    {
        // 区分字段类型
        if (strpos($val['type'], 'int') !== false || strpos($val['type'], 'decimal') !== false || strpos($val['type'], 'float') !== false || strpos($val['type'], 'time') !== false) {
            $sql .= $val['type'] . " NOT NULL DEFAULT 0 ";
        }
        if (strpos($val['type'], 'varchar') !== false || strpos($val['type'], 'char') !== false) {
            $sql .= $val['type'] . " NOT NULL DEFAULT '' ";
        }
        if (strpos($val['type'], 'text') !== false) {
            $sql .= $val['type'] . " ";
        }
        // 注释
        $val['comment'] = empty($val['comment']) ? '' : " COMMENT '" . $val['comment'] . "' ";
        $sql .= $val['comment']; // int(10) NOT NULL DEFAULT '0' comment 'id'
        return $sql;
    }

    /**
     * 获取字段注释信息
     * @param $table
     * @return array
     */
    private function getFieldInfo($table)
    {
        $sql = "SELECT column_name,column_comment FROM information_schema.columns WHERE table_name = '" . $table . "' AND table_schema = '" . C('db_name') . "'";

        $result = M()->query($sql);
        $info = [];
        foreach ($result as $key => $val) {
            $info[$val['column_name']] = [
                'comment' => $val['column_comment']
            ];
        }
        return $info;
    }
}
