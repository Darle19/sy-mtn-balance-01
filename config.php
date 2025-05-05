<?php
// config.php
return [
  'database' => [
    'host'     => 'localhost',
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
    'smtp_host'  => 'smtp.example.com',
    'smtp_port'  => 587,
    'use_ssl'    => true,
    'from'       => 'reports@example.com',
    'to'         => ['ivr@unifun.com','f.saydaliev@unifun.com'],
    'subject_tpl'=> 'TEST!!! FB Subscription Report - {date}',
    'body_tpl'   => 'Please find the attached FB Subscription Report for {date}.',
  ],
  'service_key' => 107,
];
