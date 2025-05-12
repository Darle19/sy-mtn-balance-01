<?php
namespace FBReport;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PDO;

// Модуль генерации и рассылки отчёта, работаем с sql_queries.php
class ReportModule
{
    private array $config;
    private PDO $pdo;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->initDatabase();
    }

    private function initDatabase(): void
    {
        $db = $this->config['database'];
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $db['host'], $db['port'], $db['name']
        );
        $this->pdo = new PDO($dsn, $db['user'], $db['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    private function getReportPaths(bool $forYesterday = false): array
    {
        $r      = $this->config['report'];
        $date   = $forYesterday
            ? (new \DateTime('yesterday'))->format($r['date_fmt'])
            : date($r['date_fmt']);

        $file   = "report_{$date}.{$r['format']}";
        return [    
            'template' => __DIR__ . '/../template.xlsx',
            'output'   => __DIR__ . '/../' . rtrim($r['dir'], '/') . '/' . $file,
            'date'     => $date,
        ];
    }

    private function ensureReportFile(string $template, string $output): void
    {
        if (!file_exists($output)) {
            if (!copy($template, $output)) {
                throw new \RuntimeException('Не удалось скопировать шаблон в ' . $output);
            }
        }
    }

    private function loadSpreadsheet(string $file)
    {
        $reader = IOFactory::createReader('Xlsx');
        return $reader->load($file);
    }

    private function saveSpreadsheet($spreadsheet, string $file): void
    {
        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save($file);
    }
    private function upsertReport(array $data, bool $isMorning): void
    {
        $fieldsMorning = ['morning_trial','morning_paid'];
        $fieldsEvening = [
            'new_trial','new_paid','trial_to_paid','active_trial','active_paid',
            'bill_150','bill_100','bill_50','bill_fail','unsub_trial','unsub_paid','total_fee'
        ];
        $cols = $isMorning ? $fieldsMorning : $fieldsEvening;
    
        // формируем список colon-placeholders (:morning_trial и т.д.)
        $set = [];
        foreach ($cols as $c) { $set[] = "$c = :$c"; }
    
        $sql = "
          INSERT INTO FB_subscriptions_report_new (report_dt, service_key, ".implode(',', $cols).")
          VALUES (:dt, :sk, ".implode(',', array_map(fn($c)=>":$c", $cols)).")
          ON DUPLICATE KEY UPDATE ".implode(',', $set);
    
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($data + [
            ':dt' => date('Y-m-d'),
            ':sk' => $this->config['service_key'],
        ]);
    }
    private function sendLast30(): void
    {
        $rows = $this->pdo->query("
            SELECT * FROM FB_subscriptions_report_new
            ORDER BY report_dt DESC
            LIMIT 30
        ")->fetchAll();

        // загружаем шаблон
        $paths = $this->getReportPaths(true);              // вчерашнее имя файла
        $this->ensureReportFile($paths['template'], $paths['output']);
        $sheet = $this->loadSpreadsheet($paths['output'])->getActiveSheet();

        $start = 3;                                        // с 3-ей строчки
        foreach ($rows as $i => $r) {
            $row = $start + $i;
            $sheet->setCellValue("A$row", $r['report_dt']);
            $sheet->setCellValue("B$row", $r['service_key']);
            $sheet->setCellValue("C$row", $r['morning_trial']);
            $sheet->setCellValue("D$row", $r['morning_paid']);
            $sheet->setCellValue("E$row", $r['morning_trial'] + $r['morning_paid']);
            $sheet->setCellValue("F$row", $r['new_trial']);
            $sheet->setCellValue("G$row", $r['new_paid']);
            $sheet->setCellValue("H$row", $r['new_trial'] + $r['new_paid']);
            $sheet->setCellValue("I$row", $r['trial_to_paid']);
            $sheet->setCellValue("J$row", $r['active_trial']);
            $sheet->setCellValue("K$row", $r['active_paid']);
            $sheet->setCellValue("L$row",$r['bill_150']);
            $sheet->setCellValue("M$row",$r['bill_100']);
            $sheet->setCellValue("N$row",$r['bill_50']);
            $sheet->setCellValue("O$row",$r['bill_fail']);
            $sheet->setCellValue("P$row",$r['unsub_trial']);
            $sheet->setCellValue("Q$row",$r['unsub_paid']);
            $sheet->setCellValue("R$row",$r['total_fee']);
        }
        $this->saveSpreadsheet($sheet->getParent(), $paths['output']);
        $this->sendEmail($paths['output']);
    }
    private function querySingle(string $key, string $date): int
    {
        $queries = require __DIR__ . '/../sql_queries.php';
        $sql     = $queries[$key];
        $stmt    = $this->pdo->prepare($sql);
        $params  = strpos($sql, ':date') !== false ? [':date' => $date] : [];
        $stmt->execute($params);
        $val = $stmt->fetchColumn();
        return $val === null ? 0 : (int)$val;
    }
    protected function currentHour(): int
    {
        // по умолчанию – реальное время сервера
        return (int) (new \DateTime())->format('H');
    }
    private function sendEmail(string $file): void
    {
        
        $email = $this->config['email'];
        if (!filter_var($email['enabled'], FILTER_VALIDATE_BOOLEAN)) {
            return;
        }
    
        $mail = new PHPMailer(true);
    
        // переключаем режим на Sendmail
        $mail->isSMTP();
        $mail->Host       = $email['host'];
        $mail->Port       = $email['port'];
        $mail->SMTPAuth   = false; // без логина/пароляф
        // от кого
        $mail->setFrom($email['from']);
    
        // кому
        foreach ($email['to'] as $rcpt) {
            $mail->addAddress($rcpt);
        }
    
        // тема и тело
        $date = (new \DateTime('yesterday'))->format($this->config['report']['date_fmt']);
        $mail->Subject  = str_replace('{date}', $date, $email['subject_tpl']);
        $mail->Body     = str_replace('{date}', $date, $email['body_tpl']);
        $mail->addAttachment($file);
      
        // отправляем
        try {
            $mail->send();
        } catch (Exception $e) {
            error_log('Mail error (sendmail): ' . $e->getMessage());
        }
    }
    

    public function run(): void
{
    $hour = $this->currentHour();

    if ($hour < 1) {                 // 00:30  вставляем утренние
        $data = [
            ':morning_trial' => $this->querySingle('active_trial', date('Y-m-d')),
            ':morning_paid'  => $this->querySingle('active_paid' , date('Y-m-d')),
        ];
        $this->upsertReport($data, true);

    } elseif ($hour == 8) {
        $this->sendLast30();

    } elseif ($hour >= 23) {         // 23:30   вечерняя выборка
        $d = date('Y-m-d');
        $data = [
            ':new_trial'      => $this->querySingle('new_trial', $d),
            ':new_paid'       => $this->querySingle('new_paid', $d),
            ':trial_to_paid'  => $this->querySingle('trial_to_paid',$d),
            ':active_trial'   => $this->querySingle('active_trial_last_day',$d),
            ':active_paid'    => $this->querySingle('active_paid_last_day',$d),
            ':bill_150'       => $this->querySingle('billing_success_150',$d),
            ':bill_100'       => $this->querySingle('billing_success_100',$d),
            ':bill_50'        => $this->querySingle('billing_success_50',$d),
            ':bill_fail'      => $this->querySingle('billing_fail',$d),
            ':unsub_trial'    => $this->querySingle('unsubscribe_trial',$d),
            ':unsub_paid'     => $this->querySingle('unsubscribe_paid',$d),
            ':total_fee'      => $this->querySingle('billing_success_sum',$d),
        ];
        $this->upsertReport($data,false);
    }
}

}
