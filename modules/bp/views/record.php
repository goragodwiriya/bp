<?php
/**
 * @filesource modules/bp/views/record.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Bp\Record;

use Kotchasan\Html;
use Kotchasan\Http\Request;

/**
 * module=bp-record
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class View extends \Gcms\View
{
    /**
     * ฟอร์มบันทึก
     *
     * @param Request $request
     * @param object  $index
     *
     * @return string
     */
    public function render(Request $request, $index)
    {
        $form = Html::create('form', array(
            'id' => 'product',
            'class' => 'setup_frm',
            'autocomplete' => 'off',
            'action' => 'index.php/bp/model/record/submit',
            'onsubmit' => 'doFormSubmit',
            'token' => true,
            'ajax' => true
        ));
        $fieldset = $form->add('fieldset', array(
            'title' => '{LNG_Record} {LNG_Blood Pressure} '.$index->name
        ));
        $groups = $fieldset->add('groups', array(
            'comment' => '{LNG_Measure 2 times, 1-2 minutes apart}'
        ));
        // sys1
        $groups->add('number', array(
            'id' => 'write_sys1',
            'itemClass' => 'width50',
            'labelClass' => 'g-input icon-edit',
            'label' => '{LNG_1st SYS}',
            'value' => isset($index->sys1) ? $index->sys1 : ''
        ));
        // sys2
        $groups->add('number', array(
            'id' => 'write_sys2',
            'itemClass' => 'width50',
            'labelClass' => 'g-input icon-edit',
            'label' => '{LNG_2nd}',
            'value' => isset($index->sys2) ? $index->sys2 : ''
        ));
        $groups = $fieldset->add('groups', array(
            'comment' => '{LNG_Measure 2 times, 1-2 minutes apart}'
        ));
        // dia1
        $groups->add('number', array(
            'id' => 'write_dia1',
            'itemClass' => 'width50',
            'labelClass' => 'g-input icon-edit',
            'label' => '{LNG_1st DIA}',
            'value' => isset($index->dia1) ? $index->dia1 : ''
        ));
        // dia2
        $groups->add('number', array(
            'id' => 'write_dia2',
            'itemClass' => 'width50',
            'labelClass' => 'g-input icon-edit',
            'label' => '{LNG_2nd}',
            'value' => isset($index->dia2) ? $index->dia2 : ''
        ));
        $groups = $fieldset->add('groups', array(
            'comment' => '{LNG_Measure 2 times, 1-2 minutes apart}'
        ));
        // pulse1
        $groups->add('number', array(
            'id' => 'write_pulse1',
            'itemClass' => 'width50',
            'labelClass' => 'g-input icon-edit',
            'label' => '{LNG_1st Pulse}',
            'value' => isset($index->pulse1) ? $index->pulse1 : ''
        ));
        // pulse2
        $groups->add('number', array(
            'id' => 'write_pulse2',
            'itemClass' => 'width50',
            'labelClass' => 'g-input icon-edit',
            'label' => '{LNG_2nd}',
            'value' => isset($index->pulse2) ? $index->pulse2 : ''
        ));
        // height
        $fieldset->add('text', array(
            'id' => 'write_height',
            'itemClass' => 'item',
            'labelClass' => 'g-input icon-edit',
            'label' => '{LNG_Height}',
            'unit' => '{LNG_Cm.}',
            'data-keyboard' => '1234567890.',
            'value' => empty($index->height) ? '' : $index->height
        ));
        // weight
        $fieldset->add('text', array(
            'id' => 'write_weight',
            'itemClass' => 'item',
            'labelClass' => 'g-input icon-edit',
            'label' => '{LNG_Weight}',
            'unit' => '{LNG_Kg.}',
            'data-keyboard' => '1234567890.',
            'value' => empty($index->weight) ? '' : $index->weight
        ));
        // waist
        $fieldset->add('text', array(
            'id' => 'write_waist',
            'itemClass' => 'item',
            'labelClass' => 'g-input icon-edit',
            'label' => '{LNG_Waist size}',
            'unit' => '{LNG_Cm.}',
            'data-keyboard' => '1234567890.',
            'value' => empty($index->waist) ? '' : $index->waist
        ));
        // temperature
        $fieldset->add('text', array(
            'id' => 'write_temperature',
            'itemClass' => 'item',
            'labelClass' => 'g-input icon-edit',
            'label' => '{LNG_Temperature}',
            'unit' => '℃',
            'data-keyboard' => '1234567890.',
            'value' => empty($index->temperature) ? '' : $index->temperature
        ));
        // create_date
        $fieldset->add('datetime', array(
            'id' => 'write_create_date',
            'itemClass' => 'item',
            'labelClass' => 'g-input icon-calendar',
            'label' => '{LNG_Date}',
            'value' => isset($index->create_date) ? $index->create_date : date('Y-m-d H:i')
        ));
        $groups = $fieldset->add('groups');
        // tag
        $groups->add('select', array(
            'id' => 'write_tag',
            'itemClass' => 'width90',
            'labelClass' => 'g-input icon-tags',
            'label' => '{LNG_Tag}',
            'options' => array(0 => '{LNG_Please select}')+\Bp\Category\Model::init($index->member_id)->toSelect('tag'),
            'value' => isset($index->tag) ? $index->tag : 0
        ));
        // add_tag
        $groups->add('button', array(
            'id' => 'add_tag',
            'itemClass' => 'width10',
            'labelClass' => 'g-input',
            'class' => 'magenta button wide center icon-new',
            'label' => '&nbsp;',
            'value' => '{LNG_Add}'
        ));
        $fieldset = $form->add('fieldset', array(
            'class' => 'submit'
        ));
        // submit
        $fieldset->add('submit', array(
            'class' => 'button save large',
            'value' => '{LNG_Save}'
        ));
        // id
        $fieldset->add('hidden', array(
            'id' => 'write_id',
            'value' => $index->id
        ));
        // family_id
        $fieldset->add('hidden', array(
            'id' => 'write_family_id',
            'value' => $index->family_id
        ));
        // Javascript
        $form->script('initBpRecord();');
        // คืนค่าฟอร์ม
        $content = $form->render();
        $content .= '<footer class=float_bottom_menu>';
        $content .= '<a class=bp-family title="{LNG_a family member}" href="'.WEB_URL.'index.php?module=bp-family"><span class=icon-users></span></a>';
        $content .= '<a class=bp-history title="{LNG_History}" href="'.WEB_URL.'index.php?module=bp-history&amp;id='.$index->family_id.'"><span class=icon-heart></span></a>';
        $content .= '<a class=bp-report title="{LNG_Report}" href="'.WEB_URL.'index.php?module=bp-report&amp;id='.$index->family_id.'"><span class=icon-stats></span></a>';
        $content .= '</footer>';
        return $content;
    }
}
