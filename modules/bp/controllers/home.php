<?php
/**
 * @filesource modules/bp/controllers/home.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Bp\Home;

use Kotchasan\Http\Request;

/**
 * module=project-home
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends \Gcms\Controller
{
    /**
     * ฟังก์ชั่นสร้าง card
     *
     * @param Request         $request
     * @param \Kotchasan\Html $card
     * @param array           $login
     */
    public static function addCard(Request $request, $card, $login)
    {
        if ($login) {
            // จำนวนสมาชิกในครอบครัว
            $count = \Bp\Home\Model::getCount($login['id']);
            if ($count == 0) {
                \Index\Home\Controller::renderCard($card, 'icon-users', '{LNG_a family member}', 0, '{LNG_Add} {LNG_a family member}', 'index.php?module=bp-profile');
            } else {
                foreach (\Bp\Home\Model::favorite($login['id']) as $item) {
                    \Index\Home\Controller::renderCard($card, 'icon-heart', self::$cfg->web_title, $item->name, '{LNG_Blood Pressure}', 'index.php?module=bp&amp;id='.$item->id);
                }
            }
        }
    }
}
