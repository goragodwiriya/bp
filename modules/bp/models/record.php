<?php
/**
 * @filesource modules/bp/models/record.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Bp\Record;

/**
 * One measuring session (up to two readings held in bp_items).
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * Read the requested record.
     * $id = 0 means a new record for $family_id.
     * Returns null when not found or not owned by this member.
     *
     * @param int $id
     * @param int $family_id
     * @param int $member_id
     *
     * @return object|null
     */
    public static function get($id, $family_id, $member_id)
    {
        if (empty($member_id)) {
            return null;
        }

        if ($id > 0) {
            // Editing an existing record
            return static::createQuery()
                ->select(
                    'P.*',
                    'F.name',
                    'A.sys sys1',
                    'B.sys sys2',
                    'A.dia dia1',
                    'B.dia dia2',
                    'A.pulse pulse1',
                    'B.pulse pulse2'
                )
                ->from('bp P')
                ->join('bp_family F', [['F.id', 'P.family_id'], ['F.member_id', 'P.member_id']], 'INNER')
                ->join('bp_items A', [['A.bp_id', 'P.id'], ['A.index', 1]], 'LEFT')
                ->join('bp_items B', [['B.bp_id', 'P.id'], ['B.index', 2]], 'LEFT')
                ->where([
                    ['P.id', (int) $id],
                    ['P.member_id', (int) $member_id],
                    ['P.deleted_at', null]
                ])
                ->first();
        }

        // New record: seed it with the person's stored height
        $family = static::createQuery()
            ->select('id family_id', 'name', 'member_id', 'height')
            ->from('bp_family')
            ->where([
                ['id', (int) $family_id],
                ['member_id', (int) $member_id],
                ['deleted_at', null]
            ])
            ->first();

        if (!$family) {
            return null;
        }

        $family->id = 0;
        $family->weight = '';
        $family->temperature = '';
        $family->waist = '';
        $family->tag = '';
        $family->note = '';
        $family->create_date = date('Y-m-d H:i:s');
        foreach (['sys1', 'sys2', 'dia1', 'dia2', 'pulse1', 'pulse2'] as $k) {
            $family->$k = null;
        }

        return $family;
    }

    /**
     * Validate the submitted values; returns an array of errors (empty means valid).
     *
     * @param array $save
     * @param array $items
     *
     * Every rule is carried over from the legacy system:
     *   - for each reading, filling in one value means all of sys/dia/pulse are required
     *   - a date is required
     *   - a tag must be selected
     *
     * @param array $save
     * @param array $items
     *
     * @return array
     */
    public static function validate(array $save, array $items)
    {
        $errors = [];
        foreach ($items as $i => $item) {
            if ($item['sys'] > 0 || $item['dia'] > 0 || $item['pulse'] > 0) {
                if (empty($item['sys'])) {
                    $errors['sys'.$i] = 'Please fill in';
                }
                if (empty($item['dia'])) {
                    $errors['dia'.$i] = 'Please fill in';
                }
                // All three go together. save() only writes a bp_items row when
                // sys, dia AND pulse are all present, so accepting a reading with
                // no pulse would store the record but silently discard the numbers.
                if (empty($item['pulse'])) {
                    $errors['pulse'.$i] = 'Please fill in';
                }
            }
        }
        if (empty($save['create_date'])) {
            $errors['create_date'] = 'Please fill in';
        }
        if (empty($save['tag'])) {
            $errors['tag'] = 'Please select';
        }
        return $errors;
    }

    /**
     * Save the record and return the id of the stored row.
     *
     * Only readings with all of sys/dia/pulse are stored, as in the legacy system
     * (incomplete input is already rejected by validate()).
     *
     * @param object $index The existing record from get()
     * @param array $save
     * @param array $items
     * @param int $member_id
     *
     * @return int
     */
    public static function save($index, array $save, array $items, $member_id)
    {
        $db = \Kotchasan\DB::create();
        $now = date('Y-m-d H:i:s');
        $save['updated_at'] = $now;

        if (empty($index->id)) {
            $save['member_id'] = (int) $member_id;
            if (empty($save['client_uuid'])) {
                $save['client_uuid'] = self::uuid();
            }
            $id = $db->insert('bp', $save);
        } else {
            $id = (int) $index->id;
            unset($save['client_uuid']);
            $db->update('bp', [
                ['id', $id],
                ['member_id', (int) $member_id]
            ], $save);
        }

        // Rewrite the whole bp_items set
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

        // Refresh the denormalised averages in bp_family
        \Bp\Calculator\Model::avg($save['family_id'], $member_id);

        return $id;
    }

    /**
     * Soft delete records and refresh the averages.
     * Only rows owned by this member are removed.
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

        $rows = static::createQuery()
            ->select('id', 'family_id')
            ->from('bp')
            ->where([
                ['id', $ids],
                ['member_id', (int) $member_id],
                ['deleted_at', null]
            ])
            ->fetchAll();

        if (empty($rows)) {
            return 0;
        }

        $owned = [];
        $families = [];
        foreach ($rows as $row) {
            $owned[] = (int) $row->id;
            $families[(int) $row->family_id] = (int) $row->family_id;
        }

        $now = date('Y-m-d H:i:s');
        \Kotchasan\DB::create()->update('bp', [
            ['id', $owned],
            ['member_id', (int) $member_id]
        ], [
            'deleted_at' => $now,
            'updated_at' => $now
        ]);

        foreach ($families as $family_id) {
            \Bp\Calculator\Model::avg($family_id, $member_id);
        }

        return count($owned);
    }

    /**
     * Generate a v4 UUID for use as the client_uuid of a server-created record.
     *
     * @return string
     */
    public static function uuid()
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
