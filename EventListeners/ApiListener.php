<?php

declare(strict_types=1);
namespace ApiLog\EventListeners;

use ApiLog\Logger\CustomLogger;
use ApiPlatform\Symfony\EventListener\EventPriorities;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class ApiListener implements EventSubscriberInterface
{
     public function __construct(
        private readonly CustomLogger $logger,
     ) {
     }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['apipRequest', EventPriorities::PRE_READ],
            KernelEvents::RESPONSE => ['apipResponse', EventPriorities::POST_WRITE],
        ];
    }

    public function apipRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $event->getRequest()->attributes->set('_start_time', microtime(true));
    }

    public function apipResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        if ($this->isApiPlatformRequest($event->getRequest())) {
            $start = $event->getRequest()->attributes->get('_start_time');
            $duration = $start ? round((microtime(true) - $start) * 1000, 2) : null;

            $this->logger->logApipResponse(
                $event->getRequest()->getMethod(),
                $event->getRequest()->getPathInfo(),
                $event->getResponse()->getStatusCode(),
                $duration,
                $event->getRequest()->query->all(),
            );
        }
    }

    /**
     * En Symfony 7, l'attribut `_controller` n'est pas toujours une chaine : les
     * controleurs resolus arrivent sous forme de tableau [service, methode] ou de
     * closure. Un explode() direct dessus levait une TypeError sur toutes les
     * pages du front. Seules les routes API Platform portent un identifiant
     * textuel prefixe par « api_platform. ».
     */
    private function isApiPlatformRequest(Request $request): bool
    {
        $controller = $request->attributes->get('_controller');

        return \is_string($controller) && str_starts_with($controller, 'api_platform.');
    }
}
