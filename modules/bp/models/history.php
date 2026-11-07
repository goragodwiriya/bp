<?php
/**
 * @filesource modules/bp/models/history.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Bp\History;

use Kotchasan\Database\Sql;

/**
 * One person's record history.
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * Query for the history table.
     *
     * bp_items has PRIMARY KEY (bp_id, index), so each JOIN yields at most one row;
     * the GROUP BY the legacy query used is unnecessary.
     *
     * The legacy query selected a constant '0 bmi' and recomputed it in the View,
     * This computes it in SQL so the column can be sorted and filtered.
     *
     * @param array $params
     *
     * @return \Kotchasan\QueryBuilder\QueryBuilderInterface
     */
    public static function toDataTable($params)
    {
        $where = [
            ['P.family_id', (int) $params['family_id']],
            ['P.member_id', (int) $params['member_id']],
            ['P.deleted_at', null]
        ];
        if (!empty($params['tag'])) {
            $where[] = ['P.tag', $params['tag']];
        }
        if (!empty($params['from'])) {
            $where[] = [Sql::DATE('P.create_date'), '>=', $params['from']];
        }
        if (!empty($params['to'])) {
            $where[] = [Sql::DATE('P.create_date'), '<=', $params['to']];
        }

        return static::createQuery()
            ->select(
                'P.id',
                'P.create_date',
                'A.sys sys1',
                'B.sys sys2',
                'A.dia dia1',
                'B.dia dia2',
                'A.pulse pulse1',
                'B.pulse pulse2',
                'P.height',
                'P.weight',
                Sql::create('IF(`P`.`height` > 0 AND `P`.`weight` > 0, `P`.`weight`/((`P`.`height`/100)*(`P`.`height`/100)), NULL) AS `bmi`'),
                'P.waist',
                'P.temperature',
                'P.glucose',
                'P.spo2',
                'P.note',
                'P.tag'
            )
            ->from('bp P')
            ->join('bp_items A', [['A.bp_id', 'P.id'], ['A.index', 1]], 'LEFT')
            ->join('bp_items B', [['B.bp_id', 'P.id'], ['B.index', 2]], 'LEFT')
            ->where($where);
    }

    /**
     * Average across every matching row, not just the page on screen.
     *
     * The legacy footer averaged only the rows on the current page, so the number
     * changed with the page size. This averages the whole filtered range instead.
     *
     * @param array $params
     *
     * @return object|null
     */
    public static function summary($params)
    {
        $inner = self::toDataTable($params);

        return \Kotchasan\Model::createQuery()
            ->select(
                Sql::create('AVG(NULLIF(`sys1`, 0)) AS `sys1_avg`'),
                Sql::create('AVG(NULLIF(`sys2`, 0)) AS `sys2_avg`'),
                Sql::create('AVG(NULLIF(`dia1`, 0)) AS `dia1_avg`'),
                Sql::create('AVG(NULLIF(`dia2`, 0)) AS `dia2_avg`'),
                Sql::create('COUNT(*) AS `total`')
            )
            ->from([$inner, 'Q'])
            ->first();
    }
}
