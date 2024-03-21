<?php
/**
 * @filesource modules/bp/models/history.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Bp\history;

use Gcms\Login;
use Kotchasan\Database\Sql;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * module=bp-History
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * Query ข้อมูลสำหรับส่งให้กับ DataTable
     *
     * @param array $params
     *
     * @return \Kotchasan\Database\QueryBuilder
     */
    public static function toDataTable($params)
    {
        $where = array(
            array('P.family_id', $params['family_id']),
            array('P.member_id', $params['member_id'])
        );
        if ($params['tag'] > 0) {
            $where[] = array('P.tag', $params['tag']);
        }
        if (!empty($params['from'])) {
            $where[] = array(Sql::DATE('P.create_date'), '>=', $params['from']);
        }
        if (!empty($params['to'])) {
            $where[] = array(Sql::DATE('P.create_date'), '<=', $params['to']);
        }
        return static::createQuery()
            ->select(
                'P.id', 'P.create_date', 'A.sys sys1', 'B.sys sys2',
                'A.dia dia1', 'B.dia dia2', 'A.pulse pulse1', 'B.pulse pulse2',
                'P.height', 'P.weight', '0 bmi', 'P.waist', 'P.temperature', 'P.tag'
            )
            ->from('bp P')
            ->join('bp_items A', 'LEFT', array(array('A.bp_id', 'P.id'), array('A.index', 1)))
            ->join('bp_items B', 'LEFT', array(array('B.bp_id', 'P.id'), array('B.index', 2)))
            ->where($where)
            ->groupBy('P.id');
    }

    /**
     * รับค่าจาก action (history.php)
     *
     * @param Request $request
     */
    public function action(Request $request)
    {
        $ret = [];
        // session, referer, สมาชิก
        if ($request->initSession() && $request->isReferer() && $login = Login::isMember()) {
            // รับค่าจากการ POST
            $action = $request->post('action')->toString();
            // id ที่ส่งมา
            if (preg_match_all('/,?([0-9]+),?/', $request->post('id')->toString(), $match)) {
                // Database
                $db = $this->db();
                // ตาราง
                $table_bp = $this->getTableName('bp');
                $table_items = $this->getTableName('bp_items');
                if ($action === 'delete') {
                    // ลบ ตรวจสอบสิทธิ์
                    $search = $db->select($table_bp, array(
                        array('id', $match[1]),
                        array('member_id', $login['id'])
                    ));
                    $family_id = 0;
                    $bp_ids = [];
                    foreach ($search as $item) {
                        $family_id = $item['family_id'];
                        $bp_ids[] = $item['id'];
                    }
                    if (!empty($bp_ids)) {
                        // ลบ bp
                        $db->delete($table_bp, array('id', $bp_ids), 0);
                        // ลบ bp_items
                        $db->delete($table_items, array('bp_id', $bp_ids), 0);
                        // อัปเดทค่าเฉลี่ยความดัน
                        \Bp\Calculator\Model::avg($family_id, $login['id']);
                    }
                    // reload
                    $ret['location'] = 'reload';
                }
            }
        }
        if (empty($ret)) {
            $ret['alert'] = Language::get('Unable to complete the transaction');
        }
        // คืนค่า JSON
        echo json_encode($ret);
    }
}
