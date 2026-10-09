<?php
/**
 * @filesource modules/bp/models/report.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Bp\Report;

use Kotchasan\Database\Sql;

/**
 * Data behind the report charts.
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * Read the raw readings (one record may produce two rows).
     *
     * The legacy query did not group by index, so a record measured twice became two rows
     * which the presentation layer then averaged. That behaviour is preserved so
     * so the averages shown match the legacy output exactly.
     *
     * @param array $params
     *
     * @return array
     */
    public static function get($params)
    {
        $where = [
            ['B.family_id', (int) $params['family_id']],
            ['B.member_id', (int) $params['member_id']],
            ['B.deleted_at', null]
        ];
        if (!empty($params['tag'])) {
            $where[] = ['B.tag', $params['tag']];
        }
        if (!empty($params['from'])) {
            $where[] = [Sql::DATE('B.create_date'), '>=', $params['from']];
        }
        if (!empty($params['to'])) {
            $where[] = [Sql::DATE('B.create_date'), '<=', $params['to']];
        }

        return static::createQuery()
            ->select(
                'B.id',
                'B.create_date',
                'A.sys',
                'A.dia',
                'A.pulse',
                'B.height',
                'B.weight',
                'B.glucose',
                'B.spo2',
                'B.tag'
            )
            ->from('bp B')
            ->join('bp_items A', [['A.bp_id', 'B.id']], 'LEFT')
            ->where($where)
            ->orderBy('B.create_date')
            ->fetchAll();
    }
}
