<?php
/**
 * @filesource modules/bp/models/group.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Bp\Group;

use Kotchasan\Database\Sql;

/**
 * Groups and households under care, used by health volunteer mode.
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * Read one group and verify ownership.
     *
     * @param int $id
     * @param int $member_id
     *
     * @return object|null
     */
    public static function get($id, $member_id)
    {
        if (empty($id)) {
            return null;
        }
        return static::createQuery()
            ->select('*')
            ->from('bp_group')
            ->where([
                ['id', (int) $id],
                ['member_id', (int) $member_id],
                ['deleted_at', null]
            ])
            ->first();
    }

    /**
     * Every group, with the number of people in each.
     *
     * @param array $params
     *
     * @return \Kotchasan\QueryBuilder\QueryBuilderInterface
     */
    public static function toDataTable($params)
    {
        // Count group members with LEFT JOIN + COUNT rather than a raw subquery,
        // because the real table name needs a prefix the query builder already applies
        $query = static::createQuery()
            ->select(
                'G.id',
                'G.name',
                'G.village',
                'G.moo',
                'G.address',
                'G.note',
                'G.created_at',
                Sql::COUNT('F.id', 'members')
            )
            ->from('bp_group G')
            ->join('bp_family F', [['F.group_id', 'G.id'], ['F.deleted_at', null]], 'LEFT')
            ->where([
                ['G.member_id', (int) $params['member_id']],
                ['G.deleted_at', null]
            ])
            ->groupBy('G.id');

        if (!empty($params['search'])) {
            $search = '%'.$params['search'].'%';
            $query->where([
                ['G.name', 'LIKE', $search],
                ['G.village', 'LIKE', $search]
            ], 'OR');
        }

        return $query;
    }

    /**
     * Groups as [{value, text}] for a select element.
     *
     * @param int $member_id
     *
     * @return array
     */
    public static function toOptions($member_id)
    {
        $options = [];
        $rows = static::createQuery()
            ->select('id', 'name', 'village')
            ->from('bp_group')
            ->where([
                ['member_id', (int) $member_id],
                ['deleted_at', null]
            ])
            ->orderBy('name')
            ->fetchAll();
        foreach ($rows as $row) {
            $options[] = [
                'value' => (string) $row->id,
                'text' => empty($row->village) ? $row->name : $row->name.' ('.$row->village.')'
            ];
        }
        return $options;
    }

    /**
     * Save a group and return its id.
     *
     * @param object $index
     * @param array $save
     * @param int $member_id
     *
     * @return int
     */
    public static function save($index, array $save, $member_id)
    {
        $db = \Kotchasan\DB::create();
        $now = date('Y-m-d H:i:s');
        $save['updated_at'] = $now;

        if (empty($index->id)) {
            $save['member_id'] = (int) $member_id;
            $save['created_at'] = $now;
            $save['client_uuid'] = \Bp\Record\Model::uuid();
            return (int) $db->insert('bp_group', $save);
        }

        $db->update('bp_group', [
            ['id', (int) $index->id],
            ['member_id', (int) $member_id]
        ], $save);

        return (int) $index->id;
    }

    /**
     * Soft delete a group; its people are kept and simply leave the group.
     *
     * @param array $ids
     * @param int $member_id
     *
     * @return int
     */
    public static function remove(array $ids, $member_id)
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (empty($ids)) {
            return 0;
        }

        $rows = static::createQuery()
            ->select('id')
            ->from('bp_group')
            ->where([
                ['id', $ids],
                ['member_id', (int) $member_id],
                ['deleted_at', null]
            ])
            ->fetchAll();
        if (empty($rows)) {
            return 0;
        }

        $owned = array_map(function ($row) {
            return (int) $row->id;
        }, $rows);

        $now = date('Y-m-d H:i:s');
        $db = \Kotchasan\DB::create();
        $db->update('bp_group', [
            ['id', $owned],
            ['member_id', (int) $member_id]
        ], ['deleted_at' => $now, 'updated_at' => $now]);

        // The people stay; they simply no longer belong to a group
        $db->update('bp_family', [
            ['group_id', $owned],
            ['member_id', (int) $member_id]
        ], ['group_id' => 0, 'updated_at' => $now]);

        return count($owned);
    }
}
