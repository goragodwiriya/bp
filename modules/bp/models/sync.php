<?php
/**
 * @filesource modules/bp/models/sync.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Bp\Sync;

use Kotchasan\Database\Sql;

/**
 * Accept records captured offline and merge them into the database.
 *
 * The heart of it is client_uuid plus UNIQUE (member_id, client_uuid).
 * The device generates the uuid while still offline, so any number of retries
 * collapse to a single row, no matter how often the request reaches the server.
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * Entity types accepted by sync
     *
     * @var array
     */
    public static $entities = ['record', 'family'];

    /**
     * Find a row id from a member's client_uuid.
     *
     * @param string $table
     * @param string $uuid
     * @param int $member_id
     *
     * @return object|null
     */
    public static function findByUuid($table, $uuid, $member_id)
    {
        if ($uuid === '') {
            return null;
        }
        return static::createQuery()
            ->select('id', 'deleted_at')
            ->from($table)
            ->where([
                ['member_id', (int) $member_id],
                ['client_uuid', $uuid]
            ])
            ->first();
    }

    /**
     * Merge one person into the database.
     *
     * @param array $op
     * @param int $member_id
     *
     * @return array ['status' => ..., 'id' => ...]
     */
    public static function mergeFamily(array $op, $member_id)
    {
        $uuid = isset($op['client_uuid']) ? trim((string) $op['client_uuid']) : '';
        if ($uuid === '') {
            return ['status' => 'rejected', 'reason' => 'client_uuid is required'];
        }
        $name = isset($op['name']) ? \Kotchasan\Text::topic($op['name']) : '';
        if ($name === '') {
            return ['status' => 'rejected', 'reason' => 'name is required'];
        }

        $now = date('Y-m-d H:i:s');
        $save = [
            'name' => $name,
            'sex' => isset($op['sex']) ? preg_replace('/[^a-z]/', '', (string) $op['sex']) : null,
            'height' => isset($op['height']) ? (float) $op['height'] : 0,
            'birthday' => empty($op['birthday']) ? null : \Kotchasan\Text::date($op['birthday']),
            'phone' => isset($op['phone']) ? preg_replace('/[^0-9+\-\s]/', '', (string) $op['phone']) : null,
            'updated_at' => $now
        ];

        $db = \Kotchasan\DB::create();
        $existing = self::findByUuid('bp_family', $uuid, $member_id);

        if ($existing) {
            $db->update('bp_family', [
                ['id', (int) $existing->id],
                ['member_id', (int) $member_id]
            ], $save);
            return ['status' => 'updated', 'id' => (int) $existing->id];
        }

        $save['member_id'] = (int) $member_id;
        $save['client_uuid'] = $uuid;
        $save['created_at'] = isset($op['created_at']) ? \Kotchasan\Text::date($op['created_at'], 'Y-m-d H:i:s') : $now;
        $id = $db->insert('bp_family', $save);

        return ['status' => 'created', 'id' => (int) $id];
    }

    /**
     * Merge one blood pressure record into the database.
     *
     * @param array $op
     * @param int $member_id
     *
     * @return array
     */
    public static function mergeRecord(array $op, $member_id)
    {
        $uuid = isset($op['client_uuid']) ? trim((string) $op['client_uuid']) : '';
        if ($uuid === '') {
            return ['status' => 'rejected', 'reason' => 'client_uuid is required'];
        }

        // A person can be referenced two ways: by id, or by the uuid of someone
        $familyId = isset($op['family_id']) ? (int) $op['family_id'] : 0;
        if (!empty($op['family_uuid'])) {
            $family = self::findByUuid('bp_family', (string) $op['family_uuid'], $member_id);
            if ($family) {
                $familyId = (int) $family->id;
            }
        }
        if (!\Bp\Family\Model::get($familyId, $member_id)) {
            return ['status' => 'rejected', 'reason' => 'unknown family'];
        }

        $save = [
            'family_id' => $familyId,
            'weight' => isset($op['weight']) ? (float) $op['weight'] : 0,
            'height' => isset($op['height']) ? (float) $op['height'] : 0,
            'temperature' => isset($op['temperature']) ? (float) $op['temperature'] : 0,
            'waist' => isset($op['waist']) ? (float) $op['waist'] : 0,
            'glucose' => isset($op['glucose']) && $op['glucose'] !== '' ? (float) $op['glucose'] : null,
            'spo2' => isset($op['spo2']) && $op['spo2'] !== '' ? (int) $op['spo2'] : null,
            'note' => isset($op['note']) ? \Kotchasan\Text::topic($op['note']) : null,
            'create_date' => empty($op['create_date'])
                ? date('Y-m-d H:i:s')
                : \Kotchasan\Text::date($op['create_date'], 'Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];

        // Tags accept an id or a name (an offline device may not know the id yet)
        $save['tag'] = isset($op['tag']) ? (int) $op['tag'] : 0;
        if (empty($save['tag']) && !empty($op['tag_text'])) {
            $save['tag'] = \Bp\Category\Model::save($member_id, 'tag', (string) $op['tag_text']);
        }
        if (empty($save['tag'])) {
            // The dashboard's quick form does not ask for a tag. Without this the
            // record fails validate() here, comes back as 'rejected', and the device
            // drops it from the outbox - the reading would be lost for good.
            $save['tag'] = \Bp\Category\Model::defaultTag($member_id);
        }

        $items = [];
        foreach ([1, 2] as $i) {
            $items[$i] = [
                'sys' => isset($op['sys'.$i]) ? (int) $op['sys'.$i] : 0,
                'dia' => isset($op['dia'.$i]) ? (int) $op['dia'.$i] : 0,
                'pulse' => isset($op['pulse'.$i]) ? (int) $op['pulse'.$i] : 0
            ];
        }

        $errors = \Bp\Record\Model::validate($save, $items);
        if (!empty($errors)) {
            return ['status' => 'rejected', 'reason' => 'validation: '.implode(', ', array_keys($errors))];
        }

        $db = \Kotchasan\DB::create();
        $existing = self::findByUuid('bp', $uuid, $member_id);

        if ($existing) {
            $id = (int) $existing->id;
            $db->update('bp', [['id', $id], ['member_id', (int) $member_id]], $save);
            $status = 'updated';
        } else {
            $save['member_id'] = (int) $member_id;
            $save['client_uuid'] = $uuid;
            $save['recorder_id'] = (int) $member_id;
            $id = (int) $db->insert('bp', $save);
            $status = 'created';
        }

        $db->delete('bp_items', [['bp_id', $id]], 0);
        foreach ($items as $i => $item) {
            if ($item['sys'] > 0 && $item['dia'] > 0 && $item['pulse'] > 0) {
                $db->insert('bp_items', [
                    'bp_id' => $id,
                    'index' => $i,
                    'sys' => $item['sys'],
                    'dia' => $item['dia'],
                    'pulse' => $item['pulse']
                ]);
            }
        }

        \Bp\Calculator\Model::avg($familyId, $member_id);

        return ['status' => $status, 'id' => $id];
    }

    /**
     * Everything changed since a given time, for a device to cache and read offline.
     *
     * Soft-deleted rows are included so an offline device knows to delete them too.
     *
     * @param string $since
     * @param int $member_id
     * @param int $limit
     *
     * @return array
     */
    public static function changesSince($since, $member_id, $limit = 500)
    {
        $where = [['member_id', (int) $member_id]];
        if (!empty($since)) {
            $where[] = ['updated_at', '>', $since];
        }

        $families = static::createQuery()
            ->select('id', 'client_uuid', 'name', 'sex', 'height', 'birthday', 'phone',
                'favorite', 'sys', 'dia', 'bmi', 'created_at', 'updated_at', 'deleted_at')
            ->from('bp_family')
            ->where($where)
            ->orderBy('updated_at')
            ->limit($limit)
            ->fetchAll();

        $records = static::createQuery()
            ->select('P.id', 'P.client_uuid', 'P.family_id', 'P.weight', 'P.height',
                'P.temperature', 'P.waist', 'P.glucose', 'P.spo2', 'P.tag', 'P.note',
                'P.create_date', 'P.updated_at', 'P.deleted_at',
                'A.sys sys1', 'A.dia dia1', 'A.pulse pulse1',
                'B.sys sys2', 'B.dia dia2', 'B.pulse pulse2')
            ->from('bp P')
            ->join('bp_items A', [['A.bp_id', 'P.id'], ['A.index', 1]], 'LEFT')
            ->join('bp_items B', [['B.bp_id', 'P.id'], ['B.index', 2]], 'LEFT')
            ->where(array_map(function ($w) {
                $w[0] = 'P.'.$w[0];
                return $w;
            }, $where))
            ->orderBy('P.updated_at')
            ->limit($limit)
            ->fetchAll();

        $categories = static::createQuery()
            ->select('category_id', 'type', 'language', 'topic')
            ->from('bp_category')
            ->where([['member_id', (int) $member_id]])
            ->fetchAll();

        // The newest timestamp sent, used as the starting point for the next pull
        $cursor = $since;
        foreach (array_merge($families, $records) as $row) {
            if (!empty($row->updated_at) && $row->updated_at > $cursor) {
                $cursor = $row->updated_at;
            }
        }

        return [
            'families' => $families,
            'records' => $records,
            'categories' => $categories,
            'cursor' => $cursor,
            'server_time' => date('Y-m-d H:i:s'),
            'has_more' => count($families) >= $limit || count($records) >= $limit
        ];
    }
}
