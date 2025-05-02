<?php
// config.php
return [
  'database' => [
    'host'     => 'localhost',
    'port'     => 3306,
    'name'     => 'subscription_db',
    'user'     => 'dbuser',
    'pass'     => '',
  ],
  'report'   => [
    'dir'      => 'reports/',
    'format'   => 'xlsx',
    'date_fmt' => 'Y-m-d',
  ],
  'email'    => [
    'enabled'    => false,
    'smtp_host'  => 'smtp.example.com',
    'smtp_port'  => 587,
    'use_ssl'    => true,
    'from'       => 'reports@example.com',
    'to'         => ['recipient1@example.com','recipient2@example.com'],
    'subject_tpl'=> 'FB Subscription Report - {date}',
    'body_tpl'   => 'Please find the attached FB Subscription Report for {date}.',
  ],
  'service_key' => 107,
];
