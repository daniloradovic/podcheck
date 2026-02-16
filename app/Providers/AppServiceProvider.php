<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Checks\ChannelChecks\ArtworkCheck;
use App\Services\Checks\ChannelChecks\AuthorCheck;
use App\Services\Checks\ChannelChecks\CategoryCheck;
use App\Services\Checks\ChannelChecks\DescriptionCheck as ChannelDescriptionCheck;
use App\Services\Checks\ChannelChecks\ExplicitTagCheck;
use App\Services\Checks\ChannelChecks\LanguageCheck;
use App\Services\Checks\ChannelChecks\OwnerEmailCheck;
use App\Services\Checks\ChannelChecks\WebsiteLinkCheck;
use App\Services\Checks\EpisodeChecks\DescriptionCheck as EpisodeDescriptionCheck;
use App\Services\Checks\EpisodeChecks\DurationCheck;
use App\Services\Checks\EpisodeChecks\EnclosureCheck;
use App\Services\Checks\EpisodeChecks\GuidCheck;
use App\Services\Checks\EpisodeChecks\PubDateCheck;
use App\Services\Checks\EpisodeChecks\TitleCheck;
use App\Services\FeedValidator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(FeedValidator::class, function ($app): FeedValidator {
            return new FeedValidator(
                channelChecks: [
                    $app->make(ArtworkCheck::class),
                    $app->make(CategoryCheck::class),
                    $app->make(ExplicitTagCheck::class),
                    $app->make(AuthorCheck::class),
                    $app->make(OwnerEmailCheck::class),
                    $app->make(LanguageCheck::class),
                    $app->make(WebsiteLinkCheck::class),
                    $app->make(ChannelDescriptionCheck::class),
                ],
                episodeChecks: [
                    $app->make(EnclosureCheck::class),
                    $app->make(GuidCheck::class),
                    $app->make(PubDateCheck::class),
                    $app->make(DurationCheck::class),
                    $app->make(TitleCheck::class),
                    $app->make(EpisodeDescriptionCheck::class),
                ],
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
