<?php
/**
 * @filesource modules/bp/controllers/about.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Bp\About;

use Gcms\Login;
use Kotchasan\Html;
use Kotchasan\Http\Request;
use Kotchasan\Language;
use Kotchasan\Template;
use \Bp\Calculator\Model as Calculator;

/**
 * module=bp-about
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends \Gcms\Controller
{
    /**
     * ตารางรายการ Room
     *
     * @param Request $request
     *
     * @return string
     */
    public function render(Request $request)
    {
        // ข้อความ title bar
        $this->title = Language::get('How to use');
        // เลือกเมนู
        $this->menu = 'bpabout';
        // สมาชิก, หน้าที่เลือก
        if (Login::isMember() && $file = self::getPage('about')) {
            // content
            $content = Template::createFromFile($file);
            $content->add(array(
                '/{DIA_HIGHT}/' => Calculator::$dia_hight,
                '/{DIA_MAX}/' => Calculator::$dia_max,
                '/{DIA_MIN}/' => Calculator::$dia_min,
                '/{SYS_HIGHT}/' => Calculator::$sys_hight,
                '/{SYS_MAX}/' => Calculator::$sys_max,
                '/{SYS_MIN}/' => Calculator::$sys_min
            ));
            $content = $content->render();
            // title
            if (preg_match('/<h3[^>]{0,}>(.*)<\/h3>/', $content, $match)) {
                $this->title = strip_tags($match[1]);
            }
            // แสดงผล
            $section = Html::create('section', array(
                'class' => 'content_bg'
            ));
            // แสดงเนื้อหา
            $section->appendChild($content);
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

    /**
     * @param $module
     */
    protected static function getPage($module)
    {
        if (is_file(ROOT_PATH.'modules/bp/views/'.$module.'_'.LANGUAGE.'.html')) {
            return ROOT_PATH.'modules/bp/views/'.$module.'_'.LANGUAGE.'.html';
        } elseif (is_file(ROOT_PATH.'modules/bp/views/'.$module.'.html')) {
            return ROOT_PATH.'modules/bp/views/'.$module.'.html';
        }
        return null;
    }
}
