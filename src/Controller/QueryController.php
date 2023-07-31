<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\Database;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class QueryController extends AbstractController
{
    private const EXAMPLE_QUERIES = [
        <<<STOP_TIME_QUERY
        SELECT
            *
        FROM
            stop_time
        LIMIT
            500;
        STOP_TIME_QUERY,

        <<<TABLES_QUERY
        SHOW TABLES;
        TABLES_QUERY,

        <<<AGENCY_QUERY
        SELECT * FROM agency;
        AGENCY_QUERY,

        <<<PLAN_JOURNEY_QUERY
        CALL PLAN_JOURNEY(
            'Nijmegen Heyendaal',
            'Amsterdam Science Park',
            NOW()
        );
        PLAN_JOURNEY_QUERY,

        <<<AMSTERDAM_STATION_QUERY
        SELECT
            *
        FROM
            stop
        WHERE
            name = 'Amsterdam Centraal';
        AMSTERDAM_STATION_QUERY,
    ];

    public function __construct(
        private readonly Database $database,
    ) {}

    #[Route('/structuredql', name: 'app_query', methods: ['GET', 'POST'])]
    public function structuredql(Request $request): JsonResponse
    {
        $query = $request->get('query');

        if (!$query) {
            return $this->json(null, 204);
        }

        return $this->json([
            'result' => $this->database->execute($query),
        ]);
    }

    #[Route('/query', name: 'app_query_ui')]
    public function query(Request $request): Response
    {
        $query = $request->get('query');

        if ($request->get('random')) {
            $query = collect(self::EXAMPLE_QUERIES)->random();
        }

        if (!$query) {
            return $this->render('query.html.twig', [
                'query' => '',
                'result' => null,
                'success' => true,
            ]);
        }

        try {
            $result = $this->database->execute($query);
            $success = true;
        } catch (\Exception $e) {
            $result = $e->getMessage();
            $success = false;
        }

        return $this->render('query.html.twig', compact(
            'query',
            'result',
            'success',
        ));
    }
}
