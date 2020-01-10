<?php

namespace Tests\Feature;

use App\Enums\Logs\EventType;
use App\Enums\WebsiteType;
use App\Events\PublicAdministration\PublicAdministrationActivated;
use App\Events\PublicAdministration\PublicAdministrationActivationFailed;
use App\Events\PublicAdministration\PublicAdministrationNotFoundInIpa;
use App\Events\PublicAdministration\PublicAdministrationPrimaryWebsiteUpdated;
use App\Events\PublicAdministration\PublicAdministrationPurged;
use App\Events\PublicAdministration\PublicAdministrationRegistered;
use App\Events\PublicAdministration\PublicAdministrationUpdated;
use App\Models\PublicAdministration;
use App\Models\User;
use App\Models\Website;
use App\Services\MatomoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Public administration events listener tests.
 */
class PublicAdministrationEventsSubscriberTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The public administration.
     *
     * @var PublicAdministration the public administration
     */
    private $publicAdministration;

    /**
     * Pre-tests setup.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->publicAdministration = factory(PublicAdministration::class)->create();
        factory(Website::class)->create([
            'type' => WebsiteType::PRIMARY,
            'analytics_id' => 1,
            'public_administration_id' => $this->publicAdministration->id,
        ]);
    }

    /**
     * Test roll-up registering successful on public administration activation.
     */
    public function testPublicAdministrationActivatedRollUpRegistering(): void
    {
        $this->app->bind('analytics-service', function () {
            return $this->partialMock(MatomoService::class, function ($mock) {
                $mock->shouldReceive('registerRollUp')
                    ->once()
                    ->andReturn(1);

                $mock->shouldReceive('registerUser')
                    ->once();

                $mock->shouldReceive('getUserAuthToken')
                    ->once()
                    ->andReturn('faketoken');

                $mock->shouldReceive('setWebsiteAccess')
                    ->once();

                $mock->shouldReceive('setWebsiteAccess')
                    ->once();
            });
        });

        $this->expectLogMessage('notice', [
            'Public Administration ' . $this->publicAdministration->info . ' activated',
            [
                'event' => EventType::PUBLIC_ADMINISTRATION_ACTIVATED,
                'pa' => $this->publicAdministration->ipa_code,
            ],
        ]);

        event(new PublicAdministrationActivated($this->publicAdministration));
    }

    /**
     * Test roll-up registering throwing exception.
     */
    public function testPublicAdministrationActivatedRollUpRegisteringFail(): void
    {
        $this->app->bind('analytics-service', function () {
            return $this->partialMock(MatomoService::class, function ($mock) {
                $mock->shouldReceive('registerRollUp')
                    ->andThrow(\Exception::class, 'Public administration roll-up exception testing');
            });
        });

        Log::shouldReceive('error')
            ->withSomeOfArgs('Public administration roll-up exception testing');

        $this->expectLogMessage('notice', [
            'Public Administration ' . $this->publicAdministration->info . ' activated',
            [
                'event' => EventType::PUBLIC_ADMINISTRATION_ACTIVATED,
                'pa' => $this->publicAdministration->ipa_code,
            ],
        ]);

        event(new PublicAdministrationActivated($this->publicAdministration));
    }

    /**
     * Test public administration registered event handler.
     */
    public function testPublicAdministrationRegistered(): void
    {
        $user = factory(User::class)->create();

        $this->expectLogMessage('notice', [
            'User ' . $user->uuid . ' registered Public Administration ' . $this->publicAdministration->info,
            [
                'event' => EventType::PUBLIC_ADMINISTRATION_REGISTERED,
                'pa' => $this->publicAdministration->ipa_code,
                'user' => $user->uuid,
            ],
        ]);

        event(new PublicAdministrationRegistered($this->publicAdministration, $user));
    }

    /**
     * Test public administration activation failed event handler.
     */
    public function testPublicAdministrationActivationFailed(): void
    {
        $errorMessage = 'Fake error message for public administration activation';

        $this->expectLogMessage('error', [
            'Public Administration ' . $this->publicAdministration->info . ' activation failed: ' . $errorMessage,
            [
                'event' => EventType::PUBLIC_ADMINISTRATION_ACTIVATION_FAILED,
                'pa' => $this->publicAdministration->ipa_code,
            ],
        ]);

        event(new PublicAdministrationActivationFailed($this->publicAdministration, $errorMessage));
    }

    /**
     * Test public administration updated event handler.
     */
    public function testPublicAdministrationUpdated(): void
    {
        $this->expectLogMessage('notice', [
            'Public Administration ' . $this->publicAdministration->info . ' updated',
            [
                'event' => EventType::PUBLIC_ADMINISTRATION_UPDATED,
                'pa' => $this->publicAdministration->ipa_code,
            ],
        ]);

        event(new PublicAdministrationUpdated($this->publicAdministration, []));
    }

    /**
     * Test public administration primary website activated event handler.
     */
    public function testPublicAdministrationNotFoundInIpa(): void
    {
        $this->expectLogMessage('warning', [
            'Public Administration ' . $this->publicAdministration->info . ' not found',
            [
                'event' => EventType::PUBLIC_ADMINISTRATION_UPDATED,
                'pa' => $this->publicAdministration->ipa_code,
            ],
        ]);

        event(new PublicAdministrationNotFoundInIpa($this->publicAdministration));
    }

    /**
     * Test public administration primary website activated event handler.
     */
    public function testPublicAdministrationPrimaryWebsiteUpdated(): void
    {
        $newURL = 'fakenewurl.local';
        $website = factory(Website::class)->create([
            'public_administration_id' => $this->publicAdministration->id,
        ]);

        $this->expectLogMessage('warning', [
            'Public Administration ' . $this->publicAdministration->info . ' primary website was changed in IPA index [' . $newURL . '].',
            [
                'event' => EventType::PUBLIC_ADMINISTRATION_PRIMARY_WEBSITE_CHANGED,
                'pa' => $this->publicAdministration->ipa_code,
            ],
        ]);

        event(new PublicAdministrationPrimaryWebsiteUpdated($this->publicAdministration, $website, $newURL));
    }

    /**
     * Test public administration deleted event handler.
     */
    public function testPublicAdministrationPurged(): void
    {
        $this->expectLogMessage('notice', [
            'Public Administration ' . $this->publicAdministration->getInfo() . ' purged',
            [
                'event' => EventType::PUBLIC_ADMINISTRATION_PURGED,
                'pa' => $this->publicAdministration->ipa_code,
            ],
        ]);

        event(new PublicAdministrationPurged($this->publicAdministration->toJson()));
    }
}
