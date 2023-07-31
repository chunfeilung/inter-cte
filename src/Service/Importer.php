<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Agency;
use App\Entity\Route;
use App\Entity\Stop;
use App\Entity\StopTime;
use App\Entity\Transfer;
use App\Entity\Trip;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class Importer
{
    private readonly string $directory;
    private readonly \DateTimeInterface $date;
    /**
     * @var array<string, Agency>
     */
    private array $agencies = [];
    /**
     * @var array<string, Stop>
     */
    private array $stops = [];
    /**
     * @var array<string, Route>
     */
    private array $routes = [];
    /**
     * @var array<int, bool>
     */
    private array $serviceIds = [];
    /**
     * @var array<string, Trip>
     */
    private array $trips = [];
    private const RAIL_ROUTE_TYPE = 2;
    private const IMPOSSIBLE_TRANSFER = 3;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {}

    public function import(string $directory, \DateTimeInterface $date): void
    {
        $this->directory = $directory;
        $this->date = $date;

        $this->importAgencies();
        $this->importStops();
        $this->importTransfers();
        $this->importRoutes();

        $this->importServiceIds();

        $this->importTrips();
        $this->importStopTimes();
    }

    private function importAgencies(): void
    {
        $this->processCsvFile('agency.txt', function (array $data) {
            [$id, $name] = $data;
            $agency = new Agency(
                externalId: $id,
                name: $name,
            );
            $this->agencies[$id] = $agency;
            return $agency;
        });
    }

    private function importStops(): void
    {
        $this->processCsvFile('stops.txt', function (array $data) {
            [$id, , $name, $lat, $lon, , $parent, , , $platform] = $data;

            $stop = new Stop(
                externalId: $id,
                name: $name,
                latitude: (float) $lat,
                longitude: (float) $lon,
                parent: $parent,
                platform: $platform,
            );

            $this->stops[$id] = $stop;

            return $stop;
        });
    }

    private function importRoutes(): void
    {
        $this->processCsvFile('routes.txt', function (array $data) {
            [$id, $agency, $shortName, $longName, , $type] = $data;
            if ((int) $type !== self::RAIL_ROUTE_TYPE) {
                return null;
            }
            $route = new Route(
                externalId: $id,
                agency: $this->agencies[$agency],
                shortName: $shortName,
                longName: $longName,
                type: (int) $type,
            );
            $this->routes[$id] = $route;
            return $route;
        });
    }

    private function importTransfers(): void
    {
        $this->processCsvFile('transfers.txt', function (array $data) {
            [$fromStop, $toStop, , , , , $type, $minTransferTime] = $data;

            if ((int) $type === self::IMPOSSIBLE_TRANSFER) {
                return null;
            }

            return new Transfer(
                fromStop: $this->stops[$fromStop],
                toStop: $this->stops[$toStop],
                minTransferTime: (int) $minTransferTime,
            );
        });
    }

    private function importServiceIds(): void
    {
        $this->processCsvFile('calendar_dates.txt', function (array $data) {
            [$id, $date] = $data;
            if ($date === $this->date->format('Ymd')) {
                $this->serviceIds[(int) $id] = true;
            }
            return null;
        });
    }

    private function importTrips(): void
    {
        $this->processCsvFile('trips.txt', function (array $data) {
            [$route, $service, $id, , $headsign, $shortName, $longName,] = $data;

            if (isset($this->serviceIds[$service]) === false) {
                return null;
            }

            if (isset($this->routes[$route]) === false) {
                return null;
            }

            $trip = new Trip(
                externalId: (int) $id,
                route: $this->routes[$route],
                headsign: $headsign,
                shortName: $shortName,
                longName: $longName,
            );
            $this->trips[$id] = $trip;

            return $trip;
        });
    }

    private function importStopTimes(): void
    {
        $this->processCsvFile('stop_times.txt', function (array $data) {
            [$trip, $seq, $stop, $headsign, $arr, $dep, , , , $dist] = $data;

            if (isset($this->trips[$trip]) === false) {
                return null;
            }

            return new StopTime(
                trip: $this->trips[$trip],
                stopSequence: (int) $seq,
                stop: $this->stops[$stop],
                stopHeadsign: $headsign,
                arrivalTime: $this->getDateTime($arr),
                departureTime: $this->getDateTime($dep),
                shapeDistTraveled: (float) $dist,
            );
        });
    }

    private function getDateTime(string $time): ?\DateTimeInterface
    {
        if (empty($time)) {
            return null;
        }

        [$hours, $minutes, $seconds] = explode(':', $time);
        $diff = (int) $seconds + 60 * (int) $minutes + 60 * 60 * (int) $hours;

        return \DateTimeImmutable::createFromInterface($this->date)
            ->add(date_interval_create_from_date_string("$diff seconds"));
    }

    private function processCsvFile(string $fileName, callable $callable, int $batchSize = 50_000)
    {
        $this->logger->info("Processing $fileName");

        $file = fopen("$this->directory/$fileName", 'rb');
        fgetcsv($file);

        $this->entityManager->beginTransaction();

        $i = 1;
        while (($row = fgetcsv($file)) !== false) {
            $maybeEntity = $callable($row);

            if ($maybeEntity) {
                $this->entityManager->persist($maybeEntity);
            }

            if ($i++ >= $batchSize) {
                $this->entityManager->flush();
                $this->entityManager->commit();
                gc_collect_cycles();
                $this->entityManager->beginTransaction();
                $i = 1;
            }
        }

        $this->entityManager->commit();

        fclose($file);
    }
}
