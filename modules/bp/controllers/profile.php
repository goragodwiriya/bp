<?php
/**
 * @filesource modules/bp/controllers/profile.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Bp\Profile;

use Gcms\Api as ApiController;
use Kotchasan\Http\Request;

/**
 * api/bp/profile/get|save - person details form
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends ApiController
{
    /**
     * Sex options.
     *
     * @return array
     */
    public static function sexOptions()
    {
        return [
            ['value' => 'm', 'text' => '{LNG_Male}'],
            ['value' => 'f', 'text' => '{LNG_Female}']
        ];
    }

    /**
     * Relationship to the recorder, for volunteers caring for people outside their family.
     *
     * @return array
     */
    public static function relationOptions()
    {
        $options = [];
        foreach (['self', 'family', 'neighbor', 'patient'] as $key) {
            $options[] = ['value' => $key, 'text' => '{LNG_RELATION_'.strtoupper($key).'}'];
        }
        return $options;
    }

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

            $index = Model::get($request->get('id')->toInt(), (int) $login->id);
            if (!$index) {
                return $this->redirectResponse('/404', 'No data available', 404);
            }

            $index->consent = empty($index->consent_at) ? 0 : 1;

            return $this->successResponse([
                'data' => $index,
                'options' => [
                    'sex' => self::sexOptions(),
                    'group_id' => \Bp\Group\Model::toOptions((int) $login->id),
                    'relation' => self::relationOptions()
                ]
            ], 'OK');
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

            $memberId = (int) $login->id;
            $index = Model::get($request->post('id')->toInt(), $memberId);
            if (!$index) {
                return $this->errorResponse('Can not be performed this request. Because they do not find the information you need or you are not allowed', 403);
            }

            $save = [
                'name' => $request->post('name')->topic(),
                'sex' => $request->post('sex')->filter('a-z'),
                'height' => $request->post('height')->toFloat(),
                'id_card' => $request->post('id_card')->number(),
                'birthday' => $request->post('birthday')->date(),
                'phone' => $request->post('phone')->number(),
                'address' => $request->post('address')->topic(),
                'country' => $request->post('country')->filter('A-Z'),
                'provinceID' => $request->post('provinceID')->number(),
                'province' => $request->post('province')->topic(),
                'zipcode' => $request->post('zipcode')->number(),
                // ---- Health volunteer mode ----
                'group_id' => $request->post('group_id')->toInt(),
                'relation' => $request->post('relation')->topic(),
                'chronic' => $request->post('chronic')->topic(),
                'smoking' => $request->post('smoking')->toBoolean() ? 1 : 0,
                'alcohol' => $request->post('alcohol')->toBoolean() ? 1 : 0,
                'diabetes' => $request->post('diabetes')->toBoolean() ? 1 : 0,
                'notify_optin' => $request->post('notify_optin')->toBoolean() ? 1 : 0
            ];

            // PDPA: record the time consent was first given, never overwrite it.
            // Withdrawing consent clears it back to NULL.
            $consent = $request->post('consent')->toBoolean();
            if ($consent && empty($index->consent_at)) {
                $save['consent_at'] = date('Y-m-d H:i:s');
            } elseif (!$consent) {
                $save['consent_at'] = null;
            }

            // The group must really belong to this member
            if (!empty($save['group_id']) && !\Bp\Group\Model::get($save['group_id'], $memberId)) {
                $save['group_id'] = 0;
            }

            $errors = Model::validate($save);
            if (!empty($errors)) {
                return $this->formErrorResponse($errors, 400);
            }

            Model::save($index, $save, $memberId);

            return $this->redirectResponse('/bp-family', 'Saved successfully', 200, 1000);
        } catch (\Kotchasan\InputItemException $e) {
            return $this->errorResponse($e->getMessage(), 400);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500, $e);
        }
    }
}
