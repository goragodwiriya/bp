<?php
/**
 * @filesource modules/bp/models/profile.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Bp\Profile;

/**
 * Details of a person whose readings are recorded.
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * Read the record; $id = 0 means a new one.
     * Returns null when not found or not owned by this member.
     *
     * @param int $id
     * @param int $member_id
     *
     * @return object|null
     */
    public static function get($id, $member_id)
    {
        if (empty($member_id)) {
            return null;
        }
        if (empty($id)) {
            return (object) [
                'id' => 0,
                'member_id' => (int) $member_id,
                'name' => '',
                'sex' => '',
                'height' => '',
                'id_card' => '',
                'birthday' => null,
                'phone' => '',
                'address' => '',
                'country' => 'TH',
                'provinceID' => '',
                'province' => '',
                'zipcode' => '',
                'favorite' => 0
            ];
        }
        return \Bp\Family\Model::get($id, $member_id);
    }

    /**
     * Validate the submitted values.
     *
     * @param array $save
     *
     * @return array
     */
    public static function validate(array $save)
    {
        $errors = [];
        if ($save['name'] === '') {
            $errors['name'] = 'Please fill in';
        }
        return $errors;
    }

    /**
     * Save and return the id.
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
            return $db->insert('bp_family', $save);
        }

        $db->update('bp_family', [
            ['id', (int) $index->id],
            ['member_id', (int) $member_id]
        ], $save);

        return (int) $index->id;
    }
}
