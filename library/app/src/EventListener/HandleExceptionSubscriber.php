<?php

/*
 * This file is part of the wysiwyl project.
 *
 * (c) Darkwood <mathieu@darkwood.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Darkwood\Wysiwyl\EventListener;

use Bugsnag\Client;
use Darkwood\Wysiwyl\Application\ApplicationInterface;
use Darkwood\Wysiwyl\Exception\AuthenticationException;
use Darkwood\Wysiwyl\Exception\UserExtractionFailedException;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Environment;

class HandleExceptionSubscriber implements EventSubscriberInterface
{
    /**
     * @param \Iterator<ApplicationInterface> $applications
     */
    public function __construct(
        private LoggerInterface $logger,
        private Environment $twig,
        private Client $bugsnag,
        private iterable $applications,
    ) {
    }

    public function handleException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        $statusCode = null;
        $applicationCode = null;

        if ($exception instanceof AuthenticationException) {
            $this->logger->error(sprintf('Authentication error: %s', $exception->getMessage()), [
                'exception' => $exception,
                'previous' => $exception->getPrevious(),
            ]);

            $this->bugsnag->notifyException($exception, function ($report) {
                $report->setSeverity('info');
            });

            $statusCode = 401;

            $applicationCode = $exception->getApplicationCode();
        } elseif ($exception instanceof UserExtractionFailedException) {
            $this->logger->error('Could not retrieve users', [
                'exception' => $exception,
            ]);

            $this->bugsnag->notifyException($exception, function ($report) {
                $report->setSeverity('error');
            });

            $applicationCode = $exception->getApplicationCode();

            $statusCode = 500;
        }

        if (!$statusCode) {
            return;
        }

        if ($applicationCode) {
            $application = $this->getApplication($applicationCode);

            if ($application) {
                $application->reset();
            }
        }

        $response = new Response($this->twig->render('error.html.twig', [
            'exception' => $exception,
        ]), $statusCode);

        $event->setResponse($response);
        $event->stopPropagation();
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['handleException', 255],
        ];
    }

    private function getApplication(string $code): ?ApplicationInterface
    {
        foreach ($this->applications as $application) {
            if ($application->getCode() === $code) {
                return $application;
            }
        }

        return null;
    }
}
