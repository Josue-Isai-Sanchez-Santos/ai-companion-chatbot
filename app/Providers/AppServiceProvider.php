<?php

namespace App\Providers;

use App\Ai\Contracts\ChatGateway;
use App\Ai\Contracts\EmbeddingGateway;
use App\Models\Character;
use App\Models\Conversation;
use App\Models\GeneratedAsset;
use App\Models\Memory;
use App\Policies\CharacterPolicy;
use App\Policies\ConversationPolicy;
use App\Policies\GeneratedAssetPolicy;
use App\Policies\MemoryPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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
        Gate::policy(
            Character::class,
            CharacterPolicy::class
        );

        Gate::policy(
            Conversation::class,
            ConversationPolicy::class
        );

        Gate::policy(
            Memory::class,
            MemoryPolicy::class
        );

        Gate::policy(
            GeneratedAsset::class,
            GeneratedAssetPolicy::class
        );

        RateLimiter::for(
            'character-reset',

            function (
                Request $request
            ): Limit {
                $maximum = max(
                    1,
                    (int) config(
                        'chatbot.rate_limits.reset_per_hour',
                        3
                    )
                );

                $key =
                    $request->user()?->id
                        !== null
                        ? 'user:'
                            .$request
                                ->user()
                                ->id
                        : 'ip:'
                            .$request->ip();

                return Limit::perHour(
                    $maximum
                )->by(
                    $key
                );
            }
        );
    }
}
