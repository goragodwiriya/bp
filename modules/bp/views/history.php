<?php
/**
 * @filesource modules/bp/views/history.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Bp\History;

use Kotchasan\DataTable;
use Kotchasan\Date;
use Kotchasan\Http\Request;
use \Bp\Calculator\Model as Calculator;

/**
 * module=bp-history
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class View extends \Gcms\View
{
    /**
     * @var object
     */
    private $category;
    /**
     * @var array
     */
    private $sys = [];
    /**
     * @var array
     */
    private $dia = [];
    /**
     * ตาราง Bp
     *
     * @param Request $request
     * @param object $profile
     *
     * @return string
     */
    public function render(Request $request, $profile)
    {
        $params = array(
            'family_id' => $profile->id,
            'member_id' => $profile->member_id,
            'from' => $request->request('from', date('Y-m-d', strtotime('-7 days')))->date(),
            'to' => $request->request('to', date('Y-m-d'))->date(),
            'tag' => $request->request('tag')->topic()
        );
        $this->category = \Bp\Category\Model::init($profile->member_id);
        // URL สำหรับส่งให้ตาราง
        $uri = $request->createUriWithGlobals(WEB_URL.'index.php');
        // ตาราง
        $table = new DataTable(array(
            /* Uri */
            'uri' => $uri,
            /* Model */
            'model' => \Bp\History\Model::toDataTable($params),
            /* รายการต่อหน้า */
            'perPage' => $request->cookie('bpHistoryperPage', 30)->toInt(),
            /* เรียงลำดับ */
            'sort' => $request->cookie('bpHistorysort', 'create_date desc')->toString(),
            /* ฟังก์ชั่นจัดรูปแบบการแสดงผลแถวของตาราง */
            'onRow' => array($this, 'onRow'),
            /* ฟังก์ชั่นแสดงผล Footer */
            'onCreateFooter' => array($this, 'onCreateFooter'),
            /* คอลัมน์ที่ไม่ต้องแสดงผล */
            'hideColumns' => array('id'),
            /* ตั้งค่าการกระทำของของตัวเลือกต่างๆ ด้านล่างตาราง ซึ่งจะใช้ร่วมกับการขีดถูกเลือกแถว */
            'action' => 'index.php/bp/model/history/action',
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
            /* ตัวเลือกด้านบนของตาราง ใช้จำกัดผลลัพท์การ query */
            'filters' => array(
                array(
                    'type' => 'date',
                    'name' => 'from',
                    'text' => '{LNG_from}',
                    'value' => $params['from']
                ),
                array(
                    'type' => 'date',
                    'name' => 'to',
                    'text' => '{LNG_to}',
                    'value' => $params['to']
                ),
                array(
                    'name' => 'tag',
                    'text' => '{LNG_Tag}',
                    'options' => array(0 => '{LNG_all items}') + $this->category->toSelect('tag'),
                    'value' => $params['tag']
                )
            ),
            /* ส่วนหัวของตาราง และการเรียงลำดับ (thead) */
            'headers' => array(
                'create_date' => array(
                    'text' => '{LNG_Date}',
                    'sort' => 'create_date'
                ),
                'sys1' => array(
                    'text' => 'SYS 1',
                    'class' => 'center'
                ),
                'dia1' => array(
                    'text' => 'DIA 1',
                    'class' => 'center'
                ),
                'pulse1' => array(
                    'text' => '{LNG_Pulse} 1',
                    'class' => 'center'
                ),
                'sys2' => array(
                    'text' => 'SYS 2',
                    'class' => 'center'
                ),
                'dia2' => array(
                    'text' => 'DIA 2',
                    'class' => 'center'
                ),
                'pulse2' => array(
                    'text' => '{LNG_Pulse} 2',
                    'class' => 'center'
                ),
                'height' => array(
                    'text' => '{LNG_Height}',
                    'class' => 'center'
                ),
                'weight' => array(
                    'text' => '{LNG_Weight}',
                    'class' => 'center'
                ),
                'bmi' => array(
                    'text' => '{LNG_BMI}',
                    'class' => 'center'
                ),
                'waist' => array(
                    'text' => '{LNG_Waist size}',
                    'class' => 'center'
                ),
                'temperature' => array(
                    'text' => '{LNG_Temperature}',
                    'class' => 'center'
                ),
                'tag' => array(
                    'text' => '{LNG_Tag}',
                    'class' => 'center',
                    'sort' => 'tag'
                )
            ),
            /* รูปแบบการแสดงผลของคอลัมน์ (tbody) */
            'cols' => array(
                'create_date' => array(
                    'class' => 'nowrap'
                ),
                'sys1' => array(
                    'class' => 'center'
                ),
                'dia1' => array(
                    'class' => 'center'
                ),
                'pulse1' => array(
                    'class' => 'center'
                ),
                'sys2' => array(
                    'class' => 'center'
                ),
                'dia2' => array(
                    'class' => 'center'
                ),
                'pulse2' => array(
                    'class' => 'center'
                ),
                'height' => array(
                    'class' => 'center nowrap'
                ),
                'weight' => array(
                    'class' => 'center nowrap'
                ),
                'bmi' => array(
                    'class' => 'center nowrap'
                ),
                'waist' => array(
                    'class' => 'center nowrap'
                ),
                'temperature' => array(
                    'class' => 'center nowrap'
                ),
                'tag' => array(
                    'class' => 'center nowrap'
                )
            ),
            /* ปุ่มแสดงในแต่ละแถว */
            'buttons' => array(
                array(
                    'class' => 'icon-edit button green',
                    'href' => $uri->createBackUri(array('module' => 'bp-record', 'id' => ':id')),
                    'text' => '{LNG_Edit}'
                )
            )
        ));
        // save cookie
        setcookie('bpHistoryperPage', $table->perPage, time() + 2592000, '/', HOST, HTTPS, true);
        setcookie('bpHistorysort', $table->sort, time() + 2592000, '/', HOST, HTTPS, true);
        // คืนค่า HTML
        $content = $table->render();
        $content .= '<footer class=float_bottom_menu>';
        $content .= '<a class=bp-family title="{LNG_a family member}" href="'.WEB_URL.'index.php?module=bp-family"><span class=icon-users></span></a>';
        $content .= '<a class=bp-record title="{LNG_Record}" href="'.WEB_URL.'index.php?module=bp-record&amp;family_id='.$profile->id.'"><span class=icon-new></span></a>';
        $content .= '<a class=bp-report title="{LNG_Report}" href="'.WEB_URL.'index.php?module=bp-report&amp;id='.$profile->id.'"><span class=icon-stats></span></a>';
        $content .= '</footer>';
        return $content;
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
        if (!empty($item['sys1'])) {
            $this->sys[] = $item['sys1'];
        }
        if (!empty($item['sys2'])) {
            $this->sys[] = $item['sys2'];
        }
        if (!empty($item['dia1'])) {
            $this->dia[] = $item['dia1'];
        }
        if (!empty($item['dia2'])) {
            $this->dia[] = $item['dia2'];
        }
        $item['tag'] = $this->category->get('tag', $item['tag']);
        $item['create_date'] = Date::format($item['create_date']);
        if (empty($item['height']) || empty($item['weight'])) {
            $item['bmi'] = '-';
        } else {
            $height = $item['height'] / 100;
            $bmi = round($item['weight'] / ($height * $height), 2);
            $item['bmi'] = '<span class=color-'.\Bp\Calculator\Model::bmiColor($bmi).'>'.$bmi.'</span>';
        }
        $item['height'] = empty($item['height']) ? '-' : $item['height'].' {LNG_Cm.}';
        $item['weight'] = empty($item['weight']) ? '-' : $item['weight'].' {LNG_Kg.}';
        $item['waist'] = empty($item['waist']) ? '-' : $item['waist'].' {LNG_Cm.}';
        $item['temperature'] = empty($item['temperature']) ? '-' : $item['temperature'].' ℃';
        $item['sys1'] = empty($item['sys1']) ? '-' : '<span class=color-'.Calculator::bpColor($item['sys1'], 0).'>'.$item['sys1'].'</span>';
        $item['sys2'] = empty($item['sys2']) ? '-' : '<span class=color-'.Calculator::bpColor($item['sys2'], 0).'>'.$item['sys2'].'</span>';
        $item['dia1'] = empty($item['dia1']) ? '-' : '<span class=color-'.Calculator::bpColor(0, $item['dia1']).'>'.$item['dia1'].'</span>';
        $item['dia2'] = empty($item['dia2']) ? '-' : '<span class=color-'.Calculator::bpColor(0, $item['dia2']).'>'.$item['dia2'].'</span>';
        return $item;
    }

    /**
     * ฟังก์ชั่นสร้างแถวของ footer
     *
     * @return string
     */
    public function onCreateFooter()
    {
        $tr = '<tr>';
        $tr .= '<td class=right>{LNG_Average}</td>';
        $tr .= '<th class="check-column"><a class="checkall icon-uncheck" title="{LNG_Select all}"></a></th>';
        if (empty($this->sys) || empty($this->sys)) {
            $tr .= '<td colspan=4></td>';
        } else {
            $sys = floor(array_sum($this->sys) / count($this->sys));
            $dia = floor(array_sum($this->dia) / count($this->dia));
            $tr .= '<td class="center color-'.Calculator::bpColor($sys, 0).'" colspan=2>'.$sys.'</td>';
            $tr .= '<td class="center color-'.Calculator::bpColor(0, $dia).'" colspan=2>'.$dia.'</td>';
        }
        $tr .= '<td colspan=9></td></tr>';
        return $tr;
    }
}
