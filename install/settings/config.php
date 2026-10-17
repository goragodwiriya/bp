<?php
/* config.php */
return [
    'version' => '7.0.4',
    'web_title' => 'Now.js',
    'web_description' => 'Admin Framework by Now.js',
    'timezone' => 'Asia/Bangkok',
    // โมดูล bp — เกณฑ์ความดันโลหิต (ค่าเริ่มต้นเดียวกับระบบเดิม)
    'bp_sys_hight' => 140,
    'bp_dia_hight' => 90,
    'bp_sys_max' => 120,
    'bp_dia_max' => 80,
    'bp_sys_min' => 90,
    'bp_dia_min' => 60,
    // โมดูล bp — เกณฑ์ธงแดงที่ควรส่งต่อทันที และหน้าต่างค่าเฉลี่ย
    'bp_referral_sys' => 180,
    'bp_referral_dia' => 110,
    'bp_avg_days' => 7,
    // โมดูล bp — บันทึกขณะออฟไลน์แล้ว sync กลับเมื่อออนไลน์
    'bp_sync_enabled' => true
];
