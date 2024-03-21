<?php
/**
 * @filesource modules/bp/controllers/categories.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Bp\Categories;

use Gcms\Login;
use Kotchasan\Html;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * module=bp-categories
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends \Gcms\Controller
{
    /**
     * หมวดหมู่
     *
     * @param Request $request
     *
     * @return string
     */
    public function render(Request $request)
    {
        $index = (object) array(
            // ประเภทที่ต้องการ
            'type' => $request->request('type')->topic(),
            // ชื่อหมวดหมู่ที่สามารถใช้งานได้
            'categories' => array('tag' => '{LNG_Tag}')
        );
        if (!isset($index->categories[$index->type])) {
            $index->type = \Kotchasan\ArrayTool::getFirstKey($index->categories);
        }
        // ข้อความ title bar
        $title = $index->categories[$index->type];
        $this->title = Language::trans('{LNG_List of} '.$title);
        // เลือกเมนู
        $this->menu = $index->type;
        // สมาชิก
        $login = Login::isMember();
        if ($login) {
            // แสดงผล
            $section = Html::create('section');
            // breadcrumbs
            $breadcrumbs = $section->add('nav', array(
                'class' => 'breadcrumbs'
            ));
            $ul = $breadcrumbs->add('ul');
            $ul->appendChild('<li><span class="icon-heart">{LNG_Blood Pressure}</span></li>');
            $ul->appendChild('<li><span>'.$title.'</span></li>');
            $section->add('header', array(
                'innerHTML' => '<h2 class="icon-category">'.$this->title.'</h2>'
            ));
            $div = $section->add('div', array(
                'class' => 'content_bg'
            ));
            // แสดงตาราง
            $div->appendChild(\Bp\Categories\View::create()->render($index, $login));
            // คืนค่า HTML
            return $section->render();
        }
        // 404
        return \Index\Error\Controller::execute($this, $request->getUri());
    }
}
