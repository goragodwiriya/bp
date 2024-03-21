<?php
/**
 * @filesource modules/bp/models/record.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Bp\Record;

use Gcms\Login;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * module=bp-record
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * อ่านข้อมูล ที่ $id
     * 0 หมายถึงรายการใหม่
     * คืนค่า null ถ้าไม่พบหรือไม่มีสิทธิ์
     *
     * @param int $id
     * @param int $family_id
     * @param array $login
     *
     * @return object|null
     */
    public static function get($id, $family_id, $login)
    {
        if ($login) {
            if ($id > 0) {
                // แก้ไข, อ่านรายการที่เลือก
                return static::createQuery()
                    ->from('bp P')
                    ->join('family F', 'INNER', array(array('F.id', 'P.family_id'), array('F.member_id', 'P.member_id')))
                    ->join('bp_items A', 'LEFT', array(array('A.bp_id', 'P.id'), array('A.index', 1)))
                    ->join('bp_items B', 'LEFT', array(array('B.bp_id', 'P.id'), array('B.index', 2)))
                    ->where(array(
                        array('P.id', $id),
                        array('P.member_id', $login['id'])
                    ))
                    ->first('P.*', 'F.name', 'A.sys sys1', 'B.sys sys2', 'A.dia dia1',
                        'B.dia dia2', 'A.pulse pulse1', 'B.pulse pulse2');
            } else {
                // ใหม่
                return static::createQuery()
                    ->from('family')
                    ->where(array(
                        array('id', $family_id),
                        array('member_id', $login['id'])
                    ))
                    ->first('0 id', 'name', 'member_id', 'id family_id', 'height');
            }
        }
        return null;
    }

    /**
     * บันทึกข้อมูล (record.php)
     *
     * @param Request $request
     */
    public function submit(Request $request)
    {
        $ret = [];
        // session, token, member
        if ($request->initSession() && $request->isSafe() && $login = Login::isMember()) {
            try {
                // รับค่าจากการ POST
                $save = array(
                    'tag' => $request->post('write_tag')->toInt(),
                    'height' => $request->post('write_height')->toFloat(),
                    'weight' => $request->post('write_weight')->toFloat(),
                    'temperature' => $request->post('write_temperature')->toFloat(),
                    'waist' => $request->post('write_waist')->toFloat(),
                    'create_date' => $request->post('write_create_date')->date(),
                    'family_id' => $request->post('write_family_id')->toInt()
                );
                $items = array(
                    1 => array(
                        'sys' => $request->post('write_sys1')->toInt(),
                        'dia' => $request->post('write_dia1')->toInt(),
                        'pulse' => $request->post('write_pulse1')->toInt()
                    ),
                    2 => array(
                        'sys' => $request->post('write_sys2')->toInt(),
                        'dia' => $request->post('write_dia2')->toInt(),
                        'pulse' => $request->post('write_pulse2')->toInt()
                    )
                );
                // รายการที่เลือก
                $index = self::get($request->post('write_id')->toInt(), $save['family_id'], $login);
                if ($index && $index->member_id == $login['id']) {
                    // บันทึกรายการใหม่
                    foreach ($items as $i => $item) {
                        if ($item['sys'] > 0 || $item['dia'] > 0 || $item['pulse'] > 0) {
                            if (empty($item['sys'])) {
                                $ret['ret_write_sys'.$i] = 'Please fill in';
                            }
                            if (empty($item['dia'])) {
                                $ret['ret_write_dia'.$i] = 'Please fill in';
                            }
                            if (empty($item['pulse'])) {
                                $ret['ret_write_pulse'.$i] = 'Please fill in';
                            }
                        }
                    }
                    if (empty($save['create_date'])) {
                        $ret['ret_write_create_date'] = 'Please fill in';
                    }
                    if (empty($save['tag'])) {
                        $ret['ret_write_tag'] = 'Please select';
                    }
                    if (empty($ret)) {
                        // Database
                        $db = $this->db();
                        if ($index->id == 0) {
                            $save['member_id'] = $login['id'];
                            $index->id = $db->insert($this->getTableName('bp'), $save);
                        } else {
                            $db->update($this->getTableName('bp'), $index->id, $save);
                        }
                        // bp_items
                        $table = $this->getTableName('bp_items');
                        // ลบรายการเดิม
                        $db->delete($table, array('bp_id', $index->id), 0);
                        // บันทึกรายการใหม่
                        foreach ($items as $i => $item) {
                            if ($item['sys'] > 0 && $item['dia'] > 0 && $item['pulse'] > 0) {
                                $item['bp_id'] = $index->id;
                                $item['index'] = $i;
                                $db->insert($table, $item);
                            }
                        }
                        // อัปเดทค่าเฉลี่ยความดัน
                        \Bp\Calculator\Model::avg($save['family_id'], $login['id']);
                        // คืนค่า
                        $ret['alert'] = Language::get('Saved successfully');
                        $ret['location'] = $request->getUri()->postBack('index.php', array('module' => 'bp-history', 'id' => $save['family_id']));
                        // เคลียร์
                        $request->removeToken();
                    }
                }
            } catch (\Kotchasan\InputItemException $e) {
                $ret['alert'] = $e->getMessage();
            }
        }
        if (empty($ret)) {
            // ไม่มีสิทธิ์
            $ret['alert'] = Language::get('Can not be performed this request. Because they do not find the information you need or you are not allowed');
        }
        // คืนค่าเป็น JSON
        echo json_encode($ret);
    }
}
