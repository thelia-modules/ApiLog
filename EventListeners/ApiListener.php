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
            KernelEvents::REQUEST => ['apiRequest', EventPriorities::PRE_READ],
            KernelEvents::RESPONSE => ['apiResponse', EventPriorities::POST_WRITE],
        ];
    }

    public function apiRequest(RequestEvent $event): void
    {
        $method = $event->getRequest()->getMethod();
        $requestPath = $event->getRequest()->getPathInfo();
        $options = $event->getRequest()->query->all();
        $prefix = explode('/', $requestPath, 3)[1];

        if($prefix === 'api') {
            $this->logger->logApiRequest(
                $method,
                $requestPath,
                $options,
            );
        }
    }

    public function apiResponse(ResponseEvent $event): void
    {
        $method = $event->getRequest()->getMethod();
        $requestPath = $event->getRequest()->getPathInfo();
        $options = $event->getRequest()->query->all();
        $statusCode = $event->getResponse()->getStatusCode();
        $prefix = explode('/', $requestPath, 3)[1];

        if($prefix === 'api') {
            $this->logger->logApiResponse(
                $method,
                $requestPath,
                $statusCode,
                $options,
            );
        }
    }
}
