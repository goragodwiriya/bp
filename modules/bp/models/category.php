<?php
/**
 * @filesource modules/bp/models/category.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Bp\Category;

use Kotchasan\Language;

/**
 * Tags for the bp module - they belong to individual members, not the system,
 * so this uses its own {prefix}_bp_category table rather than \Gcms\Category,
 * which is system-wide and has no member_id column.
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * Category types this module provides
     *
     * @var array
     */
    protected $categories = [
        'tag' => '{LNG_Tag}'
    ];

    /**
     * Loaded data, as [type][category_id] = topic
     *
     * @var array
     */
    private $datas = [];

    /**
     * Return every category type.
     *
     * @return array
     */
    public function items()
    {
        return $this->categories;
    }

    /**
     * Return the label of a category type, or an empty string.
     *
     * @param string $type
     *
     * @return string
     */
    public function name($type)
    {
        return isset($this->categories[$type]) ? $this->categories[$type] : '';
    }

    /**
     * Read one member's categories in the language currently in use.
     *
     * The legacy query filtered no language at all and let the last row by ORDER BY topic
     * overwrite earlier ones, so the text was right or wrong depending on sort order.
     * This picks the current language first, then falls back to the empty-language row,
     * which is what the form writes on a single-language install.
     *
     * @param int $member_id
     *
     * @return static
     */
    public static function init($member_id)
    {
        $obj = new static();
        $lang = Language::name();

        $query = static::createQuery()
            ->select('category_id', 'topic', 'type', 'language')
            ->from('bp_category')
            ->where([
                ['member_id', $member_id],
                ['is_active', 1]
            ])
            ->orderBy('topic');

        // The empty-language row is the default; the current language overrides it
        $fallback = [];
        foreach ($query->fetchAll() as $item) {
            if ($item->language === $lang) {
                $obj->datas[$item->type][$item->category_id] = $item->topic;
            } elseif ($item->language === '') {
                $fallback[$item->type][$item->category_id] = $item->topic;
            }
        }
        foreach ($fallback as $type => $rows) {
            foreach ($rows as $id => $topic) {
                if (!isset($obj->datas[$type][$id])) {
                    $obj->datas[$type][$id] = $topic;
                }
            }
        }
        foreach ($obj->datas as $type => $rows) {
            asort($obj->datas[$type]);
        }

        return $obj;
    }

    /**
     * The tag to use when the caller did not choose one.
     *
     * \Bp\Record\Model::validate() insists on a tag, but the dashboard's quick
     * form deliberately does not ask for one - and a member who has never opened
     * the tag page has none at all. Both the online save and the offline sync
     * merge go through here, so the two paths cannot drift apart and start
     * accepting different records.
     *
     * @param int $member_id
     *
     * @return string|int The tag's category_id
     */
    public static function defaultTag($member_id)
    {
        $first = static::init($member_id)->getFirstKey('tag');
        if (!empty($first)) {
            return $first;
        }
        return static::save($member_id, 'tag', \Kotchasan\Language::trans('{LNG_General}'));
    }

    /**
     * Categories for a select element, as [category_id => topic].
     *
     * @param string $type
     *
     * @return array
     */
    public function toSelect($type)
    {
        return empty($this->datas[$type]) ? [] : $this->datas[$type];
    }

    /**
     * Categories in the shape Now.js's TableManager/FormManager expects:
     * a list of [{value, text}]. Never add an "all" entry here;
     * use data-show-all="true" in the template instead.
     *
     * @param string $type
     *
     * @return array
     */
    public function toOptions($type)
    {
        $options = [];
        foreach ($this->toSelect($type) as $value => $text) {
            $options[] = [
                'value' => (string) $value,
                'text' => $text
            ];
        }
        return $options;
    }

    /**
     * Read a category name by category_id, returning $default when absent.
     *
     * @param string $type
     * @param string $category_id
     * @param string $default
     *
     * @return string
     */
    public function get($type, $category_id, $default = '')
    {
        return empty($this->datas[$type][$category_id]) ? $default : $this->datas[$type][$category_id];
    }

    /**
     * Return the first key, or null when there is none.
     *
     * @param string $type
     *
     * @return int|string|null
     */
    public function getFirstKey($type)
    {
        if (empty($this->datas[$type])) {
            return null;
        }
        reset($this->datas[$type]);
        return key($this->datas[$type]);
    }

    /**
     * Check whether a category_id exists.
     *
     * @param string $type
     * @param string $category_id
     *
     * @return bool
     */
    public function exists($type, $category_id)
    {
        return isset($this->datas[$type][$category_id]);
    }

    /**
     * Look up a category_id by name, creating the category when it does not exist.
     * Used by fields where a new name can be typed straight in (data-options-key).
     *
     * @param int $member_id
     * @param string $type
     * @param string $topic
     *
     * @return int|string 0 when the name is empty
     */
    public static function save($member_id, $type, $topic)
    {
        $topic = trim($topic);
        if ($topic === '') {
            return 0;
        }
        $db = \Kotchasan\DB::create();
        $search = $db->first('bp_category', [
            ['member_id', $member_id],
            ['type', $type],
            ['topic', $topic]
        ]);
        if ($search) {
            return $search->category_id;
        }
        $category_id = $db->nextId('bp_category', [
            ['member_id', $member_id],
            ['type', $type]
        ], 'category_id');
        $db->insert('bp_category', [
            'member_id' => $member_id,
            'type' => $type,
            'category_id' => $category_id,
            'language' => '',
            'topic' => $topic,
            'is_active' => 1
        ]);
        return $category_id;
    }
}
