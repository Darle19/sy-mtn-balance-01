<?php
return [
    // SQL-запросы для получения данных о подписках
    //Сбор информации в 00:30. Подсчет кол-ва подписок в триале из таблицы FB_subscriptions 
    'active_trial' => "
    SELECT COUNT(*) 
    FROM FB_subscriptions
    WHERE `status` IN (0, 8)
    AND DATE(start_date) < :date;
  ",
    //Сбор информации в 00:30. Подсчет кол-ва платный подписок из таблицы FB_subscriptions 
    'active_paid' => "
        SELECT COUNT(*)
        FROM FB_subscriptions
        WHERE `status` IN (1, 3, 5, 6)
        AND DATE(start_date) < :date;
    ",
    // Сбор информации в 23:30. Подсчет кол-ва Новых подписок, что подписались на триал в рамках текущего дня. Данные собираем из таблицы FB_subscriptions и FB_subscriptions_archive
    'new_trial' => "
    SELECT
    (SELECT COUNT(*) FROM FB_subscriptions
        WHERE status IN (0,8)
        AND DATE(start_date) = :date
    )
    +
    (SELECT COUNT(*) FROM FB_subscriptions_archive
        WHERE status IN (0,8)
        AND DATE(start_date) = :date
    );
  ",
    // Сбор информации в 23:30. Подсчет кол-ва Новых подписок, что подписались на платную подписку в рамках текущего дня. Данные собираем из таблицы FB_subscriptions и FB_subscriptions_archive
    'new_paid' => "
    SELECT
        (SELECT COUNT(*) FROM FB_subscriptions
        WHERE status IN (1,3,5,6)
        AND DATE(start_date) = :date
    )
    +
        (SELECT COUNT(*) FROM FB_subscriptions_archive
        WHERE status IN (1,3,5,6)
        AND DATE(start_date) = :date);
  ",
    //Подсчёт кол-ва переведенных с триала в платную • current status (в FB_subscriptions) status IN (1,3,5,6) но в начале дня status был IN (0,8)
    'trial_to_paid' => "
    SELECT COUNT(*) AS cnt
    FROM FB_subscriptions
    WHERE `status` IN (1, 3, 5, 6)
    AND DATE(trial_stop_date) = :date;
    ",
    //Сбор информации в 23:30. Подсчет кол-ва подписок в триале из таблицы FB_subscriptions на конец дня
    'active_trial_last_day' => "
      SELECT COUNT(*)
      FROM FB_subscriptions
      WHERE status IN (0, 8)
    ",
    //Сбор информации в 23:30. Подсчет кол-ва платный подписок из таблицы FB_subscriptions на конец дня
    'active_paid_last_day' => "
    SELECT COUNT(*)
    FROM FB_subscriptions
    WHERE status IN (1, 3, 5, 6);
    ",
    //Кол-во записей в billing_success_log по успешным списаниям за текущий день. сумма спиcания 150 SYP
    'billing_success_150' => "
    SELECT COUNT(*)
    FROM billing_success_log
    WHERE fee=150 AND DATE(date_time)=:date;
    ",
    //Кол-во записей в billing_success_log по успешным списаниям за текущий день. сумма спиcания 100 SYP
    'billing_success_100' => "
    SELECT COUNT(*)
    FROM billing_success_log 
    WHERE fee=100 AND DATE(date_time)=:date;
    ",
    //Кол-во записей в billing_success_log по успешным списаниям за текущий день. сумма спиcания 50 SYP
    'billing_success_50' => "
    SELECT COUNT(*)
    FROM billing_success_log 
    WHERE fee=50 AND DATE(date_time)=:date;
    ",
    //Кол-во записей в FB_Billing_transactions  по неуспешным списаниям за текущий день 
    'billing_fail' => "
    SELECT COUNT(*) 
    FROM FB_billing_transactions 
    WHERE charge_status = 'FAILED' 
    AND DATE(charge_request_date)=:date;
    ",
    //Кол-во отписок за текущий день, таблица FB_subscritions_archive, статус подписки = '2' предыдущий статус status IN (0,8)
    'unsubscribe_trial' => "
    SELECT COUNT(*) AS cnt
    FROM FB_subscriptions_archive
    WHERE previous_status IN (0, 8)
      AND status = 2
      AND DATE(stop_date) = :date;
    ",
    //Кол-во отписок за текущий день, таблица FB_subscritions_archive, статус подписки = '2' предыдущий статус status IN (1,3,5,6)
    'unsubscribe_paid' => "
    SELECT COUNT(*) AS cnt
    FROM FB_subscriptions_archive
    WHERE previous_status IN (1, 3, 5, 6)
      AND status IN (2, 4)
      AND DATE(stop_date) = :date;
    ",
    //from 'billing_success_log' sum( fee ) за текущий день 
    'billing_success_sum' => "
    SELECT SUM( fee ) 
    FROM billing_success_log 
    WHERE DATE( date_time ) = :date;
    ",
    //from 'billing_success_log' number of unique msisdn
    'unique_scharge_msisdn'=>"
    SELECT COUNT(DISTINCT msisdn) AS unique_msisdn_count
    FROM billing_success_log
    WHERE DATE(date_time) = :date;
    "

];
