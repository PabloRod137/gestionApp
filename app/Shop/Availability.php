<?php

namespace App\Shop;

use App\Models\Appointment;
use Carbon\CarbonImmutable;

/** Franjas de cita: las que genera el horario del despacho menos las ya ocupadas. */
class Availability
{
    /** Fecha YYYY-MM-DD real (rechaza 2026-02-30) o null. */
    public static function parseDate(string $value): ?CarbonImmutable
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }
        $date = CarbonImmutable::createFromFormat('!Y-m-d', $value, config('shop.time_zone'));

        return $date && $date->format('Y-m-d') === $value ? $date : null;
    }

    private static function minutes(string $time): int
    {
        [$hour, $minute] = array_map('intval', explode(':', $time));

        return $hour * 60 + $minute;
    }

    private static function time(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    /** Franjas que permite el horario, sin tener en cuenta las citas ya reservadas. */
    public static function generated(array $area, string $date, ?CarbonImmutable $now = null): array
    {
        $day = self::parseDate($date);
        if (! $day || $day->isWeekend()) {
            return [];
        }
        $zone = config('shop.time_zone');
        $earliest = ($now ?? CarbonImmutable::now($zone))->addMinutes(config('shop.min_notice_minutes'));
        $step = config('shop.slot_step_minutes');

        $slots = [];
        foreach (config('shop.business_windows') as [$start, $end]) {
            for ($cursor = self::minutes($start); $cursor + $area['duration_minutes'] <= self::minutes($end); $cursor += $step) {
                $time = self::time($cursor);
                if (CarbonImmutable::createFromFormat('Y-m-d H:i', "$date $time", $zone)->greaterThanOrEqualTo($earliest)) {
                    $slots[] = $time;
                }
            }
        }

        return $slots;
    }

    /** Intervalos [inicio, fin) en minutos de las citas activas de ese día. */
    private static function busy(string $date, bool $lock = false): array
    {
        $query = Appointment::where('fecha', $date)->where('estado', '!=', 'cancelada')->whereNotNull('hora');
        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->get(['hora', 'duracion_minutos'])->map(function (Appointment $appointment) {
            $start = self::minutes(substr($appointment->hora, 0, 5));

            return [$start, $start + ($appointment->duracion_minutos ?: 45)];
        })->all();
    }

    /** Franjas libres de un día. Con $lock se bloquean las citas del día (para comprobar y guardar sin carreras). */
    public static function free(array $area, string $date, bool $lock = false): array
    {
        $busy = self::busy($date, $lock);

        return array_values(array_filter(self::generated($area, $date), function (string $time) use ($area, $busy) {
            $start = self::minutes($time);
            $end = $start + $area['duration_minutes'];
            foreach ($busy as [$busyStart, $busyEnd]) {
                if ($start < $busyEnd && $busyStart < $end) {
                    return false;
                }
            }

            return true;
        }));
    }
}
