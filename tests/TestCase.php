<?php

namespace Tests;

use Amondar\Sextant\SextantServiceProvider;
use AttractCores\LaravelCoreAuth\CoreAuth;
use AttractCores\LaravelCoreAuth\CoreAuthServiceProvider;
use AttractCores\LaravelCoreAuth\CustomPassportServiceProvider;
use AttractCores\LaravelCoreAuth\InitializeCoreRightsServiceProvider;
use AttractCores\LaravelCoreAuth\Resolvers\CoreUserContract;
use AttractCores\LaravelCoreClasses\CoreControllerServiceProvider;
use AttractCores\LaravelCoreKit\CoreKit;
use AttractCores\LaravelCoreKit\KitServiceProvider;
use AttractCores\LaravelCoreTestBench\OauthInteracts;
use AttractCores\LaravelCoreTestBench\PHPUnitConsole;
use AttractCores\LaravelCoreVerificationBroker\VerificationBrokerServiceProvider;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Facade;
use Laravel\Passport\Passport;
use Orchestra\Testbench\TestCase as CoreTestCase;

abstract class TestCase extends CoreTestCase
{
    use RefreshDatabase, OauthInteracts, WithFaker, PHPUnitConsole;

    /**
     * Resolve user class
     *
     * @return Model
     */
    public function resolveUser() : Model
    {
        return app(CoreUserContract::class);
    }

    /**
     * Resolve user class
     *
     * @return Factory
     */
    public function resolveUserFactory() : Factory
    {
        return $this->resolveUser()->factory();
    }

    public function setUp() : void
    {
        Facade::clearResolvedInstances();
        parent::setUp();

        Artisan::call('db:seed',
            [ '--class' => \AttractCores\LaravelCoreAuth\Database\Seeders\DefaultAdminSeeder::class ]);

        Artisan::call('db:seed',
            [ '--class' => \AttractCores\LaravelCoreAuth\Database\Seeders\DefaultRolesAndPermissionsSeeder::class ]);

        $this->runConsoleOutput();

        // Clear framework data.
        \Artisan::call('config:clear');
        \Artisan::call('route:clear');
        \Artisan::call('event:clear');
        //\Artisan::call('cache:clear');

        // Create clients.
        \Artisan::call('passport:client',
            [ '--client' => true, '--name' => 'Clients', '--redirect_uri' => config('app.url'), '--user_id' => NULL, '--provider' => 'users' ]);
        \Artisan::call('passport:client',
            [ '--password' => true, '--name' => 'Password Clients', '--redirect_uri' => config('app.url'), '--user_id' => NULL, '--provider' => 'users' ]);

        Passport::loadKeysFrom(__DIR__ . '/keys/');
        \Artisan::call('passport:keys', [ '--force' => true ]);

        config()->set('auth.guards.api.driver', 'passport');
        config()->set('auth.providers.users.model', get_class($this->resolveUser()));

        foreach (app('kit-auth.permissions') as $permission) {
            app(Gate::class)->define($permission->slug, function (CoreUserContract $user, $isForce = false) use ($permission) {
                if ( $isForce || ! $permission->canBeOverwritten() ) {
                    $result = $user->hasPermissions($permission);
                } else {
                    $result = $user->hasPermissions($permission) || $user->isValidAdmin();
                }

                return $result;
            });
        }

        CoreAuth::enableRoutes();
        CoreKit::enableRoutes();
    }

    public function tearDown() : void
    {
        parent::tearDown();
    }

    protected function getPackageProviders($app)
    {
        return [
            SextantServiceProvider::class,
            CoreControllerServiceProvider::class,
            VerificationBrokerServiceProvider::class,
            CoreAuthServiceProvider::class,
            InitializeCoreRightsServiceProvider::class,
            CustomPassportServiceProvider::class,
            KitServiceProvider::class,
        ];
    }

    /**
     * Return has failed indication as string.
     *
     * @return string
     */
    protected function hasFailedAsString()
    {
        return $this->hasFailed() ? 'true' : 'false';
    }

    /**
     * Return reverted has failed indication as string.
     *
     * @return string
     */
    protected function isSucceededAsString()
    {
        return ! $this->hasFailed() ? 'true' : 'false';
    }
}