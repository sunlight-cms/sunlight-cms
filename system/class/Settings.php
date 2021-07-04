<?php

namespace Sunlight;

use Sunlight\Database\Database as DB;

abstract class Settings
{
    /** @var array */
    private static $settings;

    static function init(): void
    {
        if (self::$settings !== null) {
            throw new \LogicException('Already initialized');
        }

        self::$settings = [];

        $cond = _env === Core::ENV_ADMIN
            ? 'admin=1'
            : 'web=1';

        $query = DB::query('SELECT var,val FROM ' . _setting_table . ' WHERE preload=1 AND ' . $cond);

        while ($row = DB::row($query)) {
            self::$settings[$row['var']] = $row['val'];
        }

        Extend::call('settings.init');
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
     * Update a setting in the database
     */
    static function update(string $setting, string $newValue): void
    {
        DB::update(_setting_table, 'var=' . DB::val($setting), ['val' => $newValue]);
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
        $result = DB::queryRow('SELECT val FROM ' . _setting_table . ' WHERE var=' . DB::val($setting));

        if ($result === false) {
            throw new \OutOfBoundsException(sprintf('Unknown setting "%s"', $setting));
        }

        self::$settings[$setting] = $result['val'];
    }

    private static function loadSettings(array $settings): void
    {
        $values = DB::queryRows('SELECT var,val FROM ' . _setting_table . ' WHERE var IN(' . DB::val($settings, true) . ')', 'var', 'val');

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
