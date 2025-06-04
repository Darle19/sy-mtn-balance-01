<?php
// config.php
return [
  'database' => [
    'host'     => '127.0.0.1',
    'port'     => 3306,
    'name'     => 'FBII',
    'user'     => 'ivr_user_db',
    'pass'     => '8S339ns0GbCz8o8X',
  ],
  'report'   => [
    'dir'      => 'reports/',
    'format'   => 'xlsx',
    'date_fmt' => 'Y-m-d',
  ],
  'email'    => [
    'enabled'    => true,
    'use_ssl'    => true,
    'host'       => '10.11.200.212',
    'port'       => 25,
    'from'       => 'sy-mtn-balance@mtn.com',
    'to'         => ['digitaloperations@mtn.com.sy'],
    'cc'         => ['ivr@unifun.com','f.saydaliev@unifun.com'],
    'subject_tpl'=> 'FB Subscription Report - {date}',
    'body_tpl'   => 'Please find the attached FB Subscription Report for {date}.',
  ],
  'service_key' => 107,
];
