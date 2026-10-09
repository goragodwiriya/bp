<?php
/**
 * @filesource modules/bp/models/categories.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Bp\Categories;

use Kotchasan;
use Kotchasan\Language;

/**
 * Edit a member's whole tag table at once (data-editable-rows).
 *
 * Same shape as \Index\Categories\Model but always bound to a member_id,
 * because this module's categories belong to individual members.
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * Read all of a member's categories for editing.
     * With none stored yet it returns one blank row so typing can start right away.
     *
     * @param string $type
     * @param int $member_id
     * @param bool $multiLanguage
     *
     * @return array
     */
    public static function get($type, $member_id, $multiLanguage)
    {
        $languages = \Index\Languages\Model::getLanguageColumns();

        $query = static::createQuery()
            ->select('category_id', 'topic', 'language')
            ->from('bp_category')
            ->where([
                ['member_id', (int) $member_id],
                ['type', $type]
            ])
            ->orderBy('category_id');

        // Collect into [category_id][language] = topic first, then choose what to show.
        // Two passes are needed: the empty-language row may come before or after the
        $rows = [];
        foreach ($query->fetchAll() as $item) {
            $rows[$item->category_id][$item->language] = $item->topic;
        }

        $data = [];
        foreach ($rows as $categoryId => $topics) {
            $row = ['id' => $categoryId];
            foreach ($languages as $lng) {
                if (isset($topics[$lng])) {
                    // A translation for that language exists
                    $row[$lng] = $topics[$lng];
                } elseif (isset($topics[''])) {
                    // A single-language install stores language = ''; use it as the default
                    $row[$lng] = $topics[''];
                } else {
                    $row[$lng] = $multiLanguage ? '' : reset($topics);
                }
            }
            $data[] = $row;
        }

        if (empty($data)) {
            $row = ['id' => 1];
            foreach ($languages as $lng) {
                $row[$lng] = '';
            }
            $data[] = $row;
        }

        return array_values($data);
    }

    /**
     * Save the whole set of tags (the member's existing rows are replaced).
     *
     * @param string $type
     * @param int $member_id
     * @param array $save
     * @param bool $multiLanguage
     *
     * @return void
     */
    public static function save($type, $member_id, $save, $multiLanguage)
    {
        $db = Kotchasan\DB::create();

        $db->delete('bp_category', [
            ['member_id', (int) $member_id],
            ['type', $type]
        ], 0);

        foreach ($save as $item) {
            if (!$multiLanguage) {
                $item['language'] = '';
            }
            $item['member_id'] = (int) $member_id;
            $item['type'] = $type;
            $item['is_active'] = 1;
            $db->insert('bp_category', $item);
        }
    }

    /**
     * Column definitions for the editable table.
     *
     * @param bool $multiLanguage
     *
     * @return array
     */
    public static function getColumns($multiLanguage)
    {
        $columns = [
            [
                'field' => 'id',
                'label' => 'ID',
                'cellElement' => 'text',
                'size' => 5
            ]
        ];

        $languages = $multiLanguage
            ? \Index\Languages\Model::getLanguageColumns()
            : [Language::name()];

        foreach ($languages as $language) {
            $columns[] = [
                'field' => $language,
                'label' => ucfirst($language),
                'cellElement' => 'text',
                'size' => 20
            ];
        }

        return $columns;
    }
}
