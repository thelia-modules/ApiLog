<?php

declare(strict_types=1);

/*
 * This file is part of the project KDI PowR Connect.
 * This project is protected by proprietary license.
 * Do not share this file, unless you have permission.
 */

namespace ApiLog\Controller;

use ApiLog\Service\LoggingHttpClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Thelia\Core\HttpFoundation\JsonResponse;

#[Route(path: '/api/apiTest', name: 'apiTest')]
final class TestController extends AbstractController
{
    public function __construct(
        private readonly LoggingHttpClient $httpClient,
    ) {
    }

    #[Route(
        path: '/client',
        name: '_client',
        methods: [Request::METHOD_GET]
    )]
    public function apiTestClient(Request $request): JsonResponse
    {
        $url = 'https://tyradex.vercel.app/api/v1/pokemon';

        $response = $this->httpClient->request(Request::METHOD_GET, $url);

dd($response->getContent());

        return new JsonResponse();
    }
}
