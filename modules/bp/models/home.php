<?php
/**
 * @filesource modules/bp/models/home.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Bp\Home;

use Kotchasan\Database\Sql;

/**
 * Queries behind the daily dashboard.
 *
 * The dashboard has two jobs: let the user record today's reading without
 * navigating anywhere, and show what is worth knowing at a glance. Everything
 * here serves one of those two.
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * Everyone the member looks after, ordered so the most useful person to
     * record next comes first: not yet recorded today, then favourites, then name.
     *
     * @param int $member_id
     *
     * @return array
     */
    public static function people($member_id)
    {
        $today = date('Y-m-d');

        return static::createQuery()
            ->select(
                'F.id',
                'F.name',
                'F.sex',
                'F.birthday',
                'F.height',
                'F.favorite',
                'F.sys',
                'F.dia',
                'F.bmi',
                Sql::MAX('P.create_date', 'last_measured'),
                Sql::create("SUM(CASE WHEN DATE(P.create_date) = '$today' THEN 1 ELSE 0 END) AS done_today")
            )
            ->from('bp_family F')
            ->join('bp P', [['P.family_id', 'F.id'], ['P.deleted_at', null]], 'LEFT')
            ->where([
                ['F.member_id', (int) $member_id],
                ['F.deleted_at', null]
            ])
            ->groupBy('F.id')
            ->orderBy('F.favorite', 'DESC')
            ->orderBy('F.name')
            ->fetchAll();
    }

    /**
     * The latest reading of each person, taken from bp_items rather than the
     * averages cached on bp_family.
     *
     * Same reasoning as \Bp\Care\Model::latestReadings(): bp_family.sys/dia are
     * a 7-day average, so a single critical reading is averaged away and the
     * dashboard would under-report it.
     *
     * @param int $member_id
     * @param int $days
     *
     * @return array [family_id => object]
     */
    public static function latestReadings($member_id, $days = 400)
    {
        return \Bp\Care\Model::latestReadings($member_id, $days);
    }

    /**
     * The reading before the latest one, so the dashboard can show a trend.
     *
     * @param int $member_id
     * @param int $days
     *
     * @return array [family_id => object] second-most-recent reading
     */
    public static function previousReadings($member_id, $days = 400)
    {
        $rows = static::createQuery()
            ->select(
                'P.family_id',
                'P.create_date',
                Sql::MAX('I.sys', 'sys'),
                Sql::MAX('I.dia', 'dia')
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

        // Rows arrive newest first; the SECOND row of each person is the previous one
        $seen = [];
        $previous = [];
        foreach ($rows as $row) {
            $key = (int) $row->family_id;
            $seen[$key] = isset($seen[$key]) ? $seen[$key] + 1 : 1;
            if ($seen[$key] === 2) {
                $previous[$key] = $row;
            }
        }
        return $previous;
    }

    /**
     * How many records were made this month.
     *
     * @param int $member_id
     *
     * @return int
     */
    public static function monthCount($member_id)
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

    /**
     * How many days in a row, ending today or yesterday, have a record.
     *
     * Yesterday counts as the end of the streak so that opening the app in the
     * morning does not show the streak as already broken before recording.
     *
     * @param int $member_id
     * @param int $limit How many days back to look at most
     *
     * @return int
     */
    public static function streak($member_id, $limit = 60)
    {
        $rows = static::createQuery()
            ->select(Sql::DATE('create_date', 'day'))
            ->from('bp')
            ->where([
                ['member_id', (int) $member_id],
                ['deleted_at', null],
                [Sql::DATE('create_date'), '>=', date('Y-m-d', strtotime('-'.(int) $limit.' days'))]
            ])
            ->groupBy(Sql::DATE('create_date'))
            ->orderBy('day', 'DESC')
            ->fetchAll();

        $days = [];
        foreach ($rows as $row) {
            $days[$row->day] = true;
        }
        if (empty($days)) {
            return 0;
        }

        // Start from today, or from yesterday when today has nothing yet
        $cursor = isset($days[date('Y-m-d')])
            ? date('Y-m-d')
            : date('Y-m-d', strtotime('-1 day'));

        $streak = 0;
        while (isset($days[$cursor])) {
            $streak++;
            $cursor = date('Y-m-d', strtotime($cursor.' -1 day'));
        }
        return $streak;
    }
}
