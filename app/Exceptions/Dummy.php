<?php

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    protected $dontReport = [
        // tu peux lister des exceptions à ignorer ici
    ];

    public function register(): void
    {
        //
    }

    public function render($request, Throwable $e)
    {
        // Réponses JSON uniformes pour l’API
        if ($request->expectsJson() || $request->is('api/*')) {
            // Validation
            if ($e instanceof ValidationException) {
                return response()->json([
                    'message' => 'Les données soumises sont invalides.',
                    'errors'  => $e->errors(),
                ], 422);
            }

            // Non authentifié
            if ($e instanceof AuthenticationException) {
                return response()->json([
                    'message' => 'Non authentifié.',
                ], 401);
            }

            // Non autorisé
            if ($e instanceof AuthorizationException) {
                return response()->json([
                    'message' => 'Action non autorisée.',
                ], 403);
            }

            // Modèle introuvable → 404
            if ($e instanceof ModelNotFoundException) {
                return response()->json([
                    'message' => 'Ressource introuvable.',
                ], 404);
            }

            // Route introuvable
            if ($e instanceof NotFoundHttpException) {
                return response()->json([
                    'message' => 'Route introuvable.',
                ], 404);
            }

            // Méthode non autorisée
            if ($e instanceof MethodNotAllowedHttpException) {
                return response()->json([
                    'message' => 'Méthode HTTP non autorisée pour cette route.',
                ], 405);
            }

            // Limitation de débit
            if ($e instanceof ThrottleRequestsException) {
                return response()->json([
                    'message' => 'Trop de requêtes, réessayez plus tard.',
                ], 429);
            }

            // Erreurs SQL génériques (masquées)
            if ($e instanceof QueryException) {
                return response()->json([
                    'message' => 'Erreur serveur (base de données).',
                ], 500);
            }

            // HttpException avec code
            if ($e instanceof HttpExceptionInterface) {
                return response()->json([
                    'message' => $e->getMessage() ?: 'Erreur HTTP.',
                ], $e->getStatusCode());
            }

            // Fallback générique (cache l’erreur en prod)
            $status = app()->hasDebugModeEnabled() ? 500 : 500;
            $payload = ['message' => 'Erreur serveur.'];
            if (config('app.debug')) {
                $payload['exception'] = class_basename($e);
                $payload['trace_id']  = request()->header('X-Request-Id');
            }
            return response()->json($payload, $status);
        }

        // Pour les autres (web), comportement par défaut
        return parent::render($request, $e);
    }

    protected function unauthenticated($request, AuthenticationException $exception)
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['message' => 'Non authentifié.'], 401);
        }
        return redirect()->guest(route('login'));
    }
}
