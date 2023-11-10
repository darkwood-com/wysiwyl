<?php

/*
 * This file is part of the wysiwyl project.
 *
 * (c) Darkwood <mathieu@darkwood.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Darkwood\Wysiwyl\Controller;

use Darkwood\Wysiwyl\Statistic\StatisticCollector;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Twig\Environment;

class ContentController extends AbstractController
{
    public function __construct(
        private Environment $twig,
        private StatisticCollector $statisticCollector,
    ) {
    }

    #[Route('/', name: 'homepage', methods: ['GET'])]
    public function homepage(): Response
    {
        $content = $this->twig->render('content/homepage.html.twig');

        return new Response($content);
    }

    #[Route('/terms-of-service', name: 'terms', methods: ['GET'])]
    public function terms(): Response
    {
        $content = $this->twig->render('content/terms.html.twig');

        return new Response($content);
    }

    #[Route('/privacy-policy', name: 'privacy_policy', methods: ['GET'])]
    public function privacyPolicy(): Response
    {
        $content = $this->twig->render('content/privacy_policy.html.twig');

        return new Response($content);
    }

    #[Route('/hall-of-fame', name: 'hall_of_fame', methods: ['GET'])]
    public function hallOfFame(): Response
    {
        $companies = [
        ];

        $content = $this->twig->render('content/hall_of_fame.html.twig', [
            'companies' => $companies,
        ]);

        return new Response($content);
    }

    #[Route('/stats', name: 'stats', methods: ['GET'])]
    public function stats(): Response
    {
        $content = $this->twig->render('content/stats.html.twig', [
            'counters' => $this->statisticCollector->getCounters(),
        ]);

        return new Response($content);
    }
}
