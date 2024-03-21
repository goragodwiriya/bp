<?php
/**
 * @filesource modules/bp/models/family.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Bp\Family;

use Gcms\Login;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * module=bp-family
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * อ่านข้อมูลสำหรับใส่ลงในตาราง
     *
     * @param array $params
     *
     * @return \Kotchasan\Database\QueryBuilder
     */
    public static function toDataTable($params)
    {
        $where = array(
            array('member_id', $params['member_id'])
        );
        return static::createQuery()
            ->select('id', 'name', 'sex', 'phone', 'birthday', 'height', 'sys', 'dia', 'bmi', 'create_date', 'favorite')
            ->from('family')
            ->where($where);
    }

    /**
     * ตารางสมาชิกในครอบครัว (family.php)
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
                $table_family = $this->getTableName('family');
                if ($action === 'delete') {
                    // ลบ family
                    $db->delete($table_family, array(
                        array('id', $match[1]),
                        array('member_id', $login['id'])
                    ), 0);
                    // ตรวจสอบสิทธิ์ (bp)
                    $search = $db->select($table_bp, array(
                        array('family_id', $match[1]),
                        array('member_id', $login['id'])
                    ));
                    $bp_ids = [];
                    foreach ($search as $item) {
                        $bp_ids[] = $item['id'];
                    }
                    if (!empty($bp_ids)) {
                        // ลบ bp
                        $db->delete($table_bp, array('id', $bp_ids), 0);
                        // ลบ bp_items
                        $db->delete($table_items, array('bp_id', $bp_ids), 0);
                    }
                    // reload
                    $ret['location'] = 'reload';
                } elseif ($action === 'favorite') {
                    // favorite
                    $index = $db->first($table_family, (int) $match[1][0]);
                    if ($index) {
                        $favorite = $index->favorite == 1 ? 0 : 1;
                        $db->update($table_family, $index->id, array('favorite' => $favorite));
                        // คืนค่า
                        $ret['elem'] = 'favorite_'.$index->id;
                        $lng = Language::get('FAVORITE_TITLE');
                        $ret['title'] = $lng[$favorite];
                        $ret['class'] = 'icon-valid '.($favorite ? 'success' : 'disabled');
                    }
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
