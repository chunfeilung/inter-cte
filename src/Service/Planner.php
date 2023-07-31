<?php

declare(strict_types=1);

namespace App\Service;

use Illuminate\Support\Collection;

readonly class Planner
{
    public function __construct(private Database $database) {}

    public function plan(string $from, string $to, int $timestamp): array
    {
        $result = $this->database->planJourney($from, $to, $timestamp);

        if (empty($result)) {
            return [];
        }

        return collect($result)
            ->groupBy('journey')
            ->map(fn (Collection $journey) => $this->transform($journey))
            ->toArray();
    }

    private function transform(Collection $journey): Collection
    {
        return $journey->reduce(static function (Collection $result, array $row) {
            if ($result->isEmpty()) {
                $result->add(collect($row)->only(['type', 'time', 'station', 'platform', 'duration']));
                return $result;
            }

            if ($row['type'] === 'Arrival') {
                $trip = collect($row)->only(['train_type', 'headsign']);
                $trip['type'] = 'Trip';
                $result->add($trip);
                $result->add(collect($row)->only(['type', 'time', 'station', 'platform', 'timestamp']));
                return $result;
            }
            if ($row['type'] === 'Departure') {
                /** @var Collection $transfer */
                $transfer = $result->pop();
                $transfer['type'] = 'Transfer';
                $transfer['times'] = [$transfer['time'], $row['time']];
                $transfer['platforms'] = [$transfer['platform'], $row['platform']];
                $transfer['transfer_time'] = (int) floor((int) $row['timestamp'] - (int) $transfer['timestamp']) / 60;
                unset($transfer['time'], $transfer['platform'], $transfer['timestamp']);
                $result->add($transfer);
                return $result;
            }
            return $result;
        }, collect());
    }
}
