<?php

namespace Tests\Feature;

use App\Enums\Logs\EventType;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicAdministrationEventsSubscriberTest extends TestCase
{
    use RefreshDatabase;

    private $publicAdministration;

    protected function setUp(): void
    {
        parent::setUp();
        $this->publicAdministration = factory(PublicAdministration::class)->create();
    }

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

    public function testPublicAdministrationActivated(): void
    {
        $this->expectLogMessage('notice', [
            'Public Administration ' . $this->publicAdministration->info . ' activated',
            [
                'event' => EventType::PUBLIC_ADMINISTRATION_ACTIVATED,
                'pa' => $this->publicAdministration->ipa_code,
            ],
        ]);

        event(new PublicAdministrationActivated($this->publicAdministration));
    }

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
