<?php
/**
 * @filesource modules/bp/controllers/initmenu.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Bp\Initmenu;

use Gcms\Login;
use Kotchasan\Http\Request;

/**
 * Init Menu
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends \Kotchasan\KBase
{
    /**
     * ฟังก์ชั่นเริ่มต้นการทำงานของโมดูลที่ติดตั้ง
     * และจัดการเมนูของโมดูล
     *
     * @param Request                $request
     * @param \Index\Menu\Controller $menu
     * @param array                  $login
     */
    public static function execute(Request $request, $menu, $login)
    {
        if ($login) {
            // เมนู
            $menu->addTopLvlMenu('family', '{LNG_a family member}', 'index.php?module=bp-family', null, 'member');
            // category
            foreach (\Bp\Category\Model::init($login['id'])->items() as $key => $label) {
                $menu->addTopLvlMenu($key, $label, 'index.php?module=bp-categories&amp;type='.$key, null, 'member');
            }
            $menu->addTopLvlMenu('bpabout', '{LNG_How to use}', 'index.php?module=bp-about');
        }
    }
}
