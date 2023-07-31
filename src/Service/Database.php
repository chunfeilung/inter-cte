<?php

declare(strict_types=1);

namespace App\Service;

use Doctrine\DBAL\Statement;
use Doctrine\Persistence\ManagerRegistry;

readonly class Database
{
    public function __construct(
        private ManagerRegistry $managerRegistry
    ) {}

    public function execute(string $query, array $parameters = []): array
    {
        /** @var Statement $stmt */
        $stmt = $this->managerRegistry
            ->getConnection()
            ->prepare($query);

        foreach ($parameters as $i => $parameter) {
            $stmt->bindValue($i + 1, $parameter);
        }

        return $stmt->executeQuery()->fetchAllAssociative();
    }

    public function getDepartureTimeRange(?int $when): array
    {
        $query = $this->execute('
            SELECT
                UNIX_TIMESTAMP(MIN(departure_time)) AS min,
                UNIX_TIMESTAMP(MAX(departure_time)) AS max
            FROM
                stop_time
            WHERE
                departure_time <> arrival_time
        ');

        $result = $query[0];
        $result['cur'] = $when ?? time();

        if ($result['cur'] < $result['min'] || $result['cur'] > $result['max']) {
            $result['cur'] = strtotime(date('Y-m-d 08:00:00', $result['min']));
        }

        return $result;
    }

    public function getStations(): array
    {
        $query = $this->execute('
            SELECT DISTINCT
                node.name
            FROM
                node
            INNER JOIN
                stop ON
                    node_id = node.id
            INNER JOIN
                stop_time ON
                    stop_id = stop.id
            ORDER BY
                1
        ');

        return array_map(fn ($n) => $n['name'], $query);
    }

    public function getDepartures(string $station): array
    {
        return $this->execute(
            '
                SELECT 
                    TIME(departure_time) AS time,
                    trip.headsign,
                    stop.platform,
                    CONCAT(agency.name, " ", trip.long_name) AS train_type
                FROM
                    stop_time
                INNER JOIN
                    stop ON
                        stop_id = stop.id
                INNER JOIN
                    trip ON
                        trip_id = trip.id
                INNER JOIN
                    route ON
                        route_id = route.id
                INNER JOIN
                    agency ON
                        agency_id = agency.id
                WHERE
                    stop.name = ?
                    AND
                    departure_time BETWEEN
                        NOW()
                        AND
                        DATE_ADD(NOW(), INTERVAL 1 HOUR)
                    AND
                    stop.name <> headsign
                ORDER BY
                    departure_time
                LIMIT
                    20
            ',
            [$station]
        );
    }

    public function planJourney(string $from, string $to, int $timestamp): array
    {
        $when = date('Y-m-d H:i:s', $timestamp);
        return $this->execute(
            'CALL PLAN_JOURNEY(?, ?, ?)',
            [$from, $to, $when],
        );
    }
}
