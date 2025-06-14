<?php
// test_cycle.php
require __DIR__ . '/vendor/autoload.php';

use FBReport\ReportModule;

/**
 * ???????? ????????? ??????: 0 / 8 / 23
 *   0  ? ????????? 00:30-?????
 *   8  ? ????????? 08:00 ????????
 *   23 ? ????????? 23:30 ???????? ???????
 */
$fakeHour = isset($argv[1]) ? (int)$argv[1] : null;
$fakeDate = $argv[2] ?? date('Y-m-d');
if (!in_array($fakeHour, [0, 8, 23], true)) {
    exit("Usage: php test.php [0|8|23]\n");
}

// ????????? ??????
$config = require __DIR__ . '/config.php';

/**
 * ????????? ????: ????????? ??????
 * ? ?????????????? ?????, ??? ??????? ??????? ?????.
 */
class ReportModuleTestable extends ReportModule
{
    private int    $forcedHour;
    private string $forcedDate;
    public function setFake(int $h, string $d): void
    {
        $this->forcedHour = $h;
        $this->forcedDate = $d;
    }
    protected function currentHour(): int   { return $this->forcedHour; }
    public    function today(): string      { return $this->forcedDate; }  // новый геттер
}

// ???????, ????????? ??? ? ?????????
$module = new ReportModuleTestable($config);
$module->setFake($fakeHour, $fakeDate);
$module->run();
echo "? Finished branch for fake hour {$fakeHour} and {$fakeDate}\n ";
