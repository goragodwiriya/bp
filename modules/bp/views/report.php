<?php
/**
 * @filesource modules/bp/views/report.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Bp\Report;

use Kotchasan\Date;
use Kotchasan\Html;
use Kotchasan\Http\Request;
use \Bp\Calculator\Model as Calculator;

/**
 * module=bp-trport
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class View extends \Gcms\View
{
    /**
     * Graphs
     *
     * @param Request $request
     * @param object  $profile
     *
     * @return string
     */
    public function render(Request $request, $profile)
    {
        // คำนวณอายุ
        $age = empty($profile->birthday) ? '' : Date::compare($profile->birthday, date('Y-m-d'))['year'];
        $params = array(
            'member_id' => $profile->member_id,
            'family_id' => $request->request('id')->toInt(),
            'from' => $request->request('from', date('Y-m-d', strtotime('-7 days')))->date(),
            'to' => $request->request('to', date('Y-m-d'))->date(),
            'tag' => $request->request('tag')->toInt()
        );
        // หมวดหมู่
        $category = \Bp\Category\Model::init($profile->member_id);
        // form
        $form = Html::create('form', array(
            'id' => 'bpreport',
            'class' => 'table_nav clear',
            'method' => 'get',
            'action' => 'index.php?module=bp-report',
            'token' => false,
            'ajax' => false
        ));
        $div = $form->add('div');
        $fieldset = $div->add('fieldset');
        // from
        $fieldset->add('date', array(
            'id' => 'from',
            'label' => '{LNG_from}',
            'value' => $params['from']
        ));
        $fieldset = $div->add('fieldset');
        // to
        $fieldset->add('date', array(
            'id' => 'to',
            'label' => '{LNG_to}',
            'value' => $params['to']
        ));
        $fieldset = $div->add('fieldset');
        // tag
        $fieldset->add('select', array(
            'id' => 'tag',
            'label' => '{LNG_Tag}',
            'options' => array(0 => '{LNG_all items}') + $category->toSelect('tag'),
            'value' => $params['tag']
        ));
        $fieldset = $div->add('fieldset');
        // submit
        $fieldset->add('submit', array(
            'class' => 'button go',
            'value' => 'GO'
        ));
        // id
        $fieldset->add('hidden', array(
            'id' => 'id',
            'value' => $profile->id
        ));
        // submit
        $fieldset->add('hidden', array(
            'id' => 'module',
            'value' => 'bp-report'
        ));
        // แสดงผล
        $content = '<section id=report class="setup_frm">';
        $datas = [];
        $sys = 0;
        $dia = 0;
        $bmi = 0;
        $height = $profile->height;
        // ค่าสูงสุดของกราฟแต่ละตัว
        $max = Calculator::$sys_max;
        $weight_max = 0;
        $n = 0;
        foreach (\Bp\Report\Model::get($params) as $item) {
            if ($item->sys > 0 && $item->dia > 0) {
                $n++;
                $sys += $item->sys;
                $dia += $item->dia;
            }
            if ($item->height > 0) {
                if ($item->weight > 0) {
                    $bmi = Calculator::bmi($item->height, $item->weight);
                }
                $height = $item->height;
            }
            $max = max($max, $item->sys, $item->dia);
            $weight_max = max($weight_max, $item->weight);
            if (!isset($datas[$item->id])) {
                $datas[$item->id] = array(
                    Date::format($item->create_date),
                    [],
                    [],
                    [],
                    Date::format($item->create_date, 'j'),
                    $category->get('tag', $item->tag),
                    $item->height,
                    $item->weight
                );
            }
            $datas[$item->id][1][] = $item->sys;
            $datas[$item->id][2][] = $item->dia;
            $datas[$item->id][3][] = $item->pulse;
        }
        $content .= '<article class="ggraphs clear">';
        $content .= '<header><h3>{LNG_Blood Pressure} '.$profile->name;
        $content .= empty($profile->birthday) || $profile->birthday == '0000-00-00' ? '' : ' {LNG_age} '.$age.' {LNG_year}';
        if ($n > 0) {
            $sys = floor($sys / $n);
            $dia = floor($dia / $n);
            if ($sys > 0 && $dia > 0) {
                $content .= ' ({LNG_Average} <span class=color-'.Calculator::bpColor($sys, $dia).'>'.$sys.'/'.$dia.'</span>)';
            }
        }
        $content .= '</h3></header>';
        $content .= $form->render();
        // แนวตั้งของกราฟ (ช่วงละ)
        $block_height = 10;
        $level = ceil($max / $block_height);
        $max = $level * $block_height;
        // graph
        $content .= '<div class="bp_graph">';
        $content .= '<div class="label">';
        for ($i = $max; $i > 0; $i -= $block_height) {
            $content .= '<span style="bottom:'.((100 * $i) / $max).'%">'.$i.'</span>';
        }
        $content .= '</div>';
        $content .= '<div class="graph">';
        for ($i = $max; $i > 0; $i -= $block_height) {
            $content .= '<div class="line" style="height:'.((100 * $i) / $max).'%"></div>';
        }
        $content .= '<div class=sys_safe style="bottom:'.((100 * Calculator::$sys_min) / $max).'%;height:'.((100 * (Calculator::$sys_max - Calculator::$sys_min)) / $max).'%"></div>';
        $content .= '<div class=dia_safe style="bottom:'.((100 * Calculator::$dia_min) / $max).'%;height:'.((100 * (Calculator::$dia_max - Calculator::$dia_min)) / $max).'%"></div>';
        $label = '';
        if ($n > 0) {
            $half = floor(count($datas) / 2);
            $w = (100 / count($datas));
            $i = 0;
            foreach ($datas as $item) {
                $sys = floor(array_sum($item[1]) / count($item[1]));
                $dia = floor(array_sum($item[2]) / count($item[2]));
                $pulse = floor(array_sum($item[3]) / count($item[3]));
                // tooltip
                $tooltip = '<b>'.$item[0].'</b>';
                $tooltip .= '<br>{LNG_Tag} : '.$item[5];
                $tooltip .= '<br>{LNG_Systolic} '.$sys.' mmHg';
                $tooltip .= '<br>{LNG_Diastolic} '.$dia.' mmHg';
                $tooltip .= '<br>{LNG_Pulse} '.$pulse.' BPM';
                // sys, dia
                $content .= '<div class=item style="width:'.$w.'%">';
                $content .= '<div class=sys style="height:'.((100 * $sys) / $max).'%"></div>';
                $content .= '<div class=dia style="height:'.((100 * $dia) / $max).'%"></div>';
                $content .= '<div class=pulse style="height:'.((100 * $pulse) / $max).'%"></div>';
                $content .= '<span class="'.($i < $half ? 'tooltip-left' : 'tooltip-right').'" style="top:'.(100 - ((100 * $sys) / $max)).'%">'.$tooltip.'</span>';
                $content .= '</div>';
                // label
                $label .= '<span style="left:'.($i * $w).'%;width:'.$w.'%">'.$item[4].'</span>';
                $i++;
            }
            $content .= '<div class="label_x clear">'.$label.'</div>';
        }
        $content .= '</div>';
        $content .= '</div>';
        $content .= '<footer class=clear>';
        $content .= '<span class=sys>{LNG_Systolic} {LNG_in between} '.Calculator::$sys_min.' - '.Calculator::$sys_max.' mmHg</span>';
        $content .= '<span class=dia>{LNG_Diastolic} {LNG_in between} '.Calculator::$dia_min.' - '.Calculator::$dia_max.' mmHg</span>';
        $content .= '<span class=pulse>{LNG_Pulse}</span>';
        $content .= '</footer>';
        // weight
        $content .= '<article class="ggraphs clear">';
        $content .= '<header><h3>{LNG_Weight}';
        if ($height > 0) {
            $content .= ' ({LNG_Height} '.$height.' {LNG_Cm.}';
            if ($bmi > 0) {
                $content .= ' {LNG_BMI} <span class=color-'.Calculator::bmiColor($bmi).'>'.round($bmi, 2).'</span>';
            }
            $content .= ')';
        }
        $content .= '</h3></header>';
        $level = ceil($weight_max / $block_height);
        $weight_max = max(50, $level * $block_height);
        $content .= '<div class="bp_graph">';
        $content .= '<div class="label">';
        for ($i = $weight_max; $i > 0; $i -= $block_height) {
            $content .= '<span style="bottom:'.((100 * $i) / $weight_max).'%">'.$i.'</span>';
        }
        $content .= '</div>';
        $content .= '<div class="graph">';
        for ($i = $weight_max; $i > 0; $i -= $block_height) {
            $content .= '<div class="line" style="height:'.((100 * $i) / $weight_max).'%"></div>';
        }
        $content .= '<div class=bmi_safe style="bottom:'.((100 * 18.5) / $weight_max).'%;height:'.((100 * (22.9 - 18.5)) / $weight_max).'%"></div>';
        $label = '';
        if (count($datas) > 0) {
            $half = floor(count($datas) / 2);
            $w = (100 / count($datas));
            $i = 0;
            foreach ($datas as $item) {
                $content .= '<div class=item style="width:'.$w.'%">';
                if ($item[6] > 0 && $item[7] > 0) {
                    $content .= '<div class=weight style="height:'.((100 * $item[7]) / $weight_max).'%"></div>';
                    $bmi = Calculator::bmi($item[6], $item[7]);
                    $content .= '<div class=bmi style="height:'.((100 * $bmi) / $weight_max).'%"></div>';
                    // tooltip
                    $tooltip = '<b>'.$item[0].'</b>';
                    $tooltip .= '<br>{LNG_Height} '.$item[6].' {LNG_Cm.}';
                    $tooltip .= '<br>{LNG_Weight} '.$item[7].' {LNG_Kg.}';
                    $tooltip .= '<br>{LNG_BMI} '.round($bmi, 2);
                    $content .= '<span class="'.($i < $half ? 'tooltip-left' : 'tooltip-right').'" style="top:'.(100 - ((100 * $item[7]) / $weight_max)).'%">'.$tooltip.'</span>';
                    $day = $item[4];
                } else {
                    $day = '';
                }
                $content .= '</div>';
                // label
                $label .= '<span style="left:'.($i * $w).'%;width:'.$w.'%">'.$day.'</span>';
                $i++;
            }
            $content .= '<div class="label_x clear">'.$label.'</div>';
        }
        $content .= '</div>';
        $content .= '</div>';
        $content .= '<footer class=clear>';
        $content .= '<span class=weight>{LNG_Weight}</span>';
        $content .= '<span class=bmi>{LNG_BMI} {LNG_in between} 18.5 - 22.9</span>';
        $content .= '</footer>';
        $content .= '</article>';
        $content .= '</section>';
        $content .= '<footer class=float_bottom_menu>';
        $content .= '<a class=bp-family title="{LNG_a family member}" href="'.WEB_URL.'index.php?module=bp-family"><span class=icon-users></span></a>';
        $content .= '<a class=bp-record title="{LNG_Record}" href="'.WEB_URL.'index.php?module=bp-record&amp;family_id='.$profile->id.'"><span class=icon-new></span></a>';
        $content .= '<a class=bp-history title="{LNG_History}" href="'.WEB_URL.'index.php?module=bp-history&amp;id='.$profile->id.'"><span class=icon-heart></span></a>';
        $content .= '</footer>';
        // คืนค่า HTML
        return $content;
    }

}
