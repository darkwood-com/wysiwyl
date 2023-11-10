<?php

/*
 * This file is part of the wysiwyl project.
 *
 * (c) Darkwood <coucou@darkwood.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Darkwood\Wysiwyl\Controller;

use Bugsnag\Client;
use Darkwood\Wysiwyl\Application\ApplicationInterface;
use Darkwood\Wysiwyl\Exception\MessageDispatchTimeoutException;
use Darkwood\Wysiwyl\Exception\MessageSendFailedException;
use Darkwood\Wysiwyl\Form\MessageType;
use Darkwood\Wysiwyl\Form\ParticipantType;
use Darkwood\Wysiwyl\Model\Config;
use Darkwood\Wysiwyl\Model\Wysiwyl;
use Darkwood\Wysiwyl\Santa\MessageDispatcher;
use Darkwood\Wysiwyl\Santa\Rudolph;
use Darkwood\Wysiwyl\Santa\Spoiler;
use Darkwood\Wysiwyl\Statistic\StatisticCollector;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment;

class SantaController extends AbstractController
{
    private const SESSION_KEY_CONFIG = 'config';

    /**
     * @param \Iterator<ApplicationInterface> $applications
     */
    public function __construct(
        private RouterInterface $router,
        private Environment $twig,
        private LoggerInterface $logger,
        private iterable $applications,
        private StatisticCollector $statisticCollector,
        private Client $bugsnag,
    ) {
    }

    #[Route('/run/{application}', name: 'run', methods: ['GET', 'POST'])]
    public function run(Request $request, string $application): Response
    {
        $application = $this->getApplication($application);

        if (!$application->isAuthenticated()) {
            return new RedirectResponse($this->router->generate($application->getAuthenticationRoute()));
        }

        $this->doReset(null, $request);

        $config = new Config(
            $application->getCode(),
            $application->getOrganization(),
            $application->getAdmin(),
        );

        $this->saveConfig($request, $config);

        return $this->redirectToRoute('participants', ['application' => $application->getCode()]);
    }

    #[Route('/event/{application}', name: 'event', methods: ['GET', 'POST'])]
    public function event(Request $request, string $application): Response
    {
        $payload = json_decode($request->getContent(), true);
        
        if (isset($payload['type']) && $payload['type'] === 'url_verification') {
            return $this->json([
                'challenge' => $payload['challenge'],
            ]);
        }

        return $this->redirectToRoute('homepage');
    }

    #[Route('/participants/{application}', name: 'participants', methods: ['GET', 'POST'])]
    public function participants(Request $request, string $application): Response
    {
        $application = $this->getApplication($application);

        if (!$application->isAuthenticated()) {
            return new RedirectResponse($this->router->generate($application->getAuthenticationRoute()));
        }

        $config = $this->getConfigOrThrow404($request);

        // Fetch the users and groups and save them
        if (!$config->getAvailableUsers()) {
            $config->setAvailableUsers($application->getUsers());
            $config->setGroups($application->getGroups());

            $this->saveConfig($request, $config);
        }

        $availableUsers = $config->getAvailableUsers();
        $form = $this->createForm(ParticipantType::class, $config, [
            'available-users' => $availableUsers,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $this->saveConfig($request, $config);
            if ($form->isValid()) {
                return $this->redirectToRoute('message', ['application' => $application->getCode()]);
            }
        }

        $content = $this->twig->render('santa/application/participants_' . $application->getCode() . '.html.twig', ['application' => $application->getCode(),
            'users' => $availableUsers,
            'groups' => $config->getGroups(),
            'form' => $form->createView(),
        ]);

        return new Response($content);
    }

    #[Route('/message/{application}', name: 'message', methods: ['GET', 'POST'])]
    public function message(Rudolph $rudolph, FormFactoryInterface $formFactory, Request $request, Spoiler $spoiler, string $application): Response
    {
        $application = $this->getApplication($application);

        if (!$application->isAuthenticated()) {
            return new RedirectResponse($this->router->generate($application->getAuthenticationRoute()));
        }

        $errors = [];

        $config = $this->getConfigOrThrow404($request);

        // We remove notes from users that aren't selected anymore, and create empty ones for those who are
        // and don't have any yet.
        $selectedUsersAsArray = $config->getSelectedUsers();
        $notes = array_filter($config->getNotes(), function ($userIdentifier) use ($selectedUsersAsArray) {
            return \in_array($userIdentifier, $selectedUsersAsArray, true);
        }, \ARRAY_FILTER_USE_KEY);
        foreach ($config->getSelectedUsers() as $user) {
            $notes[$user] ??= '';
        }

        $config->setNotes($notes);

        $builder = $formFactory->createBuilder(MessageType::class, $config, [
            'selected-users' => $config->getSelectedUsers(),
        ]);

        $application->configureMessageForm($builder);

        $form = $builder->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $this->saveConfig($request, $config);

            if ($form->isValid()) {
                $wysiwyl = $this->prepareWysiwyl($rudolph, $request, $config);
                $session = $request->getSession();
                $session->set(
                    $this->getWysiwylSessionKey(
                        $wysiwyl->getHash()
                    ),
                    $wysiwyl
                );

                // Send a summary to the santa admin
                if ($config->getAdmin()) {
                    $code = $spoiler->encode($wysiwyl);
                    $spoilUrl = $this->generateUrl('spoil', [], UrlGeneratorInterface::ABSOLUTE_URL);

                    $application->sendAdminMessage($wysiwyl, $code, $spoilUrl);
                }

                return $this->redirectToRoute('send_messages', ['hash' => $wysiwyl->getHash()]);
            }

            $errors = array_map(function (FormError $error) {
                return $error->getMessage();
            }, iterator_to_array($form->getErrors(true, false)));

            if ($errors) {
                $errors = array_unique($errors);
            }
        }

        $content = $this->twig->render('santa/application/message_' . $application->getCode() . '.html.twig', [
            'application' => $application->getCode(),
            'admin' => $application->getAdmin(),
            'config' => $config,
            'errors' => $errors,
            'form' => $form->createView(),
        ]);

        return new Response($content);
    }

    #[Route('/sample-message/{application}', name: 'send_sample_message', methods: ['GET', 'POST'])]
    public function sendSampleMessage(Request $request, FormFactoryInterface $formFactory, string $application): Response
    {
        $application = $this->getApplication($application);

        $errors = [];

        if (!$application->isAuthenticated()) {
            $errors['login'] = 'Your session has expired. Please refresh the page.';
        } elseif (!$application->getAdmin()) {
            // An admin is required to use the sample feature
            // Should not happen has the Admin should always be defined
            $errors['no_admin'] = 'You are not allowed to use this feature.';

            $this->bugsnag->notifyError('sample_no_admin', 'Tries to send a sample message without an admin.', function ($report) {
                $report->setSeverity('info');
            });
        }

        $config = $this->getConfigOrThrow404($request);

        $builder = $formFactory->createBuilder(MessageType::class, $config, [
            'selected-users' => $config->getSelectedUsers(),
        ]);

        $application->configureMessageForm($builder);

        $form = $builder->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $notes = array_filter($config->getNotes());

            $candidates = array_filter($notes ? array_keys($notes) : $config->getSelectedUsers(), function ($id) use ($application) {
                return $application->getAdmin()->getIdentifier() !== $id;
            });

            $receiver = $candidates ? $candidates[array_rand($candidates)] : $application->getAdmin()->getIdentifier();

            $formErrors = array_map(function (FormError $error) {
                return $error->getMessage();
            }, iterator_to_array($form->getErrors(true, false)));

            if ($formErrors) {
                $formErrors = array_unique($formErrors);
            }

            $errors = array_merge($errors, $formErrors);

            if ($form->isValid()) {
                $wysiwyl = new Wysiwyl(
                    'sample',
                    [],
                    $config
                );

                try {
                    $application->sendSecretMessage($wysiwyl, $application->getAdmin()->getIdentifier(), $receiver, true);

                    $this->statisticCollector->incrementSampleCount($wysiwyl);
                } catch (MessageSendFailedException $e) {
                    $errors['send'] = $e->getMessage();
                }
            }
        }

        return new JsonResponse([
            'success' => empty($errors),
            'errors' => $errors,
        ]);
    }

    #[Route('/send-messages/{hash}', name: 'send_messages', methods: ['GET', 'POST'])]
    public function sendMessages(MessageDispatcher $messageDispatcher, Request $request, string $hash): Response
    {
        $wysiwyl = $this->getWysiwylOrThrow404($request, $hash);
        $application = $this->getApplication($wysiwyl->getConfig()->getApplication());

        if (!$request->isXmlHttpRequest()) {
            if ($wysiwyl->isDone()) {
                return new RedirectResponse($this->router->generate('finish', [
                    'hash' => $wysiwyl->getHash(),
                ]));
            }

            if (!$application->isAuthenticated()) {
                return new RedirectResponse($this->router->generate($application->getAuthenticationRoute()));
            }

            $content = $this->twig->render('santa/send_messages.html.twig', [
                'application' => $application->getCode(),
                'wysiwyl' => $wysiwyl,
            ]);

            return new Response($content);
        }

        $timeout = false;
        $error = false;

        try {
            $messageDispatcher->dispatchRemainingMessages($wysiwyl, $application);
        } catch (MessageDispatchTimeoutException $e) {
            $timeout = true;
        } catch (MessageSendFailedException $e) {
            $wysiwyl->addError($e->getMessage(), $e->getRecipient()->getIdentifier());

            $this->logger->error($e->getMessage(), [
                'exception' => $e,
            ]);
            $this->bugsnag->notifyException($e, function ($report) {
                $report->setSeverity('info');
            });

            $error = true;
        }

        $this->finishSantaIfDone($request, $wysiwyl, $application);

        $request->getSession()->set(
            $this->getWysiwylSessionKey(
                $wysiwyl->getHash()
            ),
            $wysiwyl
        );

        return new JsonResponse([
            'count' => \count($wysiwyl->getAssociations()) - \count($wysiwyl->getRemainingAssociations()),
            'timeout' => $timeout,
            'finished' => $error || $wysiwyl->isDone(),
        ]);
    }

    #[Route('/finish/{hash}', name: 'finish', methods: ['GET'])]
    public function finish(Request $request, string $hash): Response
    {
        $wysiwyl = $this->getWysiwylOrThrow404($request, $hash);

        $content = $this->twig->render('santa/finish.html.twig', [
            'wysiwyl' => $wysiwyl,
        ]);

        return new Response($content);
    }

    #[Route('/retry/{hash}', name: 'retry', methods: ['GET'])]
    public function retry(Request $request, string $hash): Response
    {
        $wysiwyl = $this->getWysiwylOrThrow404($request, $hash);

        $wysiwyl->resetErrors();

        return $this->redirectToRoute('send_messages', ['hash' => $hash]);
    }

    #[Route('/cancel/{application}', name: 'cancel', methods: ['GET'])]
    public function cancel(Request $request, string $application): Response
    {
        $application = $this->getApplication($application);

        if (!$application->isAuthenticated()) {
            return $this->redirectToRoute('homepage');
        }

        $this->doReset($application, $request);

        return $this->redirectToRoute('homepage');
    }

    #[Route('/spoil', name: 'spoil', methods: ['GET', 'POST'])]
    public function spoil(Request $request, Spoiler $spoiler): Response
    {
        $code = $request->request->get('code');
        $invalidCode = false;
        $associations = null;

        if ($code) {
            $associations = $spoiler->decode($code);

            if (null === $associations) {
                $invalidCode = true;
            } else {
                $this->statisticCollector->incrementSpoilCount();
            }
        }

        $content = $this->twig->render('santa/spoil.html.twig', [
            'code' => $code,
            'invalidCode' => $invalidCode,
            'associations' => $associations,
        ]);

        return new Response($content);
    }

    private function getApplication(string $code): ApplicationInterface
    {
        foreach ($this->applications as $application) {
            if ($application->getCode() === $code) {
                return $application;
            }
        }

        throw $this->createNotFoundException(sprintf('Unknown application %s.', $code));
    }

    private function getWysiwylSessionKey(string $hash): string
    {
        return sprintf('wysiwyl-%s', $hash);
    }

    private function prepareWysiwyl(Rudolph $rudolph, Request $request, Config $config): Wysiwyl
    {
        $selectedUsersAsArray = $config->getSelectedUsers();

        $associatedUsers = $rudolph->associateUsers($selectedUsersAsArray);

        $hash = md5(serialize($associatedUsers));

        return new Wysiwyl(
            $hash,
            $associatedUsers,
            $config,
        );
    }

    private function saveConfig(Request $request, Config $config): void
    {
        $session = $request->getSession();
        $session->set(self::SESSION_KEY_CONFIG, $config);
    }

    private function getConfigOrThrow404(Request $request): Config
    {
        $session = $request->getSession();

        /** @var Config|null $config * */
        $config = $session->get(self::SESSION_KEY_CONFIG);

        if (!$config) {
            throw $this->createNotFoundException('No config found in session.');
        }

        return $config;
    }

    private function getWysiwylOrThrow404(Request $request, string $hash): Wysiwyl
    {
        $wysiwyl = $request->getSession()->get(
            $this->getWysiwylSessionKey(
                $hash
            )
        );

        if (!$wysiwyl) {
            throw $this->createNotFoundException('No wysiwyl found in session.');
        }

        return $wysiwyl;
    }

    private function doReset(?ApplicationInterface $application, Request $request): void
    {
        $session = $request->getSession();
        $session->remove(self::SESSION_KEY_CONFIG);

        $application?->reset();
    }

    private function finishSantaIfDone(Request $request, Wysiwyl $wysiwyl, ApplicationInterface $application): void
    {
        if ($wysiwyl->isDone()) {
            $this->statisticCollector->incrementUsageCount($wysiwyl);
            $this->doReset($application, $request);
        }
    }
}
