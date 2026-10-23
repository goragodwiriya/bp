<?php
/**
 * @filesource modules/bp/controllers/family.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Bp\Family;

use Bp\Calculator\Model as Calculator;
use Kotchasan\Date;
use Kotchasan\Http\Request;

/**
 * api/bp/family - table of the people whose readings are recorded
 *
 * There is no module permission: every signed-in member may use it,
 * but only sees their own data, because toDataTable() always binds member_id from $login.
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends \Gcms\Table
{
    /**
     * Sortable columns (guards against SQL injection)
     *
     * @var array
     */
    protected $allowedSortColumns = ['id', 'name', 'sex', 'birthday', 'height', 'sys', 'bmi', 'created_at', 'favorite'];

    /**
     * Extra table parameters.
     *
     * member_id always comes from $login and must never be taken from the request.
     *
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
            'group_id' => $request->get('group_id')->toInt(),
            'favorite' => $request->get('favorite')->number(),
            // Default: sort by name, like the legacy table
            'sort' => $sort === '' ? 'name asc' : $sort
        ];
    }

    /**
     * Query behind the table.
     *
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
     * Format the rows before they reach the page.
     *
     * Sends both the raw values (for sorting) and formatted ones (for display),
     * so a Now.js column never has to compute anything.
     *
     * @param array $datas
     * @param object $login
     *
     * @return array
     */
    protected function formatDatas(array $datas, $login = null): array
    {
        foreach ($datas as $item) {
            $item->age = empty($item->birthday) || $item->birthday === '0000-00-00'
                ? ''
                : Date::compare($item->birthday, date('Y-m-d'))['year'];

            $item->sex_icon = empty($item->sex) ? '' : 'icon-sex-'.$item->sex;
            $item->height_text = empty($item->height) ? '-' : $item->height.' '.self::trans('{LNG_Cm.}');
            $item->created_text = empty($item->created_at) ? '' : Date::format($item->created_at, 'd M Y');

            if (empty($item->sys) || empty($item->dia)) {
                $item->bp_text = '-';
                $item->bp_color = '';
                $item->bp_level = 'none';
            } else {
                $item->bp_text = floor($item->sys).'/'.floor($item->dia);
                $item->bp_color = Calculator::bpColor($item->sys, $item->dia);
                $item->bp_level = Calculator::bpLevel($item->sys, $item->dia);
            }
            $item->need_referral = Calculator::needReferral((float) $item->sys, (float) $item->dia);

            if (empty($item->bmi)) {
                $item->bmi_text = '-';
                $item->bmi_color = '';
            } else {
                $item->bmi_text = (string) round($item->bmi, 2);
                $item->bmi_color = Calculator::bmiColor($item->bmi);
            }

            $item->favorite = (int) $item->favorite;
        }
        return $datas;
    }

    /**
     * Translate a short label that gets concatenated with a number.
     *
     * @param string $text
     *
     * @return string
     */
    protected static function trans($text)
    {
        return \Kotchasan\Language::trans($text);
    }

    /**
     * Delete people, together with their readings.
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

        $count = Model::remove($ids, (int) $login->id);
        if (empty($count)) {
            return $this->errorResponse('Can not be performed this request. Because they do not find the information you need or you are not allowed', 403);
        }

        \Index\Log\Model::add(0, 'bp', 'Delete', 'Delete family ID(s) : '.implode(', ', $ids), $login->id);

        return $this->redirectResponse('reload', 'Deleted successfully', 200, 0, 'table');
    }

    /**
     * Toggle the favourite flag.
     *
     * @param Request $request
     * @param object $login
     *
     * @return \Kotchasan\Http\Response
     */
    protected function handleFavoriteAction(Request $request, $login)
    {
        $id = $request->post('id')->toInt();
        $favorite = Model::toggleFavorite($id, (int) $login->id);
        if ($favorite === null) {
            return $this->errorResponse('Can not be performed this request. Because they do not find the information you need or you are not allowed', 403);
        }
        return $this->redirectResponse('reload', 'Saved successfully', 200, 0, 'table');
    }
}
