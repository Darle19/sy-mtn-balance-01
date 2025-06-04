<?php
require 'vendor/autoload.php';
use FBReport\ReportModule;

$config = require __DIR__ . '/config.php';
$module = new ReportModule($config);

/**
 * ??????? 5 ?????????? ??????????? ??? (??????? ?????)
 * ? ?????? ???????? upsertReport:
 *   – ??????? «????»   (active_trial / active_paid),
 *   – ????? «?????»   (??? ????????? ???????).
 */
for ($i = 1; $i <= 5; $i++) {
    $date = (new DateTime("-{$i} days"))->format('Y-m-d');

    // ????: ???????? trial / paid ?? ?????? ???
    $module->upsertReport([
        ':dt'            => "$date",
        ':morning_trial' => $module->querySingle('active_trial', $date),
        ':morning_paid'  => $module->querySingle('active_paid',  $date)
    ], true);

    // ?????: ??? ???????? ??????? ?? ????
    $module->upsertReport([
        ':dt'            => "$date",
        ':new_trial'     => $module->querySingle('new_trial',      $date),
        ':new_paid'      => $module->querySingle('new_paid',       $date),
        ':trial_to_paid' => $module->querySingle('trial_to_paid',  $date),
        ':active_trial'  => $module->querySingle('active_trial_last_day',$date),
        ':active_paid'   => $module->querySingle('active_paid_last_day', $date),
        ':bill_150'      => $module->querySingle('billing_success_150',  $date),
        ':bill_100'      => $module->querySingle('billing_success_100',  $date),
        ':bill_50'       => $module->querySingle('billing_success_50',   $date),
        ':bill_fail'     => $module->querySingle('billing_fail',         $date),
        ':unsub_trial'   => $module->querySingle('unsubscribe_trial',    $date),
        ':unsub_paid'    => $module->querySingle('unsubscribe_paid',     $date),
        ':total_fee'     => $module->querySingle('billing_success_sum',  $date)
    ], false);

    echo "? ???????? ???? $date\n";
}
