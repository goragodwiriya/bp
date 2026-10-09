<?php
/**
 * @filesource modules/bp/models/visit.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Bp\Visit;

use Kotchasan\Database\Sql;

/**
 * A health volunteer's visit round - walk household to household, recording as you go.
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * Find, or create, a group's visit round for a given date.
     *
     * A group can only have one round per day,
     * so coming back to add more readings does not create a second overlapping round.
     *
     * @param int $group_id
     * @param string $visit_date
     * @param int $member_id
     *
     * @return object
     */
    public static function ensure($group_id, $visit_date, $member_id)
    {
        $existing = static::createQuery()
            ->select('*')
            ->from('bp_visit')
            ->where([
                ['member_id', (int) $member_id],
                ['group_id', (int) $group_id],
                ['visit_date', $visit_date],
                ['deleted_at', null]
            ])
            ->first();

        if ($existing) {
            return $existing;
        }

        $now = date('Y-m-d H:i:s');
        $id = \Kotchasan\DB::create()->insert('bp_visit', [
            'member_id' => (int) $member_id,
            'group_id' => (int) $group_id,
            'visit_date' => $visit_date,
            'status' => 0,
            'client_uuid' => \Bp\Record\Model::uuid(),
            'created_at' => $now,
            'updated_at' => $now
        ]);

        return (object) [
            'id' => (int) $id,
            'group_id' => (int) $group_id,
            'visit_date' => $visit_date,
            'status' => 0,
            'note' => null
        ];
    }

    /**
     * People in the group, with whether they were already recorded today.
     *
     * @param int $group_id
     * @param int $member_id
     * @param string $visit_date
     *
     * @return array
     */
    public static function people($group_id, $member_id, $visit_date)
    {
        return static::createQuery()
            ->select(
                'F.id',
                'F.name',
                'F.sex',
                'F.birthday',
                'F.height',
                'F.chronic',
                'F.sys',
                'F.dia',
                Sql::COUNT('P.id', 'done_today')
            )
            ->from('bp_family F')
            ->join('bp P', [
                ['P.family_id', 'F.id'],
                ['P.deleted_at', null],
                [Sql::DATE('P.create_date'), $visit_date]
            ], 'LEFT')
            ->where([
                ['F.member_id', (int) $member_id],
                ['F.group_id', (int) $group_id],
                ['F.deleted_at', null]
            ])
            ->groupBy('F.id')
            ->orderBy('F.name')
            ->fetchAll();
    }

    /**
     * Close the visit round.
     *
     * @param int $id
     * @param int $member_id
     * @param string $note
     *
     * @return bool
     */
    public static function finish($id, $member_id, $note = '')
    {
        $rows = \Kotchasan\DB::create()->update('bp_visit', [
            ['id', (int) $id],
            ['member_id', (int) $member_id]
        ], [
            'status' => 1,
            'note' => $note,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        return $rows > 0;
    }
}
