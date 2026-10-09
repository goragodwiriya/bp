<?php
/**
 * @filesource modules/bp/controllers/init.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Bp\Init;

use Gcms\Api as ApiController;

/**
 * bp module hooks - menus and permissions.
 *
 * Called automatically by \Gcms\Controller::initModule(); no registration needed.
 *
 * This module holds per-member personal data, in the same way omsin does.
 * Any signed-in member may record for themselves and their family without a special permission.
 * Isolation happens in toDataTable()/Model on every endpoint, not in checkAuthorization().
 *
 * The can_manage_bp_care permission applies only to health volunteer mode: groups,
 * the carer dashboard and visit rounds - caring for people outside one's own family.
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends \Gcms\Controller
{
    /**
     * Permissions this module adds to the permission management page.
     *
     * @param array $permissions
     * @param mixed $params
     * @param object|null $login
     *
     * @return array
     */
    public static function initPermission($permissions, $params = null, $login = null)
    {
        $permissions[] = [
            'value' => 'can_manage_bp_care',
            'text' => '{LNG_Can manage the} {LNG_Health volunteer mode}'
        ];
        return $permissions;
    }

    /**
     * Module menus.
     *
     * @param array $menus
     * @param mixed $params
     * @param object|null $login
     *
     * @return array
     */
    public static function initMenus($menus, $params = null, $login = null)
    {
        if (!$login) {
            return $menus;
        }

        $children = [
            [
                'title' => '{LNG_a family member}',
                'url' => '/bp-family',
                'icon' => 'icon-users'
            ],
            [
                'title' => '{LNG_Tag}',
                'url' => '/bp-categories?type=tag',
                'icon' => 'icon-tags'
            ],
            [
                'title' => '{LNG_How to use}',
                'url' => '/bp-about',
                'icon' => 'icon-help'
            ]
        ];

        // Health volunteer mode is only shown to those with the permission,
        if (ApiController::hasPermission($login, 'can_manage_bp_care')) {
            $children[] = [
                'title' => '{LNG_Health volunteer mode}',
                'url' => '/bp-care',
                'icon' => 'icon-group'
            ];
            $children[] = [
                'title' => '{LNG_Groups}',
                'url' => '/bp-groups',
                'icon' => 'icon-list'
            ];
        }

        // The core 'Dashboard' entry also points at '/', which this module now owns.
        // Leaving it would put two menu items on the same route with different names.
        // $menus is still keyed here - getMenus() only calls array_values() afterwards.
        unset($menus['dashboard']);

        return parent::insertMenuBefore($menus, [
            [
                'title' => '{LNG_Blood Pressure}',
                'icon' => 'icon-heart',
                'children' => $children
            ]
        ], 'settings');
    }
}
