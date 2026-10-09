<?php
/**
 * @filesource modules/bp/controllers/record.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Bp\Record;

use Gcms\Api as ApiController;
use Kotchasan\Http\Request;

/**
 * api/bp/record/get|save - blood pressure entry form
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends ApiController
{
    /**
     * Read the data for the form.
     *
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

            $index = Model::get(
                $request->get('id')->toInt(),
                $request->get('family_id')->toInt(),
                (int) $login->id
            );
            if (!$index) {
                return $this->redirectResponse('/404', 'No data available', 404);
            }

            $category = \Bp\Category\Model::init((int) $login->id);

            return $this->successResponse([
                'data' => $index,
                'options' => [
                    'tag' => $category->toOptions('tag')
                ]
            ], 'OK');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500, $e);
        }
    }

    /**
     * Save the record.
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

            $memberId = (int) $login->id;
            $familyId = $request->post('family_id')->toInt();

            $save = [
                'family_id' => $familyId,
                'tag' => $request->post('tag')->toInt(),
                'height' => $request->post('height')->toFloat(),
                'weight' => $request->post('weight')->toFloat(),
                'temperature' => $request->post('temperature')->toFloat(),
                'waist' => $request->post('waist')->toFloat(),
                'glucose' => $request->post('glucose')->toString() === '' ? null : $request->post('glucose')->toFloat(),
                'spo2' => $request->post('spo2')->toString() === '' ? null : $request->post('spo2')->toInt(),
                'note' => $request->post('note')->topic(),
                // The legacy filter kept the whole timestamp rather than trimming to a date
                'create_date' => $request->post('create_date')->datetime()
            ];

            $items = [
                1 => [
                    'sys' => $request->post('sys1')->toInt(),
                    'dia' => $request->post('dia1')->toInt(),
                    'pulse' => $request->post('pulse1')->toInt()
                ],
                2 => [
                    'sys' => $request->post('sys2')->toInt(),
                    'dia' => $request->post('dia2')->toInt(),
                    'pulse' => $request->post('pulse2')->toInt()
                ]
            ];

            // The dashboard's quick form asks for the three numbers and nothing
            // else, so fill in what validate() still requires. Doing it here rather
            // than relaxing validate() keeps the full form's rules untouched.
            $quick = $request->post('quick')->toInt() === 1;
            if ($quick) {
                if (empty($save['create_date'])) {
                    $save['create_date'] = date('Y-m-d H:i:s');
                }
                if (empty($save['tag'])) {
                    $save['tag'] = \Bp\Category\Model::defaultTag($memberId);
                }
            }

            // Always verify ownership first
            $index = Model::get($request->post('id')->toInt(), $familyId, $memberId);
            if (!$index) {
                return $this->errorResponse('Can not be performed this request. Because they do not find the information you need or you are not allowed', 403);
            }

            // A new tag name may be typed straight in; create the category on the fly
            $tagText = $request->post('tag_text')->topic();
            if (empty($save['tag']) && $tagText !== '') {
                $save['tag'] = \Bp\Category\Model::save($memberId, 'tag', $tagText);
            }

            $errors = Model::validate($save, $items);
            if (!empty($errors)) {
                return $this->formErrorResponse($errors, 400);
            }

            $id = Model::save($index, $save, $items, $memberId);

            // The dashboard's quick form posts quick=1: it is already on the page
            // the user wants to stay on, and sending them to the history page
            // after every reading would make recording a second person tedious
            if ($quick) {
                return $this->successResponse(['id' => $id], 'Saved successfully');
            }

            return $this->redirectResponse('/bp-history?id='.$familyId, 'Saved successfully', 200, 1000);
        } catch (\Kotchasan\InputItemException $e) {
            return $this->errorResponse($e->getMessage(), 400);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500, $e);
        }
    }
}
