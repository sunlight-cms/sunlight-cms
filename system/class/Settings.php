<?php

namespace Sunlight;

use Kuria\Debug\Dumper;
use Sunlight\Database\Database as DB;

abstract class Settings
{
    /** @var array */
    private static $settings = [];
    private static $initialized = false;

    static function init(): void
    {
        if (self::$initialized) {
            throw new \LogicException('Already initialized');
        }

        $cond = Core::$env === Core::ENV_ADMIN
            ? 'admin=1'
            : 'web=1';

        $query = DB::query('SELECT var,val FROM ' . DB::table('setting') . ' WHERE preload=1 AND ' . $cond);

        while ($row = DB::row($query)) {
            self::$settings[$row['var']] = $row['val'];
        }

        Extend::call('settings.init');

        self::$initialized = true;
    }

    /**
     * Get a setting
     */
    static function get(string $setting): string
    {
        if (!isset(self::$settings[$setting])) {
            // lazy-load settings that haven't been preloaded
            self::loadSetting($setting);
        }

        return self::$settings[$setting];
    }

    /**
     * Get multiple settings
     */
    static function getMultiple(array $settings): array
    {
        $result = [];
        $missing = [];

        foreach ($settings as $setting) {
            $result[$setting] = self::$settings[$setting] ?? null;

            if ($result[$setting] === null) {
                $missing[] = $setting;
            }
        }

        if (!empty($missing)) {
            self::loadSettings($missing);

            foreach ($missing as $setting) {
                $result[$setting] = self::$settings[$setting];
            }
        }

        return $result;
    }

    /**
     * Update a setting
     */
    static function update(string $setting, string $newValue, bool $log = true): void
    {
        $oldValue = self::get($setting);

        DB::update('setting', 'var=' . DB::val($setting), ['val' => $newValue]);

        if ($log) {
            Logger::notice(
                'system',
                sprintf(
                    'Updated setting "%s" from %s to %s',
                    $setting,
                    Dumper::dump($oldValue),
                    Dumper::dump($newValue)
                ),
                ['setting' => $setting, 'old_value' => $oldValue, 'new_value' => $newValue]
            );
        }

        self::$settings[$setting] = $newValue;
    }

    /**
     * Overwrite a setting for the current request (not saved to the database)
     */
    static function overwrite(string $setting, string $newValue): void
    {
        self::$settings[$setting] = $newValue;
    }

    private static function loadSetting(string $setting): void
    {
        $result = DB::queryRow('SELECT val FROM ' . DB::table('setting') . ' WHERE var=' . DB::val($setting));

        if ($result === false) {
            throw new \OutOfBoundsException(sprintf('Unknown setting "%s"', $setting));
        }

        self::$settings[$setting] = $result['val'];
    }

    private static function loadSettings(array $settings): void
    {
        $values = DB::queryRows('SELECT var,val FROM ' . DB::table('setting') . ' WHERE var IN(' . DB::arr($settings) . ')', 'var', 'val');

        if (count($values) !== count($settings)) {
            $unknownSettings = [];

            foreach ($settings as $setting) {
                if (!isset($values[$setting])) {
                    $unknownSettings[] = $setting;
                }
            }

            throw new \OutOfBoundsException(sprintf(
                'Unknown settings: %s',
                implode(', ', array_map(function (string $setting) { return '"' . $setting . '"'; }, $unknownSettings))
            ));
        }

        self::$settings += $values;
    }
}
