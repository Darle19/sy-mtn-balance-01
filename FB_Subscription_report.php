<?php
namespace FBReport;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PDO;

// Модуль для генерации и рассылки отчёта по подпискам
class ReportModule
{
    // Конфиг из config.php
    private array $config;
    // Подключение к БД через PDO
    private PDO $pdo;

    // Конструктор принимает конфиг и инициализирует БД
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->initDatabase();
    }

    // Настраиваем соединение с бд
    private function initDatabase(): void
    {
        $db = $this->config['database'];
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $db['host'], $db['port'], $db['name']
        );
        $this->pdo = new PDO(
            $dsn,
            $db['user'],
            $db['pass'],
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
    }

    // Возвращаем пути к шаблону, выходному файлу и дату отчёта
    private function getReportPaths(): array
    {
        $r = $this->config['report'];
        $date      = date($r['date_fmt']);               // форматируем дату
        $outputDir = rtrim($r['dir'], '/') . '/';        // директория для отчётов
        $fileFmt   = $r['format'];                       // формат файла (xlsx)
        $filename  = "report_{$date}.{$fileFmt}";      // имя файла

        return [
            'template' => __DIR__ . '/../template.xlsx',  // путь к шаблону
            'output'   => __DIR__ . '/../' . $outputDir . $filename,
            'date'     => $date,
        ];
    }

    // Если файл отчёта ещё не создан — копируем шаблон
    private function ensureReportFile(string $template, string $output): void
    {
        if (!file_exists($output)) {
            if (!copy($template, $output)) {
                throw new \RuntimeException('Не удалось скопировать шаблон в ' . $output);
            }
        }
    }

    // Загружаем Excel-файл через PhpSpreadsheet
    private function loadSpreadsheet(string $file)
    {
        $reader = IOFactory::createReader('Xlsx');
        return $reader->load($file);
    }

    // Сохраняем изменения в файл
    private function saveSpreadsheet($spreadsheet, string $file): void
    {
        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save($file);
    }

    // Выполняем комбинированный SQL-запрос и возвращаем строку с метриками
    private function fetchMetrics(string $unused, string $sqlKey): array
    {
        $queries = require __DIR__ . '/../sql_queries.php';
        $sql     = $queries[$sqlKey];
        $stmt    = $this->pdo->prepare($sql);
        $stmt->execute([':date' => $this->getReportPaths()['date']]);
        return $stmt->fetch();
    }

    // Отправляем письма через PHPMailer
    private function sendEmail(string $file): void
    {
        $email = $this->config['email'];
        // Если в конфиге выключено — выходим
        if (!filter_var($email['enabled'], FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host    = $email['smtp_host'];
        $mail->Port    = $email['smtp_port'];
        if ($email['use_ssl']) {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // включаем SSL
        }
        $mail->setFrom($email['from']);
        foreach ($email['to'] as $rcpt) {
            $mail->addAddress($rcpt); // каждому получателю ставим адрес
        }
        $date    = $this->getReportPaths()['date'];
        $mail->Subject = str_replace('{date}', $date, $email['subject_tpl']);
        $mail->Body    = str_replace('{date}', $date, $email['body_tpl']);
        // Прикрепляем файл, если нужно и он есть
        if ($email['attachment'] && file_exists($file)) {
            $mail->addAttachment($file);
        }
        $mail->send();
    }

    // Основной метод запуска модуля
    public function run(): void
    {
        $paths = $this->getReportPaths();
        $this->ensureReportFile($paths['template'], $paths['output']);

        $spreadsheet = $this->loadSpreadsheet($paths['output']);
        $sheet       = $spreadsheet->getActiveSheet();
        $hour        = (int) (new \DateTime())->format('H');  // текущий час
        $date        = $paths['date'];
        $serviceKey  = $this->config['service_key'];

        // Если время 00:30 — собираем начальные метрики
        if ($hour < 1) {
            $metrics = $this->fetchMetrics('', 'start_of_day');
            // Вписываем дату и service_key
            $sheet->setCellValue('A3', $date);
            $sheet->setCellValue('B3', $serviceKey);
            // Заполняем trial и paid
            $sheet->setCellValue('C3', $metrics['new_trial']);
            $sheet->setCellValue('D3', $metrics['new_paid']);
            // Сумма новых подписок
            $sheet->setCellValue('E3', $metrics['new_trial'] + $metrics['new_paid']);

        // Утром отправляем готовый файл по почте
        } elseif ($hour < 9) {
            $this->sendEmail($paths['output']);

        // Вечером считаем итоговые метрики
        } elseif ($hour >= 22) {
            $metrics = $this->fetchMetrics('', 'end_of_day');
            $col     = 'F';
            foreach ($metrics as $val) {
                if ($col === 'F') {
                    $sheet->setCellValue("{$col}3", "=F3+G3"); // Сумма новых подписок
                } else {
                    $sheet->setCellValue("{$col}3", (int)$val); // Остальные метрики
                }
                $col++;
            }
        }

        // Сохраняем изменения в файл
        $this->saveSpreadsheet($spreadsheet, $paths['output']);
    }
}
