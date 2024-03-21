<?php
/**
 * @filesource modules/bp/models/profile.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Bp\Profile;

use Gcms\Login;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * module=bp-profile
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * อ่านข้อมูลสมาชิกที่ $id
     * คืนค่าข้อมูล array ไม่พบคืนค่า false
     * $id = 0 ข้อมูลใหม่
     *
     * @param int $id
     * @param array $login
     *
     * @return object|bool
     */
    public static function get($id, $login)
    {
        if ($login) {
            if (empty($id)) {
                return (object) array(
                    'id' => 0,
                    'member_id' => $login['id']
                );
            } else {
                return static::createQuery()
                    ->from('family')
                    ->where(array(
                        array('id', $id),
                        array('member_id', $login['id'])
                    ))
                    ->first();
            }
        }
        return false;
    }

    /**
     * บันทึกข้อมูล (profile.php)
     *
     * @param Request $request
     */
    public function submit(Request $request)
    {
        $ret = [];
        // session, token, สมาชิก
        if ($request->initSession() && $request->isSafe() && $login = Login::isMember()) {
            try {
                // รับค่าจากการ POST
                $save = array(
                    'name' => $request->post('register_name')->topic(),
                    'sex' => $request->post('register_sex')->filter('a-z'),
                    'height' => $request->post('register_height')->toFloat(),
                    'id_card' => $request->post('register_id_card')->number(),
                    'birthday' => $request->post('register_birthday')->date(),
                    'phone' => $request->post('register_phone')->number(),
                    'address' => $request->post('register_address')->topic(),
                    'country' => $request->post('register_country')->filter('A-Z'),
                    'provinceID' => $request->post('register_provinceID')->number(),
                    'province' => $request->post('register_province')->topic(),
                    'zipcode' => $request->post('register_zipcode')->number()
                );
                // ตรวจสอบค่าที่ส่งมา
                $user = self::get($request->post('register_id')->toInt(), $login);
                if ($user && $user->member_id == $login['id']) {
                    if ($save['name'] == '') {
                        // ไม่ได้กรอก ชื่อ
                        $ret['ret_register_name'] = 'Please fill in';
                    }
                    // บันทึก
                    if (empty($ret)) {
                        if ($user->id == 0) {
                            // ใหม่
                            $save['member_id'] = $user->member_id;
                            $save['create_date'] = date('Y-m-d H:i:s');
                            $this->db()->insert($this->getTableName('family'), $save);
                        } else {
                            // แก้ไข
                            $this->db()->update($this->getTableName('family'), $user->id, $save);
                        }
                        // ไปหน้าเดิม แสดงรายการ
                        $ret['location'] = $request->getUri()->postBack('index.php', array('module' => 'bp-family', 'id' => null));
                        // คืนค่า
                        $ret['alert'] = Language::get('Saved successfully');
                        // เคลียร์
                        $request->removeToken();
                    }
                }
            } catch (\Kotchasan\InputItemException $e) {
                $ret['alert'] = $e->getMessage();
            }
        }
        if (empty($ret)) {
            $ret['alert'] = Language::get('Unable to complete the transaction');
        }
        // คืนค่าเป็น JSON
        echo json_encode($ret);
    }
}
