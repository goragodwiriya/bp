<?php
/**
 * @filesource modules/bp/controllers/care.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Bp\Care;

use Bp\Calculator\Model as Calculator;
use Gcms\Api as ApiController;
use Kotchasan\Date;
use Kotchasan\Http\Request;

/**
 * api/bp/care - health volunteer dashboard
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends ApiController
{
    /**
     * Days after which a person counts as "not measured recently"
     */
    const STALE_DAYS = 30;

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
            if (!ApiController::hasPermission($login, 'can_manage_bp_care')) {
                return $this->errorResponse('Permission required', 403);
            }

            $memberId = (int) $login->id;
            $groupId = $request->get('group_id')->toInt();
            $today = new \DateTime('today');
            $staleBefore = (new \DateTime('today'))->modify('-'.self::STALE_DAYS.' days');

            $latest = Model::latestReadings($memberId);
            $people = [];
            $counters = [
                'total' => 0,
                'referral' => 0,
                'high' => 0,
                'prehigh' => 0,
                'normal' => 0,
                'low' => 0,
                'no_data' => 0,
                'stale' => 0,
                'no_consent' => 0
            ];

            foreach (Model::people($memberId, $groupId) as $row) {
                $counters['total']++;

                // 7-day average, useful for the trend
                $avgSys = (float) $row->sys;
                $avgDia = (float) $row->dia;
                $hasAvg = $avgSys > 0 && $avgDia > 0;

                // Most recent reading, used to judge urgency
                $last = isset($latest[(int) $row->id]) ? $latest[(int) $row->id] : null;
                $sys = $last ? (float) $last->sys : $avgSys;
                $dia = $last ? (float) $last->dia : $avgDia;
                $hasBp = $sys > 0 && $dia > 0;
                $level = $hasBp ? Calculator::bpLevel($sys, $dia) : 'none';

                $daysSince = null;
                if (!empty($row->last_measured)) {
                    $lastDate = new \DateTime(substr($row->last_measured, 0, 10));
                    $daysSince = (int) $today->diff($lastDate)->format('%a');
                }
                $stale = empty($row->last_measured)
                    || new \DateTime(substr($row->last_measured, 0, 10)) < $staleBefore;

                if ($level === 'critical') {
                    $counters['referral']++;
                } elseif ($level === 'high') {
                    $counters['high']++;
                } elseif ($level === 'prehigh') {
                    $counters['prehigh']++;
                } elseif ($level === 'low') {
                    $counters['low']++;
                } elseif ($level === 'normal') {
                    $counters['normal']++;
                } else {
                    $counters['no_data']++;
                }
                if ($stale) {
                    $counters['stale']++;
                }
                if (empty($row->consent_at)) {
                    $counters['no_consent']++;
                }

                $people[] = [
                    'id' => (int) $row->id,
                    'name' => $row->name,
                    'sex_icon' => empty($row->sex) ? '' : 'icon-sex-'.$row->sex,
                    'age' => empty($row->birthday) || $row->birthday === '0000-00-00'
                        ? ''
                        : Date::compare($row->birthday, date('Y-m-d'))['year'],
                    'phone' => $row->phone,
                    'group_name' => $row->group_name,
                    'bp_text' => $hasBp ? floor($sys).'/'.floor($dia) : '-',
                    'bp_color' => $hasBp ? Calculator::bpColor($sys, $dia) : '',
                    'bp_level' => $level,
                    'avg_text' => $hasAvg ? floor($avgSys).'/'.floor($avgDia) : '-',
                    'avg_color' => $hasAvg ? Calculator::bpColor($avgSys, $avgDia) : '',
                    'need_referral' => $level === 'critical',
                    'bmi_text' => empty($row->bmi) ? '-' : (string) round($row->bmi, 2),
                    'bmi_color' => empty($row->bmi) ? '' : Calculator::bmiColor($row->bmi),
                    'chronic' => $row->chronic,
                    'risk_flags' => self::riskFlags($row),
                    'last_measured' => $row->last_measured,
                    'last_measured_text' => empty($row->last_measured)
                        ? '-'
                        : Date::format($row->last_measured, 'd M Y'),
                    'days_since' => $daysSince,
                    'stale' => $stale,
                    // Send the class name ready-made: data-class treats ':' as a separator and
                    // already means "class name: condition",
                    // so a ternary containing ':' is misparsed and throws InvalidCharacterError
                    'stale_class' => $stale ? 'bp-orange' : '',
                    'has_consent' => !empty($row->consent_at),
                    'record_url' => '/bp-record?family_id='.((int) $row->id),
                    'history_url' => '/bp-history?id='.((int) $row->id)
                ];
            }

            // Put the urgent cases first: referral > high > overdue > everyone else
            usort($people, function ($a, $b) {
                $rank = function ($p) {
                    if ($p['need_referral']) {
                        return 0;
                    }
                    if ($p['bp_level'] === 'high') {
                        return 1;
                    }
                    if ($p['stale']) {
                        return 2;
                    }
                    return 3;
                };
                $ra = $rank($a);
                $rb = $rank($b);
                return $ra === $rb ? strcmp($a['name'], $b['name']) : $ra - $rb;
            });

            return $this->successResponse([
                'summary' => $counters,
                'people' => $people,
                'groups' => \Bp\Group\Model::toOptions($memberId),
                'group_id' => $groupId,
                'records_this_month' => Model::recordsThisMonth($memberId),
                'stale_days' => self::STALE_DAYS
            ], 'OK');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500, $e);
        }
    }

    /**
     * Export the people under care as CSV, for handing on to a health centre.
     *
     * Responds with JSON carrying the CSV body, not a file attachment,
     * Authorization header that a plain link cannot send.
     * so the page must fetch it through http and build a Blob itself.
     *
     * @param Request $request
     *
     * @return \Kotchasan\Http\Response
     */
    public function export(Request $request)
    {
        try {
            ApiController::validateMethod($request, 'GET');
            $this->initLanguage($request);

            $login = $this->authenticateRequest($request);
            if (!$login) {
                return $this->errorResponse('Unauthorized', 401);
            }
            if (!ApiController::hasPermission($login, 'can_manage_bp_care')) {
                return $this->errorResponse('Permission required', 403);
            }

            $memberId = (int) $login->id;
            $groupId = $request->get('group_id')->toInt();
            $latest = Model::latestReadings($memberId);

            $headers = [
                'name', 'sex', 'age', 'phone', 'group', 'chronic',
                'last_sys', 'last_dia', 'last_pulse', 'last_measured',
                'avg_sys', 'avg_dia', 'bmi', 'level', 'consent'
            ];

            $lines = [self::csvLine($headers)];
            foreach (Model::people($memberId, $groupId) as $row) {
                $last = isset($latest[(int) $row->id]) ? $latest[(int) $row->id] : null;
                $sys = $last ? (float) $last->sys : (float) $row->sys;
                $dia = $last ? (float) $last->dia : (float) $row->dia;

                $lines[] = self::csvLine([
                    $row->name,
                    $row->sex,
                    empty($row->birthday) || $row->birthday === '0000-00-00'
                        ? '' : Date::compare($row->birthday, date('Y-m-d'))['year'],
                    $row->phone,
                    $row->group_name,
                    $row->chronic,
                    $last ? $last->sys : '',
                    $last ? $last->dia : '',
                    $last ? $last->pulse : '',
                    $row->last_measured,
                    $row->sys > 0 ? floor($row->sys) : '',
                    $row->dia > 0 ? floor($row->dia) : '',
                    empty($row->bmi) ? '' : round($row->bmi, 2),
                    $sys > 0 && $dia > 0 ? Calculator::bpLevel($sys, $dia) : 'none',
                    empty($row->consent_at) ? 'no' : 'yes'
                ]);
            }

            return $this->successResponse([
                'filename' => 'bp-care-'.date('Ymd').'.csv',
                // A BOM so Excel on Windows reads Thai correctly
                'content' => "\xEF\xBB\xBF".implode("\r\n", $lines)."\r\n",
                'rows' => count($lines) - 1
            ], 'OK');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500, $e);
        }
    }

    /**
     * Build one CSV line, quoting per RFC 4180.
     *
     * @param array $values
     *
     * @return string
     */
    protected static function csvLine(array $values)
    {
        return implode(',', array_map(function ($value) {
            $value = (string) $value;
            if (preg_match('/[",\r\n]/', $value)) {
                return '"'.str_replace('"', '""', $value).'"';
            }
            return $value;
        }, $values));
    }

    /**
     * Risk flags worth seeing straight from the list.
     *
     * @param object $row
     *
     * @return array
     */
    protected static function riskFlags($row)
    {
        $flags = [];
        if (!empty($row->smoking)) {
            $flags[] = '{LNG_Smoking}';
        }
        if (!empty($row->diabetes)) {
            $flags[] = '{LNG_Diabetes}';
        }
        if (!empty($row->chronic)) {
            $flags[] = $row->chronic;
        }
        return $flags;
    }
}
