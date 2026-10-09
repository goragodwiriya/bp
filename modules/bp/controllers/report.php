<?php
/**
 * @filesource modules/bp/controllers/report.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Bp\Report;

use Bp\Calculator\Model as Calculator;
use Gcms\Api as ApiController;
use Kotchasan\Date;
use Kotchasan\Http\Request;

/**
 * api/bp/report - data for the pressure and weight charts
 *
 * The legacy view hand-built <div>s with style="height:NN%" across 270 lines.
 * This sends a data series and lets Now.js's GraphComponent draw it instead.
 * The safe-range bands are sent as numbers for CSS to overlay.
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends ApiController
{
    /**
     * Series data for GraphComponent.
     *
     * The shape GraphRenderer::validateData() demands is
     *   [{name: 'SYS', data: [{label: '27', value: 122}, ...]}, ...]
     * It must be a top-level array; do not wrap it in another key.
     *
     * @param Request $request
     *
     * @return \Kotchasan\Http\Response
     */
    public function graph(Request $request)
    {
        try {
            ApiController::validateMethod($request, 'GET');
            $this->initLanguage($request);

            $login = $this->authenticateRequest($request);
            if (!$login) {
                return $this->errorResponse('Unauthorized', 401);
            }

            $memberId = (int) $login->id;
            $profile = \Bp\Family\Model::get($request->get('id')->toInt(), $memberId);
            if (!$profile) {
                return $this->redirectResponse('/404', 'No data available', 404);
            }

            $t = Calculator::thresholds();
            $params = [
                'member_id' => $memberId,
                'family_id' => (int) $profile->id,
                'from' => $request->get('from', date('Y-m-d', strtotime('-'.$t['avg_days'].' days')))->date(),
                'to' => $request->get('to', date('Y-m-d'))->date(),
                'tag' => $request->get('tag')->topic()
            ];

            $records = [];
            foreach (Model::get($params) as $row) {
                if (!isset($records[$row->id])) {
                    $records[$row->id] = [
                        'label' => Date::format($row->create_date, 'j M'),
                        'height' => (float) $row->height,
                        'weight' => (float) $row->weight,
                        'sys' => [],
                        'dia' => [],
                        'pulse' => []
                    ];
                }
                foreach (['sys', 'dia', 'pulse'] as $k) {
                    if ($row->$k !== null) {
                        $records[$row->id][$k][] = (int) $row->$k;
                    }
                }
            }

            $avg = function ($values) {
                return empty($values) ? 0 : floor(array_sum($values) / count($values));
            };

            // Skip records with no value for this chart entirely; never send 0,
            // because a line chart would dive to zero and look like a collapse
            // (the legacy bar chart simply drew no bar, so it never hit this)
            if ($request->get('type')->filter('a-z') === 'weight') {
                $series = [
                    ['name' => \Kotchasan\Language::trans('{LNG_Weight}'), 'data' => []],
                    ['name' => 'BMI', 'data' => []]
                ];
                foreach ($records as $item) {
                    if ($item['weight'] <= 0) {
                        continue;
                    }
                    $series[0]['data'][] = ['label' => $item['label'], 'value' => round($item['weight'], 1)];
                    if ($item['height'] > 0) {
                        $series[1]['data'][] = [
                            'label' => $item['label'],
                            'value' => round(Calculator::bmi($item['height'], $item['weight']), 2)
                        ];
                    }
                }
            } else {
                $series = [
                    ['name' => \Kotchasan\Language::trans('{LNG_Systolic}'), 'data' => []],
                    ['name' => \Kotchasan\Language::trans('{LNG_Diastolic}'), 'data' => []],
                    ['name' => \Kotchasan\Language::trans('{LNG_Pulse}'), 'data' => []]
                ];
                foreach ($records as $item) {
                    if (empty($item['sys']) && empty($item['dia']) && empty($item['pulse'])) {
                        continue;
                    }
                    foreach ([0 => 'sys', 1 => 'dia', 2 => 'pulse'] as $i => $key) {
                        if (!empty($item[$key])) {
                            $series[$i]['data'][] = ['label' => $item['label'], 'value' => $avg($item[$key])];
                        }
                    }
                }
            }

            // Drop any series left with no points, or GraphRenderer receives an empty one
            $series = array_values(array_filter($series, function ($one) {
                return !empty($one['data']);
            }));

            return $this->successResponse($series, 'OK');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500, $e);
        }
    }

    /**
     * @param Request $request
     *
     * @return \Kotchasan\Http\Response
     */
    public function index(Request $request)
    {
        try {
            ApiController::validateMethod($request, 'GET');
            $this->initLanguage($request);

            $login = $this->authenticateRequest($request);
            if (!$login) {
                return $this->errorResponse('Unauthorized', 401);
            }

            $memberId = (int) $login->id;
            $profile = \Bp\Family\Model::get($request->get('id')->toInt(), $memberId);
            if (!$profile) {
                return $this->redirectResponse('/404', 'No data available', 404);
            }

            $t = Calculator::thresholds();
            $params = [
                'member_id' => $memberId,
                'family_id' => (int) $profile->id,
                'from' => $request->get('from', date('Y-m-d', strtotime('-'.$t['avg_days'].' days')))->date(),
                'to' => $request->get('to', date('Y-m-d'))->date(),
                'tag' => $request->get('tag')->topic()
            ];

            $category = \Bp\Category\Model::init($memberId);

            // Fold the several readings of one record together
            $records = [];
            $sysSum = 0;
            $diaSum = 0;
            $n = 0;
            $height = (float) $profile->height;
            $bmi = 0;

            foreach (Model::get($params) as $row) {
                if ($row->sys > 0 && $row->dia > 0) {
                    $n++;
                    $sysSum += $row->sys;
                    $diaSum += $row->dia;
                }
                if ($row->height > 0) {
                    $height = (float) $row->height;
                    if ($row->weight > 0) {
                        $bmi = Calculator::bmi($row->height, $row->weight);
                    }
                }
                if (!isset($records[$row->id])) {
                    $records[$row->id] = [
                        'id' => (int) $row->id,
                        'date' => $row->create_date,
                        'date_text' => Date::format($row->create_date),
                        'day' => Date::format($row->create_date, 'j'),
                        'tag_text' => $category->get('tag', $row->tag),
                        'height' => (float) $row->height,
                        'weight' => (float) $row->weight,
                        'glucose' => $row->glucose === null ? null : (float) $row->glucose,
                        'spo2' => $row->spo2 === null ? null : (int) $row->spo2,
                        '_sys' => [],
                        '_dia' => [],
                        '_pulse' => []
                    ];
                }
                if ($row->sys !== null) {
                    $records[$row->id]['_sys'][] = (int) $row->sys;
                }
                if ($row->dia !== null) {
                    $records[$row->id]['_dia'][] = (int) $row->dia;
                }
                if ($row->pulse !== null) {
                    $records[$row->id]['_pulse'][] = (int) $row->pulse;
                }
            }

            $series = [];
            foreach ($records as $item) {
                $sys = empty($item['_sys']) ? 0 : floor(array_sum($item['_sys']) / count($item['_sys']));
                $dia = empty($item['_dia']) ? 0 : floor(array_sum($item['_dia']) / count($item['_dia']));
                $pulse = empty($item['_pulse']) ? 0 : floor(array_sum($item['_pulse']) / count($item['_pulse']));
                unset($item['_sys'], $item['_dia'], $item['_pulse']);

                $item['sys'] = $sys;
                $item['dia'] = $dia;
                $item['pulse'] = $pulse;
                $item['bp_color'] = $sys > 0 || $dia > 0 ? Calculator::bpColor($sys, $dia) : '';
                $item['bp_level'] = Calculator::bpLevel($sys, $dia);
                $item['need_referral'] = Calculator::needReferral($sys, $dia);
                $item['bmi'] = $item['height'] > 0 && $item['weight'] > 0
                    ? round(Calculator::bmi($item['height'], $item['weight']), 2)
                    : null;
                $series[] = $item;
            }

            // The average counts every reading, not every record - as the legacy system did
            $sysAvg = $n > 0 ? floor($sysSum / $n) : 0;
            $diaAvg = $n > 0 ? floor($diaSum / $n) : 0;

            $age = empty($profile->birthday) || $profile->birthday === '0000-00-00'
                ? ''
                : Date::compare($profile->birthday, date('Y-m-d'))['year'];

            return $this->successResponse([
                'profile' => [
                    'id' => (int) $profile->id,
                    'name' => $profile->name,
                    'age' => $age,
                    'height' => $height
                ],
                'series' => $series,
                'summary' => [
                    'count' => count($series),
                    'sys' => $sysAvg,
                    'dia' => $diaAvg,
                    'bp_text' => $sysAvg > 0 && $diaAvg > 0 ? $sysAvg.'/'.$diaAvg : '-',
                    'bp_color' => $sysAvg > 0 && $diaAvg > 0 ? Calculator::bpColor($sysAvg, $diaAvg) : '',
                    'bmi' => $bmi > 0 ? round($bmi, 2) : null,
                    'bmi_color' => $bmi > 0 ? Calculator::bmiColor($bmi) : ''
                ],
                'bands' => [
                    'sys_min' => $t['sys_min'],
                    'sys_max' => $t['sys_max'],
                    'dia_min' => $t['dia_min'],
                    'dia_max' => $t['dia_max'],
                    'bmi_min' => 18.5,
                    'bmi_max' => 22.9
                ],
                'params' => $params,
                'options' => [
                    'tag' => $category->toOptions('tag')
                ]
            ], 'OK');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500, $e);
        }
    }
}
