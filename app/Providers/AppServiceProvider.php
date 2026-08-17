<?php

namespace App\Providers;

use App\Ai\Contracts\ChatGateway;
use App\Ai\Contracts\EmbeddingGateway;
use Illuminate\Support\ServiceProvider;
use LogicException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            ChatGateway::class,
            function ($app): ChatGateway {
                $driver = (string) config(
                    'ai.chat.driver'
                );

                $concrete = config(
                    "ai.chat.drivers.{$driver}"
                );

                if (
                    ! is_string($concrete)
                    || ! is_a(
                        $concrete,
                        ChatGateway::class,
                        true
                    )
                ) {
                    throw new LogicException(
                        "Invalid AI chat driver [{$driver}]."
                    );
                }

                return $app->make($concrete);
            }
        );

        $this->app->singleton(
            EmbeddingGateway::class,
            function ($app): EmbeddingGateway {
                $driver = (string) config(
                    'ai.embedding.driver'
                );

                $concrete = config(
                    "ai.embedding.drivers.{$driver}"
                );

                if (
                    ! is_string($concrete)
                    || ! is_a(
                        $concrete,
                        EmbeddingGateway::class,
                        true
                    )
                ) {
                    throw new LogicException(
                        "Invalid AI embedding driver [{$driver}]."
                    );
                }

                return $app->make($concrete);
            }
        );
    }

    public function boot(): void
    {
        //
    }
}
