<?php

namespace App\Middlewares;

class Middleware {
  public function handle(
    callable $controller,
    $request,
    array $middlewares
  ) {
$next = function ($request) use ($controller) {
    return $controller($request);
};

        foreach (array_reverse($middlewares) as $middleware) {
            $previousNext = $next;

            $next = function ($request) use (
                $middleware,
                $previousNext
            ) {
                return $middleware::handle(
                    $request,
                    $previousNext
                );
            };
        }

        return $next($request);
    }
}