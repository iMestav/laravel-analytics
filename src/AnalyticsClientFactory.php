<?php

namespace Spatie\Analytics;

use Google_Client;
use Google_Service_Analytics;
use Illuminate\Contracts\Cache\Repository;
use Madewithlove\IlluminatePsrCacheBridge\Laravel\CacheItemPool;
use Carbon\Carbon;
use App\Models\Auth\User;

class AnalyticsClientFactory
{
    public static function createForConfig(array $analyticsConfig): AnalyticsClient
    {
        $authenticatedClient = self::createAuthenticatedGoogleClient($analyticsConfig);

        $googleService = new Google_Service_Analytics($authenticatedClient);

        return self::createAnalyticsClient($analyticsConfig, $googleService);
    }

    public static function createAuthenticatedGoogleClient(array $config): Google_Client
    {
        $client = \Google::getClient();
        $client->setAccessType("offline");
        $client->setAccessToken($config['token']);

        if ($client->isAccessTokenExpired()) {
            if ($client->getRefreshToken()) {
                $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
            } else {
                $authUrl = $client->createAuthUrl();
                throw new \Exception($authUrl);
            }
        }

        // if($config['expires']) {
        //     $newCredentials = $client->refreshToken($config['access_token']);
        //     dd($newCredentials);
        //     if($newCredentials != null || $newCredentials != []) {
        //         User::find($config['startup_id'])->apiConnects()->whereProvider('google')->first()->update([
        //             'access_token' => $newCredentials['access_token'],
        //             'refresh_token' => $newCredentials['refresh_token'],
        //             'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
        //             'expires_in' => Carbon::now()->addSeconds($newCredentials['expires_in'])->format('Y-m-d H:i:s')
        //         ]);
        //     }
        // }
        return $client;
    }

    protected static function configureCache(Google_Client $client, $config)
    {
        $config = collect($config);

        $store = \Cache::store($config->get('store'));

        $cache = new CacheItemPool($store);

        $client->setCache($cache);

        $client->setCacheConfig(
            $config->except('store')->toArray()
        );
    }

    protected static function createAnalyticsClient(array $analyticsConfig, Google_Service_Analytics $googleService): AnalyticsClient
    {
        $client = new AnalyticsClient($googleService, app(Repository::class));

        $client->setCacheLifeTimeInMinutes($analyticsConfig['cache_lifetime_in_minutes']);

        return $client;
    }
}
