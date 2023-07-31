<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Importer;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:reset',
    description: '(Re)instantiate application',
)]
class ResetCommand extends Command
{
    public function __construct(
        private ManagerRegistry $registry,
        private Importer $importer,
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(
                'date',
                InputArgument::OPTIONAL,
                'Import data for this date. Defaults to today.',
            );
        $this
            ->addArgument(
                'url',
                InputArgument::OPTIONAL,
                'Download URL for GTFS data',
                default: 'https://gtfs.openov.nl/gtfs-rt/gtfs-openov-nl.zip'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->resetDatabase($output);
        $this->importData($input, $output);
        $this->createRoutingGraph($output);
        $this->createProcedure($output);

        return Command::SUCCESS;
    }

    private function resetDatabase(OutputInterface $output): void
    {
        $this->getApplication()
            ->find('doctrine:database:drop')
            ->run(new ArrayInput(['--force' => true]), $output);

        $this->getApplication()
            ->find('doctrine:database:create')
            ->run(new ArrayInput([]), $output);

        $migrationInput = new ArrayInput([]);
        $migrationInput->setInteractive(false);
        $this->getApplication()
            ->find('doctrine:migrations:migrate')
            ->run($migrationInput, $output);
    }

    private function importData(InputInterface $input, OutputInterface $output): void
    {
        ini_set('memory_limit', -1);

        // Declare variables
        $url = $input->getArgument('url');
        $date = $input->getArgument('date') ?? date('Y-m-d');
        $tmp = sys_get_temp_dir() . '/gtfs-openov-nl';

        // Download
        $output->writeln("Downloading $url to $tmp.zip");
        copy($url, "$tmp.zip");

        // Extract
        $output->writeln("Attempting to extract $tmp.zip");
        $zip = new \ZipArchive();
        $result = $zip->open("$tmp.zip");

        if (!$result) {
            throw new \RuntimeException("Could not open GTFS file at $tmp.zip");
        }

        if (!is_dir($tmp)) {
            mkdir($tmp);
        }

        $zip->extractTo($tmp);
        $zip->close();

        // Import
        $output->writeln("Importing GTFS data");
        $this->importer->import($tmp, new \DateTimeImmutable($date));
    }

    private function createRoutingGraph(OutputInterface $output): void
    {
        $output->writeln('Assembling routing graph');

        $connection = $this->registry->getConnection();

        $connection->exec('
            INSERT INTO node (name)
            SELECT DISTINCT
                name
            FROM
                stop
            WHERE
                parent IS NULL
        ');

        $connection->exec('
            UPDATE
                stop
            INNER JOIN
                node ON
                    stop.name = node.name
            SET
                node_id = node.id
        ');

        $connection->exec('
            UPDATE
                stop
            INNER JOIN
                stop parent ON
                    stop.parent = parent.external_id
            SET
                stop.node_id = parent.node_id
        ');

        $connection->exec('
            CREATE TABLE stop_sequence (
                id INT(11),
                stop_sequence INT(11),
                
                PRIMARY KEY (id)
            )
        ');

        $connection->exec('
            INSERT INTO
                stop_sequence
            SELECT
                id,
                ROW_NUMBER() OVER (PARTITION BY trip_id ORDER BY stop_sequence) row_num
            FROM
                stop_time
        ');

        $connection->exec('
            INSERT INTO
                edge (from_node_id, to_node_id, distance, stops)
            SELECT
                S_a.node_id,
                S_b.node_id,
                MIN(B.shape_dist_traveled - A.shape_dist_traveled) AS shape_dist_traveled,
                MIN(B_seq.stop_sequence - A_seq.stop_sequence) as stop_sequence
            FROM
                stop_time A
            INNER JOIN
                stop_time B ON
                    A.trip_id = B.trip_id
                    AND
                    A.stop_sequence < B.stop_sequence
            INNER JOIN
                stop_sequence A_seq ON
                    A.id = A_seq.id
            INNER JOIN
                stop_sequence B_seq ON
                    B.id = B_seq.id
            INNER JOIN
                trip ON
                    A.trip_id = trip.id
            INNER JOIN
                route ON
                    trip.route_id = route.id
            INNER JOIN
                stop S_a ON
                    S_a.id = A.stop_id
            INNER JOIN
                stop S_b ON
                    S_b.id = B.stop_id
            GROUP BY
                S_a.node_id,
                S_b.node_id
        ');

        $connection->exec('DROP TABLE stop_sequence');
    }

    private function createProcedure(OutputInterface $output): void
    {
        $output->writeln('Creating procedure');

        $connection = $this->registry->getConnection();

        $connection->executeQuery("DROP PROCEDURE IF EXISTS PLAN_JOURNEY");
        $connection->executeQuery("
            CREATE PROCEDURE PLAN_JOURNEY(
                IN origin VARCHAR(255),            -- Station name
                IN destination VARCHAR(255),       -- Station name
                IN journey_departure_time DATETIME -- Just a datetime
            )
            BEGIN
                -- ---------------------------------------------------------------------- --
                -- VARIABLE DECLARATIONS                                                  --
                -- ---------------------------------------------------------------------- --
                DECLARE orig_node INTEGER;
                DECLARE dest_node INTEGER;
                DECLARE done BOOLEAN;
                DECLARE path VARCHAR(255);
            
                -- These variables are used for the algorithm that looks up departures and
                -- arrivals in `stop_time` for each `path`
                DECLARE node VARCHAR(255);
                DECLARE step SMALLINT DEFAULT 0;
                DECLARE dest VARCHAR(255);
                DECLARE earliest_arrival_time DATETIME;
            
                -- By declaring a cursor we can iterate over the results of this common
                -- table expression.
                DECLARE best_paths_cursor CURSOR FOR
                    WITH RECURSIVE
                        allowed_node (node_id) AS (
                            SELECT DISTINCT
                                node_id
                            FROM
                                stop
                            INNER JOIN (
                                SELECT
                                    -- 0.25 latitude and 0.20 longitude should be roughly
                                    -- equivalent to 22 kilometres in the Netherlands
                                    MIN(latitude)  - 0.25 AS min_lat,
                                    MAX(latitude)  + 0.25 AS max_lat,
                                    MIN(longitude) - 0.25 AS min_lon,
                                    MAX(longitude) + 0.25 AS max_lon
                                FROM
                                    stop
                                WHERE
                                    node_id IN (
                                        orig_node,
                                        dest_node
                                    )
                            ) bounding_box ON
                                latitude BETWEEN min_lat AND max_lat
                                AND
                                longitude BETWEEN min_lon AND max_lon
                                AND
                                -- This is a stupid hack to remove anything that’s not
                                -- clearly a train station, as bus/tram/metro stops in this
                                -- dataset tend to have names that include both a place and
                                -- a street name, e.g. “Amsterdam, Rokin”.
                                name NOT LIKE '%,%'
                        ),
                        possible_path (nodes, transfers, stops, distance, current_node) AS (
                            -- Base case
                            SELECT
                                CONCAT(' – ', CAST(from_node_id AS CHAR(255)), ' – '),
                                0,
                                stops,
                                distance,
                                to_node_id
                            FROM
                                edge
                            WHERE
                                from_node_id = orig_node
                                AND
                                to_node_id IN (SELECT node_id FROM allowed_node)
            
                            UNION ALL
            
                            -- Recursive case
                            SELECT
                                CONCAT(nodes, from_node_id, ' – '),
                                transfers + 1,
                                possible_path.stops + edge.stops,
                                possible_path.distance + edge.distance,
                                to_node_id
                            FROM
                                possible_path
                            INNER JOIN
                                edge ON
                                    current_node = from_node_id
                            WHERE
                                -- Only consider nodes within that rectangular bounding box
                                -- that we discussed earlier.
                                to_node_id IN (SELECT node_id FROM allowed_node)
                                AND
                                -- Used to detect cycles within a possible path. It makes no
                                -- sense to visit the same station twice!
                                nodes NOT LIKE CONCAT('% – ', to_node_id, ' – %')
                                AND
                                -- This basically sets the number of “layers” that our
                                -- pathfinding algorithm is allowed to explore. With
                                -- transfers set to 3, the execution time is roughly a
                                -- second on my machine. With 4 it’s about 20 seconds. I
                                -- haven’t tried to set it to 5, but it’ll probably take
                                -- *very* long to complete.
                                transfers < 3
                        )
                    SELECT DISTINCT
                        -- The SUBSTRING() is used to remove the leading “ – ” that we added
                        -- in the base case.
                        SUBSTRING(CONCAT(nodes, current_node), 4) AS path
                    FROM
                        possible_path
                    WHERE
                        current_node = dest_node
                    ORDER BY
                        -- This seems to work reasonably well as a way to score different
                        -- paths. The basic idea is that direct connections (fewer
                        -- transfers) are preferred over journeys with many transfers, and
                        -- intercity/express trains (fewer stops) are preferred over slower,
                        -- local trains (many stops).
                        transfers * stops,
                        distance
                    LIMIT
                        5; -- Completely arbitrary limit
            
                -- Make sure that algorithm terminates
                DECLARE CONTINUE HANDLER FOR NOT FOUND
                    SET done = TRUE;
            
                -- ---------------------------------------------------------------------- --
                -- INPUT VALIDATION                                                       --
                -- ---------------------------------------------------------------------- --
            
                -- Retrieve `node.id`s for departure and arrival stations
                SET orig_node = (
                    SELECT
                        id
                    FROM
                        node
                    WHERE
                        name COLLATE utf8mb4_unicode_ci = origin
                );
                SET dest_node = (
                    SELECT
                        id
                    FROM
                        node
                    WHERE
                        name COLLATE utf8mb4_unicode_ci = destination
                );
            
                -- Abort procedure with a descriptive message if something is wrong with the
                -- input.
                IF orig_node IS NULL THEN
                    SIGNAL SQLSTATE '02404'
                        SET MESSAGE_TEXT =  'Origin node not found.';
                END IF;
            
                IF dest_node IS NULL THEN
                    SIGNAL SQLSTATE '02404'
                        SET MESSAGE_TEXT =  'Destination node not found.';
                END IF;
            
                IF origin = destination THEN
                    SIGNAL SQLSTATE '02400'
                        SET MESSAGE_TEXT = 'Origin and destination are the same.';
                END IF;
            
                -- ---------------------------------------------------------------------- --
                -- GENERATE ADVICE                                                        --
                -- ---------------------------------------------------------------------- --
            
                -- This table will be used to store the final results
                CREATE TEMPORARY TABLE IF NOT EXISTS travel_advice (
                    journey    VARCHAR(255),
                    type       VARCHAR(255),
                    time       DATETIME,
                    station    VARCHAR(255),
                    platform   VARCHAR(255),
                    train_type VARCHAR(255),
                    headsign   VARCHAR(255),
                    departure  DATETIME,
                    arrival    DATETIME,
                    
                    PRIMARY KEY (journey, time)
                );
            
                OPEN best_paths_cursor;
            
                read_loop: LOOP
                    -- We look up timetables for each `possible_path`. Intermediate results
                    -- are stored per path in `tmp_path_advice`.
                    CREATE TEMPORARY TABLE IF NOT EXISTS tmp_path_advice (
                        stop_times        TEXT,
                        cur_step          SMALLINT,
                        cur_stop_id       INTEGER,
                        cur_trip_id       INTEGER,
                        cur_stop_sequence SMALLINT,
                        first_datetime    DATETIME,
                        cur_datetime      DATETIME,
                        is_complete       BOOLEAN DEFAULT FALSE
                    );
            
                    FETCH best_paths_cursor INTO path;
            
                    IF done THEN
                        LEAVE read_loop;
                    END IF;
            
                    SET step = 0;
                    SET earliest_arrival_time = NULL;
                    -- Extract the last `node_id` from the `path` string.
                    SET dest = SUBSTRING_INDEX(SUBSTRING_INDEX(path, ' – ', -1), ' – ', 1);
            
                    WHILE LENGTH(path) > 0 DO
                            -- The `node` and `path` variables keep track of the current
                            -- state of the algorithm. These only need to be set when a
                            -- traveller reaches a new station, i.e. when they depart from
                            -- their origin and when they arrive at another station.
                            IF step = 0 OR step % 2 = 1 THEN
                                SET node = SUBSTRING_INDEX(path, ' – ', 1);
                                SET path = SUBSTRING(path, LENGTH(node) + 3);
                                SET node = TRIM(node);
                            END IF;
            
                            -- The first leg of the journey is special, because we do not
                            -- start at a specific platform and thus also do not have to
                            -- take transfer times into account.
                            IF step = 0 THEN
                                INSERT INTO
                                    tmp_path_advice
                                SELECT
                                    -- These are all initial values
                                    stop_time.id,   -- stop_times
                                    step,           -- cur_step
                                    stop_id,        -- cur_stop_id
                                    trip_id,        -- cur_trip_id
                                    stop_sequence,  -- cur_stop_sequence
                                    departure_time, -- first_datetime
                                    departure_time, -- cur_datetime
                                    FALSE           -- is_complete
                                FROM
                                    stop_time
                                INNER JOIN
                                    stop ON
                                        stop_id = stop.id
                                WHERE
                                    node_id = node
                                    AND
                                    departure_time BETWEEN
                                        -- Retrieve all trains that depart in the next 30
                                        -- minutes
                                        journey_departure_time
                                        AND
                                        DATE_ADD(journey_departure_time, INTERVAL 30 MINUTE)
                                ORDER BY
                                    departure_time;
                            -- Arrivals at new stations are handled as part of odd steps.
                            ELSEIF step % 2 = 1 THEN
                                INSERT INTO
                                    tmp_path_advice
                                SELECT
                                    CONCAT(stop_times, ',', stop_time.id), -- stop_times
                                    step,                                  -- cur_step
                                    stop_id,                               -- cur_stop_id
                                    trip_id,                               -- cur_trip_id
                                    stop_sequence,                         -- cur_stop_sequ…
                                    first_datetime,                        -- first_datetim…
                                    stop_time.arrival_time,                -- cur_datetime
                                    node_id = dest                         -- is_complete
                                FROM
                                    tmp_path_advice
                                INNER JOIN
                                    stop_time ON
                                        -- The train has already stopped at stations with a
                                        -- lower stop_sequence, so we’ll need to ignore
                                        -- those.
                                        stop_time.trip_id = cur_trip_id
                                        AND
                                        stop_time.stop_sequence > cur_stop_sequence
                                INNER JOIN
                                    stop ON
                                        stop_id = stop.id
                                WHERE
                                    cur_step = step - 1
                                    AND
                                    stop_times IS NOT NULL
                                ORDER BY
                                    arrival_time;
                            ELSE
                                -- Departures from stations are handled as part of even
                                -- steps.
                                INSERT INTO
                                    tmp_path_advice
                                SELECT DISTINCT
                                    CONCAT(stop_times, ',', stop_time.id), -- stop_times
                                    step AS step,                          -- cur_step
                                    stop_id,                               -- cur_stop_id
                                    trip_id,                               -- cur_trip_id
                                    stop_sequence,                         -- cur_stop_sequ…
                                    first_datetime,                        -- first_datetim…
                                    departure_time,                        -- cur_datetime
                                    node_id = dest                         -- is_complete
                                FROM
                                    tmp_path_advice
                                INNER JOIN
                                    stop ON
                                        node_id = node
                                INNER JOIN
                                    stop_time ON
                                        stop.id = stop_id
                                INNER JOIN
                                    trip cur_trip ON cur_trip.id = cur_trip_id
                                INNER JOIN
                                    trip new_trip ON new_trip.id = stop_time.trip_id
                                LEFT JOIN
                                    transfer ON
                                        from_stop_id = cur_stop_id
                                        AND
                                        (
                                            to_stop_id = stop_id
                                            OR
                                            to_stop_id IN (
                                                SELECT
                                                    id
                                                FROM
                                                    stop
                                                WHERE
                                                    name = (
                                                        SELECT
                                                            name
                                                        FROM
                                                            stop
                                                        WHERE
                                                            id = stop_id
                                                    )
                                            )
                                        )
                                WHERE
                                    cur_step = step - 1
                                    AND
                                    stop_times IS NOT NULL
                                    AND
                                    -- This is the Netherlands, so we’re not willing to
                                    -- consider “transfers” that take more than half an
                                    -- hour.
                                    departure_time BETWEEN
                                        DATE_ADD(
                                            cur_datetime,
                                            INTERVAL min_transfer_time SECOND
                                        )
                                        AND
                                        DATE_ADD(cur_datetime, INTERVAL 30 MINUTE)
                                    AND
                                    -- Do not reboard same train
                                    cur_trip.route_id <> new_trip.route_id
                                    AND
                                    -- Do not transfer to train with same destination 
                                    NOT (
                                        cur_trip.headsign = new_trip.headsign
                                        AND
                                        cur_trip.long_name = new_trip.long_name
                                    )
                                ORDER BY
                                    departure_time;
                            END IF;
            
                            SET step = step + 1;
                        END WHILE;
            
                        -- Get rid of all partial trip advice. We only need the final
                        -- results.
                        DELETE
                            tmp_path_advice
                        FROM
                            tmp_path_advice
                        WHERE
                            is_complete = FALSE;
            
                        -- Determine the earliest possible time of arrival across all
                        -- possible journeys.
                        SET earliest_arrival_time = (
                            SELECT MIN(cur_datetime)
                            FROM tmp_path_advice
                        );
            
                        -- Discard all travel advice that takes more than 30 minutes longer
                        -- than the fastest possible journey.
                        DELETE
                            tmp_path_advice
                        FROM
                            tmp_path_advice
                        WHERE
                            cur_datetime > DATE_ADD(
                                earliest_arrival_time,
                                INTERVAL 30 MINUTE
                            );
            
                        -- This SELECT query generates the human-readable output of this
                        -- procedure.
                        INSERT IGNORE INTO
                            travel_advice
                        SELECT DISTINCT
                            SUBSTRING(SHA1(stop_times), 1, 8) AS journey,
                            IF(sequence % 2 = 1, 'Departure', 'Arrival') AS type,
                            IF(sequence % 2 = 1, departure_time, arrival_time) AS time,
                            stop.name AS station,
                            stop.platform,
                            CONCAT(agency.name, ' ', trip.long_name) AS train_type,
                            trip.headsign,
                            first_datetime AS journey_departure,
                            cur_datetime AS journey_arrival 
                        FROM
                            tmp_path_advice
                        CROSS JOIN
                            -- `stop_times` is a comma-separated string. By converting it
                            -- to a JSON list we can iterate over it and extract the key
                            -- (`sequence`) and `value` of each element.
                            JSON_TABLE (
                                CONCAT('[', stop_times, ']'),
                                '$[*]'
                                COLUMNS (
                                    sequence FOR ORDINALITY,
                                    value INT PATH '$'
                                )
                            ) result
                        INNER JOIN
                            stop_time ON
                                result.value = stop_time.id
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
                            is_complete
                        ORDER BY
                            -- We want to arrive at our destination as early as possible.
                            -- When multiple options have the same arrival time, prefer
                            -- options that take less time, i.e. have a later time of
                            -- departure.
                            cur_datetime,
                            first_datetime DESC,
                            journey,  -- Used to keep the rows for each journey together
                            sequence; -- Keep rows within journeys in the right order
            
                    DROP TEMPORARY TABLE IF EXISTS tmp_path_advice;
            
                END LOOP;
            
                CLOSE best_paths_cursor;
            
                -- ---------------------------------------------------------------------- --
                -- GENERATE OUTPUT                                                        --
                -- ---------------------------------------------------------------------- --
            
                SELECT DISTINCT
                    travel_advice.journey,
                    type,
                    SUBSTRING(TIME(time), 1, 5) AS time, -- “Timmeh!”
                    station,
                    platform,
                    train_type,
                    headsign,
                    SUBSTRING(TIMEDIFF(arrival, departure), 1, 5) AS duration,
                    UNIX_TIMESTAMP(time) AS timestamp
                FROM
                    travel_advice
                INNER JOIN (
                    SELECT
                        journey,
                        COUNT(*) / 2 - 1 AS num_transfers
                    FROM
                        travel_advice
                    GROUP BY
                        journey
                ) journey_row_count 
                    USING (journey)
                WHERE
                    -- We’re still not interested in alternative suggestions that are
                    -- significantly worse than the best option
                    arrival <= DATE_ADD(earliest_arrival_time, INTERVAL 30 MINUTE)
                ORDER BY
                    arrival,        -- Arrive as early as possible
                    num_transfers,  -- Fewer transfers are better
                    departure DESC, -- Depart as late as possible
                    journey,        -- Group departures and arrivals for each journey
                    timestamp;      -- For human readability
            
                DROP TEMPORARY TABLE IF EXISTS travel_advice;
            END;
        ");
    }
}
