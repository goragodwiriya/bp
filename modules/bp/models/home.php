<?php
/**
 * @filesource modules/bp/models/home.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Bp\Home;

/**
 * module=bp-home
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * ฟังก์ชั่นอ่านรายชื่อสมาชิกในครอบครัวทั้งหมด
     * ที่สามารถแสดงในหน้า Dashboard ได้
     *
     * @param int $member_id
     *
     * @return array
     */
    public static function favorite($member_id)
    {
        $where = array(
            array('member_id', $member_id),
            array('favorite', 1)
        );
        return static::createQuery()
            ->select('id', 'name')
            ->from('family')
            ->where($where)
            ->order('name')
            ->cacheOn()
            ->execute();
    }

    /**
     * ฟังก์ชั่นอ่านจำนวนสมาชิกในครอบครัวทั้งหมด
     *
     * @param int $member_id
     *
     * @return int
     */
    public static function getCount($member_id)
    {
        $query = static::createQuery()
            ->selectCount()
            ->from('family')
            ->where(array('member_id', $member_id))
            ->execute();
        return $query[0]->count;
    }
}
