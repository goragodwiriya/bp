<?php
/**
 * @filesource modules/bp/controllers/categories.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Bp\Categories;

use Gcms\Api as ApiController;
use Kotchasan\Http\Request;
use Kotchasan\Text;

/**
 * api/bp/categories/get|save - edit a member's own tags
 *
 * Unlike \Index\Categories\Controller this needs no can_config permission,
 * because these tags are personal to each member rather than system-wide.
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends ApiController
{
    /**
     * Category types that can be edited
     *
     * @var array
     */
    protected $categories = [
        'tag' => '{LNG_Tag}'
    ];

    /**
     * This module stores a single language, like the legacy system
     *
     * @var bool
     */
    protected $multiLanguage = false;

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

            $type = $request->get('type', 'tag')->filter('a-z_');
            if (!isset($this->categories[$type])) {
                return $this->redirectResponse('/404', 'No data available', 404);
            }

            return $this->successResponse([
                'data' => [
                    'type' => $type,
                    'title' => $this->categories[$type],
                    'options' => [
                        'columns' => Model::getColumns($this->multiLanguage),
                        'data' => Model::get($type, (int) $login->id, $this->multiLanguage)
                    ]
                ]
            ], 'Category details retrieved');
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
            if (!ApiController::canModify($login)) {
                return $this->errorResponse('Permission required', 403);
            }

            $type = $request->post('type', 'tag')->filter('a-z_');
            if (!isset($this->categories[$type])) {
                return $this->redirectResponse('/404', 'No data available', 404);
            }

            $ids = $request->post('id', [])->topic();
            $languages = \Index\Language\Model::getLanguages();

            $langValues = [];
            foreach ($languages as $lng) {
                $langValues[$lng] = $request->post($lng, [])->topic();
            }

            $error = [];
            $save = [];
            $check = [];
            foreach ($ids as $key => $id) {
                $category_id = Text::topic($id);
                if ($category_id === '') {
                    $error['category_id_'.$key] = 'Category ID is required';
                    continue;
                }
                foreach ($languages as $lng) {
                    if (isset($save[$category_id.$lng])) {
                        $error['category_id_'.$key] = 'Category ID '.$category_id.' already exists';
                    } else {
                        $topic = Text::topic(isset($langValues[$lng][$key]) ? $langValues[$lng][$key] : '');
                        if ($topic !== '') {
                            $save[$category_id.$lng] = [
                                'category_id' => $category_id,
                                'language' => $lng,
                                'topic' => $topic
                            ];
                            $check[$category_id][$topic] = $lng;
                        }
                    }
                }
                if (empty($check[$category_id])) {
                    $error['category_'.$languages[0].'_'.$key] = 'Please fill in';
                }
            }

            if (!empty($save) && !empty($error)) {
                return $this->formErrorResponse($error);
            }

            if (!$this->multiLanguage) {
                $unique = [];
                foreach ($save as $item) {
                    $key = $item['category_id'].'|'.$item['topic'];
                    if (!isset($unique[$key])) {
                        $unique[$key] = $item;
                    }
                }
                $save = $unique;
            }

            Model::save($type, (int) $login->id, $save, $this->multiLanguage);

            \Index\Log\Model::add(0, 'bp', 'Save', 'Categories saved '.ucfirst($type).' ('.count($save).' rows)', $login->id);

            return $this->redirectResponse('reload', 'Saved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500, $e);
        }
    }
}
