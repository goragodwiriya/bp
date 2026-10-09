<?php
/**
 * @filesource modules/bp/controllers/visit.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Bp\Visit;

use Bp\Calculator\Model as Calculator;
use Gcms\Api as ApiController;
use Kotchasan\Date;
use Kotchasan\Http\Request;

/**
 * api/bp/visit/get|save|finish - health volunteer visit mode
 *
 * Walk a whole group and record many people in one pass, unlike the normal
 * entry page, which handles one person at a time.
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends ApiController
{
    /**
     * @param Request $request
     *
     * @return \Kotchasan\Http\Response
     */
    public function get(Request $request)
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
            $group = \Bp\Group\Model::get($groupId, $memberId);
            if (!$group) {
                return $this->redirectResponse('/404', 'No data available', 404);
            }

            // The default in ->get('date', ...) only applies when the key is ABSENT.
            // If the key is present but empty, ->date() returns null and visit_date becomes NULL,
            // which SQL rejects, so an empty value has to be handled here as well.
            $visitDate = $request->get('date')->date();
            if (empty($visitDate)) {
                $visitDate = date('Y-m-d');
            }
            $visit = Model::ensure($groupId, $visitDate, $memberId);

            $people = [];
            $done = 0;
            foreach (Model::people($groupId, $memberId, $visitDate) as $row) {
                $doneToday = (int) $row->done_today > 0;
                if ($doneToday) {
                    $done++;
                }
                $hasBp = $row->sys > 0 && $row->dia > 0;
                $people[] = [
                    'id' => (int) $row->id,
                    'name' => $row->name,
                    'sex_icon' => empty($row->sex) ? '' : 'icon-sex-'.$row->sex,
                    'age' => empty($row->birthday) || $row->birthday === '0000-00-00'
                        ? ''
                        : Date::compare($row->birthday, date('Y-m-d'))['year'],
                    'height' => (float) $row->height,
                    'chronic' => $row->chronic,
                    'last_bp' => $hasBp ? floor($row->sys).'/'.floor($row->dia) : '-',
                    'last_bp_color' => $hasBp ? Calculator::bpColor($row->sys, $row->dia) : '',
                    'done_today' => $doneToday
                ];
            }

            return $this->successResponse([
                'visit' => $visit,
                'group' => ['id' => (int) $group->id, 'name' => $group->name, 'village' => $group->village],
                'date' => $visitDate,
                'people' => $people,
                'progress' => ['done' => $done, 'total' => count($people)],
                'options' => ['tag' => \Bp\Category\Model::init($memberId)->toOptions('tag')]
            ], 'OK');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500, $e);
        }
    }

    /**
     * Record many people in one request.
     *
     * Rows with no pressure values are skipped silently rather than treated as errors,
     * since finding nobody home is perfectly normal.
     *
     * @param Request $request
     *
     * @return \Kotchasan\Http\Response
     */
    public function save(Request $request)
    {
        try {
            ApiController::validateMethod($request, 'POST');
            $this->validateCsrfToken($request);
            $this->initLanguage($request);

            $login = $this->authenticateRequest($request);
            if (!$login) {
                return $this->redirectResponse('/login', 'Unauthorized', 401);
            }
            if (!ApiController::canModify($login, ['can_manage_bp_care'])) {
                return $this->errorResponse('Permission required', 403);
            }

            $memberId = (int) $login->id;
            $groupId = $request->post('group_id')->toInt();
            $group = \Bp\Group\Model::get($groupId, $memberId);
            if (!$group) {
                return $this->errorResponse('Can not be performed this request. Because they do not find the information you need or you are not allowed', 403);
            }

            $visitDate = $request->post('date')->date();
            if (empty($visitDate)) {
                $visitDate = date('Y-m-d');
            }
            $visit = Model::ensure($groupId, $visitDate, $memberId);

            $body = $request->getParsedBody();
            $rows = isset($body['rows']) && is_array($body['rows']) ? $body['rows'] : [];
            $tag = $request->post('tag')->toInt();

            $saved = [];
            $errors = [];

            foreach ($rows as $i => $row) {
                if (!is_array($row)) {
                    continue;
                }
                $familyId = isset($row['family_id']) ? (int) $row['family_id'] : 0;
                $sys = isset($row['sys']) ? (int) $row['sys'] : 0;
                $dia = isset($row['dia']) ? (int) $row['dia'] : 0;
                $pulse = isset($row['pulse']) ? (int) $row['pulse'] : 0;

                // Nothing filled in at all means this person was not seen; skip
                if ($sys === 0 && $dia === 0 && $pulse === 0) {
                    continue;
                }

                $index = \Bp\Record\Model::get(0, $familyId, $memberId);
                if (!$index) {
                    $errors['rows_'.$i] = 'unknown family';
                    continue;
                }

                $save = [
                    'family_id' => $familyId,
                    'visit_id' => (int) $visit->id,
                    'recorder_id' => $memberId,
                    'tag' => $tag,
                    'height' => isset($row['height']) ? (float) $row['height'] : 0,
                    'weight' => isset($row['weight']) ? (float) $row['weight'] : 0,
                    'waist' => isset($row['waist']) ? (float) $row['waist'] : 0,
                    'temperature' => isset($row['temperature']) ? (float) $row['temperature'] : 0,
                    'glucose' => isset($row['glucose']) && $row['glucose'] !== '' ? (float) $row['glucose'] : null,
                    'spo2' => isset($row['spo2']) && $row['spo2'] !== '' ? (int) $row['spo2'] : null,
                    'note' => isset($row['note']) ? \Kotchasan\Text::topic($row['note']) : null,
                    'create_date' => $visitDate.' '.date('H:i:s')
                ];

                $items = [
                    1 => ['sys' => $sys, 'dia' => $dia, 'pulse' => $pulse],
                    2 => ['sys' => 0, 'dia' => 0, 'pulse' => 0]
                ];

                $rowErrors = \Bp\Record\Model::validate($save, $items);
                if (!empty($rowErrors)) {
                    $errors['rows_'.$i] = implode(', ', array_keys($rowErrors));
                    continue;
                }

                $id = \Bp\Record\Model::save($index, $save, $items, $memberId);
                $saved[] = ['family_id' => $familyId, 'id' => $id];
            }

            if (!empty($errors) && empty($saved)) {
                return $this->formErrorResponse($errors, 400);
            }

            \Index\Log\Model::add((int) $visit->id, 'bp', 'Visit',
                'Visit '.$group->name.' — saved '.count($saved).' record(s)', $memberId);

            return $this->successResponse([
                'visit_id' => (int) $visit->id,
                'saved' => $saved,
                'errors' => $errors
            ], 'Saved '.count($saved).' record(s)');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500, $e);
        }
    }

    /**
     * Close the visit round.
     *
     * @param Request $request
     *
     * @return \Kotchasan\Http\Response
     */
    public function finish(Request $request)
    {
        try {
            ApiController::validateMethod($request, 'POST');
            $this->validateCsrfToken($request);
            $this->initLanguage($request);

            $login = $this->authenticateRequest($request);
            if (!$login) {
                return $this->redirectResponse('/login', 'Unauthorized', 401);
            }
            if (!ApiController::canModify($login, ['can_manage_bp_care'])) {
                return $this->errorResponse('Permission required', 403);
            }

            $ok = Model::finish(
                $request->post('visit_id')->toInt(),
                (int) $login->id,
                $request->post('note')->textarea()
            );
            if (!$ok) {
                return $this->errorResponse('Can not be performed this request. Because they do not find the information you need or you are not allowed', 403);
            }

            return $this->redirectResponse('/bp-care', 'Saved successfully', 200, 1000);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500, $e);
        }
    }
}
