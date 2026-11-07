<?php
/**
 * @filesource modules/bp/models/family.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Bp\Family;

use Kotchasan\Database\Sql;

/**
 * Table of the people whose readings are recorded.
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * Query for the table.
     *
     * Cross-member isolation lives here: member_id always comes from $login,
     *
     * @param array $params
     *
     * @return \Kotchasan\QueryBuilder\QueryBuilderInterface
     */
    public static function toDataTable($params)
    {
        $where = [
            ['F.member_id', $params['member_id']],
            ['F.deleted_at', null]
        ];
        if (!empty($params['group_id'])) {
            $where[] = ['F.group_id', (int) $params['group_id']];
        }
        if (isset($params['favorite']) && $params['favorite'] !== '') {
            $where[] = ['F.favorite', (int) $params['favorite']];
        }

        $query = static::createQuery()
            ->select(
                'F.id',
                'F.name',
                'F.sex',
                'F.phone',
                'F.birthday',
                'F.height',
                'F.sys',
                'F.dia',
                'F.bmi',
                'F.favorite',
                'F.group_id',
                'F.created_at'
            )
            ->from('bp_family F')
            ->where($where);

        if (!empty($params['search'])) {
            $search = '%'.$params['search'].'%';
            $query->where([
                ['F.name', 'LIKE', $search],
                ['F.phone', 'LIKE', $search],
                ['F.id_card', 'LIKE', $search]
            ], 'OR');
        }

        return $query;
    }

    /**
     * Read one row and verify ownership.
     * Returns null when not found or not owned by this member.
     *
     * @param int $id
     * @param int $member_id
     *
     * @return object|null
     */
    public static function get($id, $member_id)
    {
        if (empty($id) || empty($member_id)) {
            return null;
        }
        return static::createQuery()
            ->select('*')
            ->from('bp_family')
            ->where([
                ['id', (int) $id],
                ['member_id', (int) $member_id],
                ['deleted_at', null]
            ])
            ->first();
    }

    /**
     * Soft delete a person along with all of their readings.
     * Only rows genuinely owned by this member are removed.
     *
     * @param array $ids
     * @param int $member_id
     *
     * @return int How many rows were actually removed
     */
    public static function remove(array $ids, $member_id)
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (empty($ids)) {
            return 0;
        }

        // Always verify ownership first
        $owned = [];
        $rows = static::createQuery()
            ->select('id')
            ->from('bp_family')
            ->where([
                ['id', $ids],
                ['member_id', (int) $member_id],
                ['deleted_at', null]
            ])
            ->fetchAll();
        foreach ($rows as $row) {
            $owned[] = (int) $row->id;
        }
        if (empty($owned)) {
            return 0;
        }

        $now = date('Y-m-d H:i:s');
        $db = \Kotchasan\DB::create();

        // Soft delete so an offline device learns about the deletion when it syncs
        $db->update('bp_family', [
            ['id', $owned],
            ['member_id', (int) $member_id]
        ], [
            'deleted_at' => $now,
            'updated_at' => $now
        ]);

        $db->update('bp', [
            ['family_id', $owned],
            ['member_id', (int) $member_id]
        ], [
            'deleted_at' => $now,
            'updated_at' => $now
        ]);

        return count($owned);
    }

    /**
     * Toggle a person's favourite flag.
     *
     * @param int $id
     * @param int $member_id
     *
     * @return int|null The new favourite flag, or null when not found
     */
    public static function toggleFavorite($id, $member_id)
    {
        $index = self::get($id, $member_id);
        if (!$index) {
            return null;
        }
        $favorite = $index->favorite == 1 ? 0 : 1;
        \Kotchasan\DB::create()->update('bp_family', [
            ['id', (int) $id],
            ['member_id', (int) $member_id]
        ], [
            'favorite' => $favorite,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        return $favorite;
    }

    /**
     * Total number of people belonging to one member.
     *
     * @param int $member_id
     *
     * @return int
     */
    public static function count($member_id)
    {
        $row = static::createQuery()
            ->select(Sql::COUNT('id', 'count'))
            ->from('bp_family')
            ->where([
                ['member_id', (int) $member_id],
                ['deleted_at', null]
            ])
            ->first();
        return $row ? (int) $row->count : 0;
    }

    /**
     * Favourites, for the home page cards.
     *
     * @param int $member_id
     *
     * @return array
     */
    public static function favorites($member_id)
    {
        return static::createQuery()
            ->select('id', 'name', 'sys', 'dia', 'bmi', 'birthday', 'sex')
            ->from('bp_family')
            ->where([
                ['member_id', (int) $member_id],
                ['favorite', 1],
                ['deleted_at', null]
            ])
            ->orderBy('name')
            ->fetchAll();
    }
}
