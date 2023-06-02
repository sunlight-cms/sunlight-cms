<?php

namespace Sunlight;

abstract class Cron
{
    static function run(): void
    {
        $tasks = self::getTasks();

        $cronNow = time();
        $cronUpdate = false;
        $cronLockFileHandle = null;
        $cronTimes = Settings::get('cron_times');

        if ($cronTimes !== '') {
            $cronTimes = unserialize($cronTimes);
        } else {
            $cronTimes = [];
            $cronUpdate = true;
        }

        foreach ($tasks as $taskName => $task) {
            if (isset($cronTimes[$taskName])) {
                // last run time is known
                if ($cronNow - $cronTimes[$taskName] >= $task['interval']) {
                    // check lock file
                    if ($cronLockFileHandle === null) {
                        $cronLockFile = SL_ROOT . 'system/cron.lock';
                        $cronLockFileHandle = fopen($cronLockFile, 'r');

                        if (!flock($cronLockFileHandle, LOCK_EX | LOCK_NB)) {
                            // lock file is not accessible
                            fclose($cronLockFileHandle);
                            $cronLockFileHandle = null;
                            $cronUpdate = false;
                            break;
                        }
                    }

                    // run task
                    $delay = $cronNow - $cronTimes[$taskName];
                    $task['callback']($cronTimes[$taskName], $delay);

                    Extend::call('cron.task', [
                        'name' => $taskName,
                        'task' => $task,
                        'last' => $cronTimes[$taskName],
                        'delay' => $delay,
                    ]);

                    // update last run time
                    $cronTimes[$taskName] = $cronNow;
                    $cronUpdate = true;
                }
            } else {
                // unknown last run time
                $cronTimes[$taskName] = $cronNow;
                $cronUpdate = true;
            }
        }

        // update run times
        if ($cronUpdate) {
            // remove unknown intervals
            foreach (array_keys($cronTimes) as $cronTimeKey) {
                if (!isset($tasks[$cronTimeKey])) {
                    unset($cronTimes[$cronTimeKey]);
                }
            }

            // save
            Settings::update('cron_times', serialize($cronTimes));
        }

        // free lock file
        if ($cronLockFileHandle !== null) {
            flock($cronLockFileHandle, LOCK_UN);
            fclose($cronLockFileHandle);
        }
    }

    /**
     * @return array<string, array{interval: int, callback: callable}>
     */
    private static function getTasks(): array
    {
        $tasks = [
            'maintenance' => [
                'interval' => (int) Settings::get('maintenance_interval'),
                'callback' => [SystemMaintenance::class, 'run'],
            ],
        ];

        Extend::call('cron.init', ['tasks' => &$tasks]);

        return $tasks;
    }
}
