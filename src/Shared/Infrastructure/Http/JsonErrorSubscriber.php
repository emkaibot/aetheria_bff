<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use App\Shared\Domain\DomainError;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Los errores salen siempre en JSON: los de reglas del juego (DomainError) como
 * 422 `{error, message}`, y los HTTP como `{error}`. Sin esto, un 404 de Symfony
 * llega a la app como una página HTML y el `res.json()` revienta.
 */
final class JsonErrorSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::EXCEPTION => ['onException', -10]];
    }

    public function onException(ExceptionEvent $event): void
    {
        $e = $event->getThrowable();

        if ($e instanceof DomainError) {
            $event->setResponse(new JsonResponse(
                ['error' => $e->code(), 'message' => $e->getMessage()],
                JsonResponse::HTTP_UNPROCESSABLE_ENTITY,
            ));

            return;
        }

        if (!$e instanceof HttpExceptionInterface) {
            return;
        }

        $event->setResponse(new JsonResponse(
            ['error' => $e->getMessage() !== '' ? $e->getMessage() : 'http_error'],
            $e->getStatusCode(),
            $e->getHeaders(),
        ));
    }
}
