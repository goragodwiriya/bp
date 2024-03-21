<?php
/**
 * @filesource modules/bp/views/profile.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Bp\Profile;

use Gcms\Login;
use Kotchasan\Html;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * module=bp-profile
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class View extends \Gcms\View

{
    /**
     * ฟอร์มแก้ไขสมาชิก
     *
     * @param Request $request
     * @param object   $user
     * @param array   $login
     *
     * @return string
     */
    public function render(Request $request, $user, $login)
    {
        $form = Html::create('form', array(
            'id' => 'setup_frm',
            'class' => 'setup_frm',
            'autocomplete' => 'off',
            'action' => 'bp.php/bp/model/profile/submit',
            'onsubmit' => 'doFormSubmit',
            'ajax' => true,
            'token' => true
        ));
        $fieldset = $form->add('fieldset', array(
            'title' => '{LNG_Details of} {LNG_a family member}'
        ));
        $groups = $fieldset->add('groups');
        // name
        $groups->add('text', array(
            'id' => 'register_name',
            'labelClass' => 'g-input icon-customer',
            'itemClass' => 'width50',
            'label' => '{LNG_Name}',
            'maxlength' => 100,
            'value' => isset($user->name) ? $user->name : ''
        ));
        // sex
        $groups->add('select', array(
            'id' => 'register_sex',
            'labelClass' => 'g-input icon-sex',
            'itemClass' => 'width50',
            'label' => '{LNG_Sex}',
            'options' => Language::get('SEXES'),
            'value' => isset($user->sex) ? $user->sex : 'f'
        ));
        $groups = $fieldset->add('groups');
        // id_card
        $groups->add('number', array(
            'id' => 'register_id_card',
            'labelClass' => 'g-input icon-profile',
            'itemClass' => 'width50',
            'label' => '{LNG_Identification No.}',
            'maxlength' => 13,
            'value' => isset($user->id_card) ? $user->id_card : ''
        ));
        // birthday
        $groups->add('date', array(
            'id' => 'register_birthday',
            'labelClass' => 'g-input icon-calendar',
            'itemClass' => 'width50',
            'label' => '{LNG_Birthday}',
            'value' => isset($user->birthday) ? $user->birthday : null
        ));
        $groups = $fieldset->add('groups');
        // height
        $groups->add('text', array(
            'id' => 'register_height',
            'itemClass' => 'width50',
            'labelClass' => 'g-input icon-edit',
            'label' => '{LNG_Height}',
            'unit' => '{LNG_Cm.}',
            'data-keyboard' => '1234567890.',
            'value' => empty($user->height) ? '' : $user->height
        ));
        // phone
        $groups->add('text', array(
            'id' => 'register_phone',
            'labelClass' => 'g-input icon-phone',
            'itemClass' => 'width50',
            'label' => '{LNG_Phone}',
            'maxlength' => 32,
            'value' => isset($user->phone) ? $user->phone : ''
        ));
        // address
        $fieldset->add('text', array(
            'id' => 'register_address',
            'labelClass' => 'g-input icon-address',
            'itemClass' => 'item',
            'label' => '{LNG_Address}',
            'maxlength' => 150,
            'value' => isset($user->address) ? $user->address : ''
        ));
        $groups = $fieldset->add('groups');
        // country
        $groups->add('text', array(
            'id' => 'register_country',
            'labelClass' => 'g-input icon-world',
            'itemClass' => 'width33',
            'label' => '{LNG_Country}',
            'datalist' => \Kotchasan\Country::all(),
            'value' => isset($user->country) ? $user->country : 'TH'
        ));
        // provinceID
        $groups->add('text', array(
            'id' => 'register_province',
            'name' => 'register_provinceID',
            'labelClass' => 'g-input icon-location',
            'itemClass' => 'width33',
            'label' => '{LNG_Province}',
            'datalist' => [],
            'text' => isset($user->province) ? $user->province : '',
            'value' => isset($user->provinceID) ? $user->provinceID : ''
        ));
        // zipcode
        $groups->add('number', array(
            'id' => 'register_zipcode',
            'labelClass' => 'g-input icon-number',
            'itemClass' => 'width33',
            'label' => '{LNG_Zipcode}',
            'maxlength' => 10,
            'value' => isset($user->zipcode) ? $user->zipcode : ''
        ));
        $fieldset = $form->add('fieldset', array(
            'class' => 'submit'
        ));
        // submit
        $fieldset->add('submit', array(
            'class' => 'button save large icon-save',
            'value' => '{LNG_Save}'
        ));
        $fieldset->add('hidden', array(
            'id' => 'register_id',
            'value' => $user->id
        ));
        // Javascript
        $form->script('initEditProfile("register");');
        $form->script('birthdayChanged("register_birthday", "%s ({LNG_age} %y {LNG_year}, %m {LNG_month} %d {LNG_days})");');
        // คืนค่า HTML
        $content = $form->render();
        $content .= '<footer class=float_bottom_menu>';
        $content .= '<a class=bp-family title="{LNG_a family member}" href="'.WEB_URL.'index.php?module=bp-family"><span class=icon-users></span></a>';
        if ($user->id > 0) {
            $content .= '<a class=bp-record title="{LNG_Record}" href="'.WEB_URL.'index.php?module=bp-record&amp;family_id='.$user->id.'"><span class=icon-new></span></a>';
            $content .= '<a class=bp-history title="{LNG_History}" href="'.WEB_URL.'index.php?module=bp-history&amp;id='.$user->id.'"><span class=icon-heart></span></a>';
            $content .= '<a class=bp-report title="{LNG_Report}" href="'.WEB_URL.'index.php?module=bp-report&amp;id='.$user->id.'"><span class=icon-stats></span></a>';
        }
        $content .= '</footer>';
        return $content;
    }
}
