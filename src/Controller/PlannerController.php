<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\Database;
use App\Service\Planner;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PlannerController extends AbstractController
{
    public function __construct(
        private readonly Database $database,
        private readonly Planner $planner,
    ) {}

    #[Route('/', name: 'planner')]
    public function planner(Request $request): Response
    {
        $origin = $this->tryStation($request->get('from'));
        $destination = $this->tryStation($request->get('to'));
        $when = $request->get('when') ?: time();

        $plans = $origin && $destination
            ? $this->planner->plan($origin, $destination, (int) $when)
            : [];

        return $this->render('planner.html.twig', [
            'range' => $this->database->getDepartureTimeRange((int) $when),
            'stations' => $this->database->getStations(),
            'origin' => $origin,
            'destination' => $destination,
            'plans' => $plans,
        ]);
    }

    #[Route('/departures', name: 'departures')]
    public function departures(Request $request): Response
    {
        $selectedStation = $this->tryStation($request->get('station'));
        $departures = $selectedStation !== null
            ? $this->database->getDepartures($selectedStation)
            : [];

        return $this->render('departures.html.twig', [
            'stations' => $this->database->getStations(),
            'selected_station' => $selectedStation,
            'departures' => $departures,
        ]);
    }

    private function tryStation(?string $value): ?string
    {
        return in_array($value, $this->database->getStations(), true)
            ? $value
            : null;
    }
}
