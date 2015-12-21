<?php

/**
 * Конфигурация демонов
 */
return [
    'watcher'      => [
        'multi-instance'  => true,
        'child-processes' => 100,
        'sleep'           => 60,
    ],
];
