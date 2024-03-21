<?php
/**
 * @filesource modules/bp/controllers/record.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Bp\Record;

use Gcms\Login;
use Kotchasan\Html;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * module=bp-record
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends \Gcms\Controller
{
    /**
     * เพิ่ม-แก้ไข บันทึก
     *
     * @param Request $request
     *
     * @return string
     */
    public function render(Request $request)
    {
        // ข้อความ title bar
        $this->title = Language::get('Record');
        // เลือกเมนู
        $this->menu = 'bp';
        // สมาชิก
        $login = Login::isMember();
        // รายการที่ต้องการ
        $index = \Bp\Record\Model::get($request->request('id')->toInt(), $request->request('family_id')->toInt(), $login);
        if ($index) {
            // ข้อความ title bar
            $title = Language::get($index->id == 0 ? 'Add' : 'Edit');
            $this->title = $title.' '.$this->title;
            // แสดงผล
            $section = Html::create('section');
            // breadcrumbs
            $breadcrumbs = $section->add('nav', array(
                'class' => 'breadcrumbs'
            ));
            $ul = $breadcrumbs->add('ul');
            $ul->appendChild('<li><a href="'.WEB_URL.'index.php" class="icon-heart">{LNG_Blood Pressure}</a></li>');
            $ul->appendChild('<li><a href="index.php?module=bp&amp;id='.$index->family_id.'">'.$index->name.'</a></li>');
            $ul->appendChild('<li><span>'.$title.'</span></li>');
            $section->add('header', array(
                'innerHTML' => '<h2 class="icon-edit">'.$this->title.'</h2>'
            ));
            $div = $section->add('div', array(
                'class' => 'content_bg'
            ));
            // แสดงฟอร์ม
            $div->appendChild(\Bp\Record\View::create()->render($request, $index));
            // คืนค่า HTML
            return $section->render();
        }
        // 404
        return \Index\Error\Controller::execute($this, $request->getUri());
    }
}
