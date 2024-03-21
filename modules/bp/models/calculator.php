<?php
/**
 * @filesource modules/bp/models/calculator.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Bp\Calculator;

use Kotchasan\Database\Sql;

/**
 * ฟังก์ชั่นคำนวณ
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model
{
    /**
     * ความดันโลหิตตัวบนสูง
     *
     * @var int
     */
    public static $sys_hight = 140;
    /**
     * ความดันโลหิตตัวล่างสูง
     *
     * @var int
     */
    public static $dia_hight = 90;

    /**
     * ความดันโลหิตตัวบนปกติ
     *
     * @var int
     */
    public static $sys_max = 120;
    /**
     * ความดันโลหิตตัวล่างปกติ
     *
     * @var int
     */
    public static $dia_max = 80;
    /**
     * ความดันโลหิตตัวบนต่ำ
     *
     * @var int
     */
    public static $sys_min = 90;
    /**
     * ความดันโลหิตตัวล่างต่ำ
     *
     * @var int
     */
    public static $dia_min = 60;

    /**
     * บันทึกค่าเฉลี่ยความดันโลหิต ภายใน 7 วัน
     *
     * @param int $family_id
     * @param int $member_id
     */
    public static function avg($family_id, $member_id)
    {
        // ความดันโลหิตเฉลี่ย ในรอบสัปดาห์
        $last_week = date('Y-m-d', strtotime('-7 days'));
        $q1 = \Kotchasan\Model::createQuery()
            ->select('B.family_id', Sql::AVG('I.sys', 'sys'), Sql::AVG('I.dia', 'dia'))
            ->from('bp B')
            ->join('bp_items I', 'LEFT', array('I.bp_id', 'B.id'))
            ->where(array(
                array('B.family_id', $family_id),
                array('B.member_id', $member_id),
                array(Sql::DATE('B.create_date'), '>=', $last_week)
            ));
        // BMI ล่าสุด
        $q2 = \Kotchasan\Model::createQuery()
            ->select(Sql::create('`weight`/((`height`/100)*(`height`/100))'))
            ->from('bp')
            ->where(array(
                array('family_id', $family_id),
                array('member_id', $member_id),
                array('weight', '>', 0),
                array('height', '>', 0)
            ))
            ->order('create_date DESC')
            ->limit(1);
        // save
        \Kotchasan\Model::createQuery()
            ->update('family F')
            ->join(array($q1, 'B'), 'INNER', array('B.family_id', 'F.id'))
            ->set(array(
                'F.sys' => 'B.sys',
                'F.dia' => 'B.dia',
                'F.bmi' => $q2
            ))
            ->where(array('F.id', $family_id))
            ->execute();
    }

    /**
     * คืนค่าสีจากค่า BP
     *
     * @param int $sys
     * @param int $dia
     *
     * @return string
     */
    public static function bpColor($sys, $dia)
    {
        if (($sys > 0 && $sys > self::$sys_hight) || ($dia > 0 && $dia > self::$dia_hight)) {
            // ความดันโลหิตสูง
            $color = 'red';
        } elseif (($sys > 0 && $sys > self::$sys_max) || ($dia > 0 && $dia > self::$dia_max)) {
            // ความดันโลหิตเริ่มสูง
            $color = 'orange';
        } elseif (($sys > 0 && $sys < self::$sys_min) || ($dia > 0 && $dia < self::$dia_min)) {
            // ความดันต่ำ
            $color = 'blue';
        } else {
            // ความดัน ปกติ
            $color = 'green';
        }
        return $color;
    }

    /**
     * คืนค่าสีจากค่า BMI
     *
     * @param float $bmi
     *
     * @return string
     */
    public static function bmiColor($bmi)
    {
        if ($bmi < 18.5) {
            $color = 'blue';
        } else if ($bmi < 23) {
            $color = 'green';
        } else if ($bmi < 25) {
            $color = 'orange';
        } else {
            $color = 'red';
        }
        return $color;
    }

    /**
     * คำนวณค่า BMI
     *
     * @param float $height
     * @param float $weight
     *
     * @return float
     */
    public static function bmi($height, $weight)
    {
        $height = $height / 100;
        return $weight / ($height * $height);
    }
}
