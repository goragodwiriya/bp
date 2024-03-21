<?php
/**
 * @filesource modules/bp/views/family.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Bp\Family;

use Kotchasan\DataTable;
use Kotchasan\Date;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * module=bp-family
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class View extends \Gcms\View
{
    /**
     * ตารางรายชื่อสมาชิกในครอบครัว
     *
     * @param Request $request
     * @param array $login
     *
     * @return string
     */
    public function render(Request $request, $login)
    {
        // ค่าที่ส่งมา
        $params = array(
            'member_id' => $login['id']
        );
        // URL สำหรับส่งให้ตาราง
        $uri = $request->createUriWithGlobals(WEB_URL.'index.php');
        // ตาราง
        $table = new DataTable(array(
            /* Uri */
            'uri' => $uri,
            /* Model */
            'model' => \Bp\Family\Model::toDataTable($params),
            /* รายการต่อหน้า */
            'perPage' => $request->cookie('family_perPage', 30)->toInt(),
            /* เรียงลำดับ */
            'sort' => $request->cookie('family_sort', 'id desc')->toString(),
            /* ฟังก์ชั่นจัดรูปแบบการแสดงผลแถวของตาราง */
            'onRow' => array($this, 'onRow'),
            /* คอลัมน์ที่ไม่ต้องแสดงผล */
            'hideColumns' => array('id', 'dia'),
            /* คอลัมน์ที่สามารถค้นหาได้ */
            'searchColumns' => array('name', 'phone'),
            /* ตั้งค่าการกระทำของของตัวเลือกต่างๆ ด้านล่างตาราง ซึ่งจะใช้ร่วมกับการขีดถูกเลือกแถว */
            'action' => 'bp.php/bp/model/family/action',
            'actionCallback' => 'dataTableActionCallback',
            'actions' => array(
                array(
                    'id' => 'action',
                    'class' => 'ok',
                    'text' => '{LNG_With selected}',
                    'options' => array(
                        'delete' => '{LNG_Delete}'
                    )
                )
            ),
            /* ส่วนหัวของตาราง และการเรียงลำดับ (thead) */
            'headers' => array(
                'name' => array(
                    'text' => '{LNG_Name}',
                    'sort' => 'name'
                ),
                'sex' => array(
                    'text' => '{LNG_Sex}',
                    'class' => 'center',
                    'sort' => 'sex'
                ),
                'phone' => array(
                    'text' => '{LNG_Phone}'
                ),
                'birthday' => array(
                    'text' => '{LNG_age}',
                    'class' => 'center',
                    'sort' => 'birthday'
                ),
                'height' => array(
                    'text' => '{LNG_Height}',
                    'class' => 'center'
                ),
                'sys' => array(
                    'text' => 'BP',
                    'class' => 'center'
                ),
                'bmi' => array(
                    'text' => 'BMI',
                    'class' => 'center'
                ),
                'create_date' => array(
                    'text' => '{LNG_Created}',
                    'class' => 'center'
                ),
                'favorite' => array(
                    'text' => '',
                    'sort' => 'favorite',
                    'class' => 'center notext'
                )
            ),
            /* รูปแบบการแสดงผลของคอลัมน์ (tbody) */
            'cols' => array(
                'name' => array(
                    'class' => 'nowrap'
                ),
                'sex' => array(
                    'class' => 'center'
                ),
                'birthday' => array(
                    'class' => 'center'
                ),
                'height' => array(
                    'class' => 'center'
                ),
                'sys' => array(
                    'class' => 'center'
                ),
                'bmi' => array(
                    'class' => 'center'
                ),
                'create_date' => array(
                    'class' => 'center nowrap'
                ),
                'favorite' => array(
                    'class' => 'center'
                )
            ),
            /* ปุ่มแสดงในแต่ละแถว */
            'buttons' => array(
                array(
                    'class' => 'icon-new button pink',
                    'href' => $uri->createBackUri(array('module' => 'bp-record', 'family_id' => ':id')),
                    'text' => '{LNG_Record}'
                ),
                array(
                    'class' => 'icon-heart button orange',
                    'href' => $uri->createBackUri(array('module' => 'bp-history', 'id' => ':id')),
                    'text' => '{LNG_History}'
                ),
                array(
                    'class' => 'icon-stats button brown',
                    'href' => $uri->createBackUri(array('module' => 'bp-report', 'id' => ':id')),
                    'text' => '{LNG_Report}'
                ),
                array(
                    'class' => 'icon-edit button blue',
                    'href' => $uri->createBackUri(array('module' => 'bp-profile', 'id' => ':id')),
                    'text' => '{LNG_Edit}'
                )
            ),
            /* ปุ่มเพิม */
            'addNew' => array(
                'class' => 'float_button icon-register',
                'href' => $uri->createBackUri(array('module' => 'bp-profile', 'id' => 0)),
                'title' => '{LNG_Add} {LNG_a family member}'
            )
        ));
        // save cookie
        setcookie('family_perPage', $table->perPage, time() + 2592000, '/', HOST, HTTPS, true);
        setcookie('family_sort', $table->sort, time() + 2592000, '/', HOST, HTTPS, true);
        // คืนค่า HTML
        return $table->render();
    }

    /**
     * จัดรูปแบบการแสดงผลในแต่ละแถว
     *
     * @param array  $item ข้อมูลแถว
     * @param int    $o    ID ของข้อมูล
     * @param object $prop กำหนด properties ของ TR
     *
     * @return array คืนค่า $item กลับไป
     */
    public function onRow($item, $o, $prop)
    {
        $item['create_date'] = Date::format($item['create_date'], 'd M Y');
        $item['phone'] = self::showPhone($item['phone']);
        $item['sex'] = '<span class=icon-sex-'.$item['sex'].'></span>';
        $item['birthday'] = empty($item['birthday']) ? '' : Date::compare($item['birthday'], date('Y-m-d'))['year'];
        $item['favorite'] = '<a id=favorite_'.$item['id'].' class="icon-valid '.($item['favorite'] ? 'success' : 'disabled').'" title="'.Language::get('FAVORITE_TITLE', '', $item['favorite']).'"></a>';
        $item['height'] = empty($item['height']) ? '-' : $item['height'].' {LNG_Cm.}';
        if (empty($item['sys']) || empty($item['dia'])) {
            $item['sys'] = '-';
        } else {
            $item['sys'] = '<span class=color-'.\Bp\Calculator\Model::bpColor($item['sys'], $item['dia']).'>'.floor($item['sys']).'/'.floor($item['dia']).'</span>';
        }
        $item['bmi'] = empty($item['bmi']) ? '-' : '<span class=color-'.\Bp\Calculator\Model::bmiColor($item['bmi']).'>'.round($item['bmi'], 2).'</span>';
        return $item;
    }
}
