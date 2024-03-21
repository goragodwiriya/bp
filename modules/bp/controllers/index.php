<?php
/**
 * @filesource modules/bp/controllers/index.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Bp\Index;

use Gcms\Login;
use Kotchasan\Collection;
use Kotchasan\Html;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * module=bp
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends \Gcms\Controller
{
    /**
     * ประวัติการบันทึก
     *
     * @param Request $request
     *
     * @return string
     */
    public function render(Request $request)
    {
        // ข้อความ title bar
        $this->title = Language::get('Blood Pressure');
        // เลือกเมนู
        $this->menu = 'bp';
        // สมาชิก
        $login = Login::isMember();
        // สมาชิกที่เลือก
        $profile = \Bp\Profile\Model::get($request->request('id')->toInt(), $login);
        if ($profile) {
            // ข้อความ title bar
            $this->title .= ' '.$profile->name;
            // แสดงผล
            $section = Html::create('section');
            // breadcrumbs
            $breadcrumbs = $section->add('nav', array(
                'class' => 'breadcrumbs'
            ));
            $ul = $breadcrumbs->add('ul');
            $ul->appendChild('<li><a href="'.WEB_URL.'index.php" class="icon-home">{LNG_Home}</a></li>');
            $ul->appendChild('<li><span">'.$profile->name.'</span></li>');
            $ul->appendChild('<li><span>{LNG_Blood Pressure}</span></li>');

            // card
            $card = new Collection();
            \Index\Home\Controller::renderCard($card, 'icon-new', $profile->name, '{LNG_Record}', '{LNG_Record} {LNG_Blood Pressure}', 'index.php?module=bp-record&amp;family_id='.$profile->id);
            \Index\Home\Controller::renderCard($card, 'icon-heart', $profile->name, '{LNG_History}', '{LNG_History} {LNG_Blood Pressure}', 'index.php?module=bp-history&amp;id='.$profile->id);
            \Index\Home\Controller::renderCard($card, 'icon-stats', $profile->name, '{LNG_Report}', '{LNG_Report} {LNG_Blood Pressure}', 'index.php?module=bp-report&amp;id='.$profile->id);
            // dashboard
            $dashboard = $section->add('article', array(
                'class' => 'dashboard clear'
            ));
            $dashboard->add('header', array(
                'innerHTML' => '<h2 class="icon-heart">'.$this->title.'</h2>'
            ));
            // grid
            $grid = $dashboard->add('div', array(
                'class' => 'ggrid'
            ));
            // render card
            foreach ($card as $item) {
                $grid->add('div', array(
                    'class' => 'block4 card',
                    'innerHTML' => $item
                ));
            }
            $content = $section->render();
            $content .= '<footer class=float_bottom_menu>';
            $content .= '<a class=bp-family title="{LNG_a family member}" href="'.WEB_URL.'index.php?module=bp-family"><span class=icon-users></span></a>';
            $content .= '</footer>';
            // คืนค่า HTML
            return $content;
        }
        // 404
        return \Index\Error\Controller::execute($this, $request->getUri());
    }
}
