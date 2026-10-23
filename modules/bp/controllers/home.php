<?php
/**
 * @filesource modules/bp/controllers/home.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Bp\Home;

use Bp\Calculator\Model as Calculator;
use Gcms\Api as ApiController;
use Kotchasan\Date;
use Kotchasan\Http\Request;

/**
 * api/bp/home - the daily dashboard, which is this app's home page
 *
 * The legacy system injected cards into the system Dashboard via Home\Controller::addCard.
 * adminframework has no such hook (initModule only knows initMenus/initPermission), so the
 * module claims '/' itself by re-registering the route from admin.js - RouterManager.routes
 * is a Map, and the module registers after the app's own routes, so the later one wins.
 *
 * The dashboard has two jobs and everything here serves one of them:
 *   1. record today's reading without navigating anywhere
 *   2. show what is worth knowing at a glance
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends ApiController
{
    /**
     * Everything the daily dashboard shows.
     *
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
            $people = Model::people($memberId);
            $latest = Model::latestReadings($memberId);
            $previous = Model::previousReadings($memberId);

            $list = [];
            $alerts = [];
            $doneToday = 0;
            $defaultId = 0;

            foreach ($people as $item) {
                $id = (int) $item->id;
                $done = (int) $item->done_today > 0;
                if ($done) {
                    $doneToday++;
                } elseif ($defaultId === 0) {
                    // First person still missing today's reading - the one to record next
                    $defaultId = $id;
                }

                $one = self::toCard($item);
                $one['done_today'] = $done;
                // data-class must receive a plain class name, never a ternary:
                // TemplateManager splits the value on ':' as a class separator,
                // so "a ? 'x' : ''" reaches DOMTokenList as a token with spaces
                // and throws InvalidCharacterError
                $one['chip_class'] = $done ? 'bp-chip-done' : '';
                $one['favorite'] = (int) $item->favorite === 1;
                $one['height'] = (float) $item->height;
                $one['last_text'] = empty($item->last_measured)
                    ? ''
                    : Date::format($item->last_measured, 'j M');
                $one['days_ago'] = empty($item->last_measured)
                    ? null
                    : (int) floor((strtotime(date('Y-m-d')) - strtotime(date('Y-m-d', strtotime($item->last_measured)))) / 86400);
                // Built here, not in the template: ExpressionEvaluator only resolves
                // functions registered with it, so Now.translate() inside a data-text
                // expression evaluates to nothing and the label silently disappears
                if ($one['days_ago'] === null) {
                    $one['ago_text'] = \Kotchasan\Language::trans('{LNG_never}');
                } elseif ($one['days_ago'] === 0) {
                    $one['ago_text'] = \Kotchasan\Language::trans('{LNG_Today}');
                } else {
                    $one['ago_text'] = $one['days_ago'].' '.\Kotchasan\Language::trans('{LNG_days ago}');
                }

                // The genuine latest reading, not the 7-day average kept on bp_family
                if (isset($latest[$id])) {
                    $sys = (int) $latest[$id]->sys;
                    $dia = (int) $latest[$id]->dia;
                    $one['latest_text'] = $sys.'/'.$dia;
                    $one['latest_color'] = Calculator::bpColor($sys, $dia);
                    $one['latest_level'] = Calculator::bpLevel($sys, $dia);
                    $one['latest_referral'] = Calculator::needReferral($sys, $dia);
                    $one['trend'] = self::trend($sys, isset($previous[$id]) ? (int) $previous[$id]->sys : null);
                    if ($one['latest_referral']) {
                        $alerts[] = [
                            'id' => $id,
                            'name' => $item->name,
                            'text' => $one['latest_text'],
                            'record_url' => $one['record_url'],
                            'history_url' => $one['history_url']
                        ];
                    }
                } else {
                    $one['latest_text'] = '-';
                    $one['latest_color'] = '';
                    $one['latest_level'] = 'none';
                    $one['latest_referral'] = false;
                    $one['trend'] = '';
                }
                $list[] = $one;
            }

            $total = count($list);
            if ($defaultId === 0 && $total > 0) {
                // Everyone is done today; default to the first person anyway so the
                // quick form is still usable for a second reading
                $defaultId = $list[0]['id'];
            }

            $favorites = array_values(array_filter($list, function ($one) {
                return $one['favorite'];
            }));

            return $this->successResponse([
                'total' => $total,
                'is_empty' => $total === 0,
                'cards' => empty($favorites) ? $list : $favorites,
                'people' => $list,
                'default_id' => $defaultId,
                'alerts' => $alerts,
                'today' => [
                    'date_text' => Date::format(date('Y-m-d H:i:s'), 'j F Y'),
                    'done' => $doneToday,
                    'total' => $total,
                    'all_done' => $total > 0 && $doneToday >= $total,
                    'done_class' => $total > 0 && $doneToday >= $total ? 'positive' : ''
                ],
                'stats' => [
                    'month_records' => Model::monthCount($memberId),
                    'streak' => Model::streak($memberId),
                    'people' => $total
                ]
            ], 'OK');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500, $e);
        }
    }

    /**
     * Which way the systolic reading moved since the previous one.
     *
     * Only a change of more than 5 mmHg counts, because normal measurement
     * noise is bigger than that and an arrow that flips every reading is noise
     * rather than information.
     *
     * @param int $now
     * @param int|null $before
     *
     * @return string 'up', 'down', 'same' or '' when there is nothing to compare
     */
    protected static function trend($now, $before)
    {
        if ($before === null || $before <= 0 || $now <= 0) {
            return '';
        }
        $diff = $now - $before;
        if (abs($diff) <= 5) {
            return 'same';
        }
        return $diff > 0 ? 'up' : 'down';
    }

    /**
     * One person's summary card.
     *
     * @param Request $request
     *
     * @return \Kotchasan\Http\Response
     */
    public function person(Request $request)
    {
        try {
            ApiController::validateMethod($request, 'GET');
            $this->initLanguage($request);

            $login = $this->authenticateRequest($request);
            if (!$login) {
                return $this->errorResponse('Unauthorized', 401);
            }

            $profile = \Bp\Family\Model::get($request->get('id')->toInt(), (int) $login->id);
            if (!$profile) {
                return $this->redirectResponse('/404', 'No data available', 404);
            }

            return $this->successResponse([
                'data' => self::toCard($profile)
            ], 'OK');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500, $e);
        }
    }

    /**
     * Every threshold in force, for the how-to page.
     *
     * @param Request $request
     *
     * @return \Kotchasan\Http\Response
     */
    public function thresholds(Request $request)
    {
        try {
            ApiController::validateMethod($request, 'GET');
            $this->initLanguage($request);

            $login = $this->authenticateRequest($request);
            if (!$login) {
                return $this->errorResponse('Unauthorized', 401);
            }

            return $this->successResponse(Calculator::thresholds(), 'OK');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500, $e);
        }
    }

    /**
     * Turn a bp_family row into a display-ready card.
     *
     * @param object $item
     *
     * @return array
     */
    protected static function toCard($item)
    {
        $hasBp = !empty($item->sys) && !empty($item->dia);
        $age = empty($item->birthday) || $item->birthday === '0000-00-00'
            ? ''
            : Date::compare($item->birthday, date('Y-m-d'))['year'];

        return [
            'id' => (int) $item->id,
            'name' => $item->name,
            'age' => $age,
            'sex_icon' => empty($item->sex) ? '' : 'icon-sex-'.$item->sex,
            'bp_text' => $hasBp ? floor($item->sys).'/'.floor($item->dia) : '-',
            'bp_color' => $hasBp ? Calculator::bpColor($item->sys, $item->dia) : '',
            'bp_level' => $hasBp ? Calculator::bpLevel($item->sys, $item->dia) : 'none',
            'need_referral' => $hasBp ? Calculator::needReferral($item->sys, $item->dia) : false,
            'bmi_text' => empty($item->bmi) ? '-' : (string) round($item->bmi, 2),
            'bmi_color' => empty($item->bmi) ? '' : Calculator::bmiColor($item->bmi),
            'record_url' => '/bp-record?family_id='.((int) $item->id),
            'history_url' => '/bp-history?id='.((int) $item->id),
            'report_url' => '/bp-report?id='.((int) $item->id)
        ];
    }
}
