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

    private function getReportPaths(): array
    {
        $r = $this->config['report'];
        $date      = date($r['date_fmt']);
        $outputDir = rtrim($r['dir'], '/') . '/';
        $fileFmt   = $r['format'];
        $filename  = "report_{$date}.{$fileFmt}";

        return [
            'template' => __DIR__ . '/../template.xlsx',
            'output'   => __DIR__ . '/../' . $outputDir . $filename,
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

    private function sendEmail(string $file): void
    {
        $email = $this->config['email'];
        if (!filter_var($email['enabled'], FILTER_VALIDATE_BOOLEAN)) {
            return;
        }
    
        $mail = new PHPMailer(true);
    
        // переключаем режим на Sendmail
        $mail->isSendmail();
    
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
        $paths      = $this->getReportPaths();
        $this->ensureReportFile($paths['template'], $paths['output']);

        $spreadsheet = $this->loadSpreadsheet($paths['output']);
        $sheet       = $spreadsheet->getActiveSheet();
        $hour        = (int)(new \DateTime())->format('H');
        $date        = $paths['date'];
        $serviceKey  = $this->config['service_key'];

        if ($hour < 1) {
            // 00:30 – активные подписки
            $trialActive = $this->querySingle('active_trial', $date);
            $paidActive  = $this->querySingle('active_paid', $date);
            $sheet->setCellValue('A3', $date);
            $sheet->setCellValue('B3', $serviceKey);
            $sheet->setCellValue('C3', $trialActive);
            $sheet->setCellValue('D3', $paidActive);
            $sheet->setCellValue('E3', $trialActive + $paidActive);

        } elseif ($hour < 9) {
            // 08:00 – отправка отчёта
            $this->sendEmail($paths['output']);

        } elseif ($hour <= 23) {
            // 23:30 – итоговые метрики по ключам
            $keys = [
                'new_trial', 'new_paid', 'trial_to_paid',
                'active_trial_last_day', 'active_paid_last_day',
                'billing_success_150', 'billing_success_100', 'billing_success_50',
                'billing_fail', 'unsubscribe_trial', 'unsubscribe_paid',
                'billing_success_sum',
            ];
            $col = 'F';
            foreach ($keys as $key) {
                if ($col == 'H'){$col++;}
                $val = $this->querySingle($key, $date);
                $sheet->setCellValue("{$col}3", $val);
                $col++;
            }
            $this->sendEmail($paths['output']);
        }

        $this->saveSpreadsheet($spreadsheet, $paths['output']);
    }
}
