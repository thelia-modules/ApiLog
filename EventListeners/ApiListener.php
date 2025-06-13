<?php
namespace ApiLog\EventListeners;

use ApiLog\Logger\CustomLogger;
use ApiPlatform\Symfony\EventListener\EventPriorities;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class ApiListener implements EventSubscriberInterface
{
     public function __construct(
        private readonly CustomLogger $logger,
     ) {
     }

    public static function getSubscribedEvents()
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

//        $controller = $event->getRequest()->attributes->get('_controller');
//        $isAPIP = explode('.',  $controller)[0] === 'api_platform';
//
//        if($isAPIP) {
//            $method = $event->getRequest()->getMethod();
//            $requestPath = $event->getRequest()->getPathInfo();
//            $options = $event->getRequest()->query->all();
//
//            $this->logger->logApipRequest(
//                $method,
//                $requestPath,
//                $options,
//            );
//        }
    }

    public function apipResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $controller = $event->getRequest()->attributes->get('_controller');
        $isAPIP = explode('.',  $controller)[0] === 'api_platform';

        if($isAPIP) {
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
}
