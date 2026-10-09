<?php
/**
 * @filesource modules/bp/controllers/group.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Bp\Group;

use Gcms\Api as ApiController;
use Kotchasan\Http\Request;

/**
 * api/bp/group/get|save - group / household form
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

            $id = $request->get('id')->toInt();
            if (empty($id)) {
                $index = (object) [
                    'id' => 0,
                    'name' => '',
                    'village' => '',
                    'moo' => '',
                    'address' => '',
                    'note' => '',
                    'latitude' => null,
                    'longitude' => null
                ];
            } else {
                $index = Model::get($id, (int) $login->id);
                if (!$index) {
                    return $this->redirectResponse('/404', 'No data available', 404);
                }
            }

            return $this->successResponse(['data' => $index], 'OK');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500, $e);
        }
    }

    /**
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
            $id = $request->post('id')->toInt();
            $index = empty($id) ? (object) ['id' => 0] : Model::get($id, $memberId);
            if (!$index) {
                return $this->errorResponse('Can not be performed this request. Because they do not find the information you need or you are not allowed', 403);
            }

            $save = [
                'name' => $request->post('name')->topic(),
                'village' => $request->post('village')->topic(),
                'moo' => $request->post('moo')->topic(),
                'address' => $request->post('address')->topic(),
                'note' => $request->post('note')->textarea(),
                'latitude' => $request->post('latitude')->toString() === '' ? null : $request->post('latitude')->toFloat(),
                'longitude' => $request->post('longitude')->toString() === '' ? null : $request->post('longitude')->toFloat()
            ];

            if ($save['name'] === '') {
                return $this->formErrorResponse(['name' => 'Please fill in'], 400);
            }

            Model::save($index, $save, $memberId);

            return $this->redirectResponse('/bp-groups', 'Saved successfully', 200, 1000);
        } catch (\Kotchasan\InputItemException $e) {
            return $this->errorResponse($e->getMessage(), 400);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500, $e);
        }
    }
}
