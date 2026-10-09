<?php
/**
 * @filesource modules/bp/controllers/history.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Bp\History;

use Bp\Calculator\Model as Calculator;
use Kotchasan\Date;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * api/bp/history - one person's record history
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends \Gcms\Table
{
    /**
     * @var array
     */
    protected $allowedSortColumns = ['id', 'create_date', 'sys1', 'dia1', 'pulse1', 'weight', 'bmi', 'waist', 'temperature', 'tag'];

    /**
     * @var object|null The person currently being viewed
     */
    private $profile = null;

    /**
     * Verify the requested person really belongs to this member.
     *
     * @param Request $request
     * @param object $login
     *
     * @return true|\Kotchasan\Http\Response
     */
    protected function checkAuthorization(Request $request, $login)
    {
        $this->profile = \Bp\Family\Model::get($request->get('id')->toInt(), (int) $login->id);
        if (!$this->profile) {
            return $this->errorResponse('Can not be performed this request. Because they do not find the information you need or you are not allowed', 403);
        }
        return true;
    }

    /**
     * @param Request $request
     * @param object $login
     *
     * @return array
     */
    protected function getCustomParams(Request $request, $login): array
    {
        // The legacy table defaulted to newest first (cookie bpHistorysort = 'create_date desc').
        // \Gcms\Table has no default, so it is set here through getCustomParams(),
        // whose result is merged over $params after parseParams().
        $sort = $request->get('sort')->toString();

        return [
            'member_id' => (int) $login->id,
            'family_id' => $request->get('id')->toInt(),
            'tag' => $request->get('tag')->topic(),
            'from' => $request->get('from')->date(),
            'to' => $request->get('to')->date(),
            'sort' => $sort === '' ? 'create_date desc' : $sort
        ];
    }

    /**
     * @param array $params
     * @param object $login
     *
     * @return \Kotchasan\QueryBuilder\QueryBuilderInterface
     */
    protected function toDataTable($params, $login = null)
    {
        return Model::toDataTable($params);
    }

    /**
     * @param array $params
     * @param object $login
     *
     * @return array
     */
    protected function getFilters($params, $login = null)
    {
        return [
            'tag' => \Bp\Category\Model::init((int) $login->id)->toOptions('tag')
        ];
    }

    /**
     * Page context: who is being viewed, and the average across the whole range.
     *
     * @param array $params
     * @param object $login
     *
     * @return array
     */
    protected function getOptions(array $params, $login)
    {
        $summary = Model::summary($params);
        $sysAvg = $summary && $summary->sys1_avg !== null ? floor(($summary->sys1_avg + ($summary->sys2_avg ?? $summary->sys1_avg)) / 2) : 0;
        $diaAvg = $summary && $summary->dia1_avg !== null ? floor(($summary->dia1_avg + ($summary->dia2_avg ?? $summary->dia1_avg)) / 2) : 0;

        return [
            'profile' => [
                'id' => (int) $this->profile->id,
                'name' => $this->profile->name
            ],
            'summary' => [
                'total' => $summary ? (int) $summary->total : 0,
                'sys' => $sysAvg,
                'dia' => $diaAvg,
                'text' => $sysAvg > 0 && $diaAvg > 0 ? $sysAvg.'/'.$diaAvg : '-',
                'color' => $sysAvg > 0 && $diaAvg > 0 ? Calculator::bpColor($sysAvg, $diaAvg) : ''
            ]
        ];
    }

    /**
     * Format each row for display.
     *
     * @param array $datas
     * @param object $login
     *
     * @return array
     */
    protected function formatDatas(array $datas, $login = null): array
    {
        $category = \Bp\Category\Model::init((int) $login->id);
        $cm = Language::trans('{LNG_Cm.}');
        $kg = Language::trans('{LNG_Kg.}');

        foreach ($datas as $item) {
            $item->date_text = Date::format($item->create_date);
            $item->tag_text = $category->get('tag', $item->tag);

            foreach ([1, 2] as $i) {
                $sys = 'sys'.$i;
                $dia = 'dia'.$i;
                $pulse = 'pulse'.$i;
                $item->{$sys.'_text'} = empty($item->$sys) ? '-' : (string) $item->$sys;
                $item->{$sys.'_color'} = empty($item->$sys) ? '' : Calculator::bpColor($item->$sys, 0);
                $item->{$dia.'_text'} = empty($item->$dia) ? '-' : (string) $item->$dia;
                $item->{$dia.'_color'} = empty($item->$dia) ? '' : Calculator::bpColor(0, $item->$dia);
                // The legacy code never formatted pulse, leaving empty cells as null instead of '-'
                $item->{$pulse.'_text'} = empty($item->$pulse) ? '-' : (string) $item->$pulse;
            }

            $item->bmi_text = $item->bmi === null ? '-' : (string) round($item->bmi, 2);
            $item->bmi_color = $item->bmi === null ? '' : Calculator::bmiColor($item->bmi);
            $item->height_text = empty($item->height) ? '-' : $item->height.' '.$cm;
            $item->weight_text = empty($item->weight) ? '-' : $item->weight.' '.$kg;
            $item->waist_text = empty($item->waist) ? '-' : $item->waist.' '.$cm;
            $item->temperature_text = empty($item->temperature) ? '-' : $item->temperature.' ℃';
            $item->glucose_text = empty($item->glucose) ? '-' : $item->glucose.' mg/dL';
            $item->spo2_text = empty($item->spo2) ? '-' : $item->spo2.' %';

            $maxSys = max((int) $item->sys1, (int) $item->sys2);
            $maxDia = max((int) $item->dia1, (int) $item->dia2);
            $item->need_referral = Calculator::needReferral($maxSys, $maxDia);
            $item->bp_level = Calculator::bpLevel($maxSys, $maxDia);
        }
        return $datas;
    }

    /**
     * Delete records.
     *
     * @param Request $request
     * @param object $login
     *
     * @return \Kotchasan\Http\Response
     */
    protected function handleDeleteAction(Request $request, $login)
    {
        $ids = $request->post('ids', [])->toInt();
        if (empty($ids)) {
            $id = $request->post('id')->toInt();
            $ids = $id > 0 ? [$id] : [];
        }
        if (empty($ids)) {
            return $this->errorResponse('No items selected', 400);
        }

        $count = \Bp\Record\Model::remove($ids, (int) $login->id);
        if (empty($count)) {
            return $this->errorResponse('Can not be performed this request. Because they do not find the information you need or you are not allowed', 403);
        }

        \Index\Log\Model::add(0, 'bp', 'Delete', 'Delete bp ID(s) : '.implode(', ', $ids), $login->id);

        return $this->redirectResponse('reload', 'Deleted successfully', 200, 0, 'table');
    }
}
