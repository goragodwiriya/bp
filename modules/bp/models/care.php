<?php
/**
 * @filesource modules/bp/models/care.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Bp\Care;

use Kotchasan\Database\Sql;

/**
 * Overview of the people under care, for the health volunteer dashboard.
 *
 * Answers the questions a volunteer needs before setting out:
 *   - who needs referring to a hospital
 *   - who has not been measured for a long time
 *   - how many visits were made this month
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * People under care, with their stored values and the date last measured.
     *
     * @param int $member_id
     * @param int $group_id 0 means every group
     *
     * @return array
     */
    public static function people($member_id, $group_id = 0)
    {
        $where = [
            ['F.member_id', (int) $member_id],
            ['F.deleted_at', null]
        ];
        if (!empty($group_id)) {
            $where[] = ['F.group_id', (int) $group_id];
        }

        return static::createQuery()
            ->select(
                'F.id',
                'F.name',
                'F.sex',
                'F.birthday',
                'F.phone',
                'F.group_id',
                'F.sys',
                'F.dia',
                'F.bmi',
                'F.chronic',
                'F.smoking',
                'F.diabetes',
                'F.consent_at',
                'G.name group_name',
                Sql::MAX('P.create_date', 'last_measured')
            )
            ->from('bp_family F')
            ->join('bp_group G', [['G.id', 'F.group_id']], 'LEFT')
            ->join('bp P', [['P.family_id', 'F.id'], ['P.deleted_at', null]], 'LEFT')
            ->where($where)
            ->groupBy('F.id')
            ->orderBy('F.name')
            ->fetchAll();
    }

    /**
     * The most recent reading for each person.
     *
     * WARNING: bp_family.sys/dia hold the 7-day AVERAGE written by Calculator.
     * They cannot decide a referral, because one critical reading (say 185/112)
     * is averaged away by the normal days and looks merely "pre-high".
     * A volunteer's screen has to show the genuine latest reading as well.
     *
     * @param int $member_id
     * @param int $days How many days back to look
     *
     * @return array [family_id => object]
     */
    public static function latestReadings($member_id, $days = 90)
    {
        $rows = static::createQuery()
            ->select(
                'P.family_id',
                'P.create_date',
                Sql::MAX('I.sys', 'sys'),
                Sql::MAX('I.dia', 'dia'),
                Sql::MAX('I.pulse', 'pulse')
            )
            ->from('bp P')
            ->join('bp_items I', [['I.bp_id', 'P.id']], 'INNER')
            ->where([
                ['P.member_id', (int) $member_id],
                ['P.deleted_at', null],
                [Sql::DATE('P.create_date'), '>=', date('Y-m-d', strtotime('-'.(int) $days.' days'))]
            ])
            ->groupBy('P.id')
            ->orderBy('P.create_date', 'DESC')
            ->fetchAll();

        // Rows come newest first, so the first row per person is the latest reading
        $latest = [];
        foreach ($rows as $row) {
            $key = (int) $row->family_id;
            if (!isset($latest[$key])) {
                $latest[$key] = $row;
            }
        }
        return $latest;
    }

    /**
     * Number of records made this month.
     *
     * @param int $member_id
     *
     * @return int
     */
    public static function recordsThisMonth($member_id)
    {
        $row = static::createQuery()
            ->select(Sql::COUNT('id', 'total'))
            ->from('bp')
            ->where([
                ['member_id', (int) $member_id],
                ['deleted_at', null],
                [Sql::DATE('create_date'), '>=', date('Y-m-01')]
            ])
            ->first();
        return $row ? (int) $row->total : 0;
    }
}
