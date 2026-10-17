<?php
/**
 * @filesource modules/bp/models/calculator.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Bp\Calculator;

use Kotchasan\Database\Sql;

/**
 * All interpretation of blood pressure and BMI values for this module.
 *
 * Ported wholesale from the legacy bp/modules/bp/models/calculator.php.
 * Thresholds now live in settings/config.php, with identical defaults.
 *
 * WARNING: the colour boundaries are strictly greater/less than, not >= or <=.
 *    140/90 is still orange; 141/90 is the first red. Do not change by accident.
 *    (Locked down by 240 assertions in .harness/out/baseline_legacy.json.)
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * Thresholds read from config, cached per request
     *
     * @var array|null
     */
    private static $thresholds = null;

    /**
     * Every threshold, read from settings/config.php.
     * Any key missing from the file falls back to the legacy default.
     *
     * @return array
     */
    public static function thresholds()
    {
        if (self::$thresholds === null) {
            $cfg = \Kotchasan\Config::create();
            self::$thresholds = [
                'sys_hight' => isset($cfg->bp_sys_hight) ? (int) $cfg->bp_sys_hight : 140,
                'dia_hight' => isset($cfg->bp_dia_hight) ? (int) $cfg->bp_dia_hight : 90,
                'sys_max' => isset($cfg->bp_sys_max) ? (int) $cfg->bp_sys_max : 120,
                'dia_max' => isset($cfg->bp_dia_max) ? (int) $cfg->bp_dia_max : 80,
                'sys_min' => isset($cfg->bp_sys_min) ? (int) $cfg->bp_sys_min : 90,
                'dia_min' => isset($cfg->bp_dia_min) ? (int) $cfg->bp_dia_min : 60,
                'referral_sys' => isset($cfg->bp_referral_sys) ? (int) $cfg->bp_referral_sys : 180,
                'referral_dia' => isset($cfg->bp_referral_dia) ? (int) $cfg->bp_referral_dia : 110,
                'avg_days' => isset($cfg->bp_avg_days) ? (int) $cfg->bp_avg_days : 7
            ];
        }
        return self::$thresholds;
    }

    /**
     * Clear the cached thresholds (used by tests and after saving settings).
     *
     * @return void
     */
    public static function resetThresholds()
    {
        self::$thresholds = null;
    }

    /**
     * Return the colour for a blood pressure reading.
     *
     * A value of 0 means "not measured" and is ignored, so call
     * bpColor($sys, 0) for the systolic column and bpColor(0, $dia) for diastolic.
     * With neither value present the result is green, as in the legacy system.
     *
     * @param int $sys
     * @param int $dia
     *
     * @return string red|orange|blue|green
     */
    public static function bpColor($sys, $dia)
    {
        $t = self::thresholds();
        if (($sys > 0 && $sys > $t['sys_hight']) || ($dia > 0 && $dia > $t['dia_hight'])) {
            // High blood pressure
            return 'red';
        } elseif (($sys > 0 && $sys > $t['sys_max']) || ($dia > 0 && $dia > $t['dia_max'])) {
            // Pre-high blood pressure
            return 'orange';
        } elseif (($sys > 0 && $sys < $t['sys_min']) || ($dia > 0 && $dia < $t['dia_min'])) {
            // Low blood pressure
            return 'blue';
        }
        // Normal blood pressure
        return 'green';
    }

    /**
     * Return the colour for a BMI value.
     *
     * @param float $bmi
     *
     * @return string
     */
    public static function bmiColor($bmi)
    {
        if ($bmi < 18.5) {
            return 'blue';
        } elseif ($bmi < 23) {
            return 'green';
        } elseif ($bmi < 25) {
            return 'orange';
        }
        return 'red';
    }

    /**
     * Calculate BMI.
     *
     * The legacy code never guarded against a height of 0 (callers had to), which
     * raises DivisionByZeroError on PHP 8. Returning 0 keeps the page alive.
     *
     * @param float $height Height in centimetres
     * @param float $weight Weight in kilograms
     *
     * @return float
     */
    public static function bmi($height, $weight)
    {
        if ($height <= 0) {
            return 0.0;
        }
        $height = $height / 100;
        return $weight / ($height * $height);
    }

    /**
     * Textual pressure level (new; does not affect the legacy colours).
     *
     * @param int $sys
     * @param int $dia
     *
     * @return string none|low|normal|prehigh|high|critical
     */
    public static function bpLevel($sys, $dia)
    {
        if ($sys <= 0 && $dia <= 0) {
            return 'none';
        }
        if (self::needReferral($sys, $dia)) {
            return 'critical';
        }
        $color = self::bpColor($sys, $dia);
        $map = [
            'red' => 'high',
            'orange' => 'prehigh',
            'blue' => 'low',
            'green' => 'normal'
        ];
        return $map[$color];
    }

    /**
     * Reading that warrants immediate referral to a hospital (new).
     *
     * @param int $sys
     * @param int $dia
     *
     * @return bool
     */
    public static function needReferral($sys, $dia)
    {
        $t = self::thresholds();
        return ($sys > 0 && $sys >= $t['referral_sys']) || ($dia > 0 && $dia >= $t['referral_dia']);
    }

    /**
     * Write the rolling blood pressure average (7 days by default) and the latest
     * BMI back into bp_family so the people list renders quickly.
     *
     * The legacy code did this with a single UPDATE ... INNER JOIN (subquery), but
     * adminframework's UpdateBuilder does not support JOIN (toSql() only builds
     * UPDATE ... SET ... WHERE), so it is split into a SELECT then an UPDATE
     * while preserving two behaviours of the original:
     *
     *   1. With no records inside the window, nothing is updated at all
     *      (the old INNER JOIN matched nothing because family_id came back NULL),
     *      so the existing values in bp_family simply stay as they are.
     *   2. When it does update but no weight/height was ever recorded, bmi is NULL, not 0.
     *
     * @param int $family_id
     * @param int $member_id
     *
     * @return bool True when a row was actually updated
     */
    public static function avg($family_id, $member_id)
    {
        $t = self::thresholds();
        $since = date('Y-m-d', strtotime('-'.$t['avg_days'].' days'));

        // Average pressure inside the time window.
        // No GROUP BY on purpose: MySQL always returns one row, with family_id NULL
        // when nothing matched. That stands in for the old INNER JOIN test.
        $row = static::createQuery()
            ->select('B.family_id', Sql::AVG('I.sys', 'sys'), Sql::AVG('I.dia', 'dia'))
            ->from('bp B')
            ->join('bp_items I', [['I.bp_id', 'B.id']], 'LEFT')
            ->where([
                ['B.family_id', $family_id],
                ['B.member_id', $member_id],
                ['B.deleted_at', null],
                [Sql::DATE('B.create_date'), '>=', $since]
            ])
            ->first();

        if (!$row || $row->family_id === null) {
            return false;
        }

        // BMI from the most recent record that has both weight and height
        $bmiRow = static::createQuery()
            ->select(Sql::create('`weight`/((`height`/100)*(`height`/100)) AS `bmi`'))
            ->from('bp')
            ->where([
                ['family_id', $family_id],
                ['member_id', $member_id],
                ['deleted_at', null],
                ['weight', '>', 0],
                ['height', '>', 0]
            ])
            ->orderBy('create_date', 'DESC')
            ->limit(1)
            ->first();

        \Kotchasan\DB::create()->update('bp_family', [['id', $family_id]], [
            'sys' => $row->sys,
            'dia' => $row->dia,
            'bmi' => $bmiRow ? $bmiRow->bmi : null
        ]);

        return true;
    }
}
