<?php
/**
 * @filesource modules/bp/controllers/groups.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Bp\Groups;

use Gcms\Api as ApiController;
use Kotchasan\Date;
use Kotchasan\Http\Request;

/**
 * api/bp/groups - groups and households under care (health volunteer mode)
 *
 * Unlike the rest of the module this REQUIRES the can_manage_bp_care permission,
 * because it manages people outside one's own family rather than personal data.
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
    protected $allowedSortColumns = ['id', 'name', 'village', 'created_at'];

    /**
     * @param Request $request
     * @param object $login
     *
     * @return true|\Kotchasan\Http\Response
     */
    protected function checkAuthorization(Request $request, $login)
    {
        if (!ApiController::hasPermission($login, 'can_manage_bp_care')) {
            return $this->errorResponse('Permission required', 403);
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
        $sort = $request->get('sort')->toString();
        return [
            'member_id' => (int) $login->id,
            'sort' => $sort === '' ? 'name asc' : $sort
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
        return \Bp\Group\Model::toDataTable($params);
    }

    /**
     * @param array $datas
     * @param object $login
     *
     * @return array
     */
    protected function formatDatas(array $datas, $login = null): array
    {
        foreach ($datas as $item) {
            $item->members = (int) $item->members;
            $item->created_text = empty($item->created_at) ? '' : Date::format($item->created_at, 'd M Y');
            $item->place = trim(($item->village ?: '').(empty($item->moo) ? '' : ' '.\Kotchasan\Language::trans('{LNG_Moo}').' '.$item->moo));
        }
        return $datas;
    }

    /**
     * Delete groups (the people in them are kept).
     *
     * @param Request $request
     * @param object $login
     *
     * @return \Kotchasan\Http\Response
     */
    protected function handleDeleteAction(Request $request, $login)
    {
        if (!ApiController::hasPermission($login, 'can_manage_bp_care')) {
            return $this->errorResponse('Permission required', 403);
        }

        $ids = $request->post('ids', [])->toInt();
        if (empty($ids)) {
            $id = $request->post('id')->toInt();
            $ids = $id > 0 ? [$id] : [];
        }
        if (empty($ids)) {
            return $this->errorResponse('No items selected', 400);
        }

        $count = \Bp\Group\Model::remove($ids, (int) $login->id);
        if (empty($count)) {
            return $this->errorResponse('Can not be performed this request. Because they do not find the information you need or you are not allowed', 403);
        }

        \Index\Log\Model::add(0, 'bp', 'Delete', 'Delete bp group ID(s) : '.implode(', ', $ids), $login->id);

        return $this->redirectResponse('reload', 'Deleted successfully', 200, 0, 'table');
    }
}
