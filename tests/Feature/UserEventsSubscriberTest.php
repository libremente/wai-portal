<?php

namespace Tests\Feature;

use App\Enums\Logs\EventType;
use App\Enums\UserStatus;
use App\Enums\WebsiteAccessType;
use App\Events\User\UserActivated;
use App\Events\User\UserDeleted;
use App\Events\User\UserEmailChanged;
use App\Events\User\UserLogin;
use App\Events\User\UserLogout;
use App\Events\User\UserReactivated;
use App\Events\User\UserRestored;
use App\Events\User\UserStatusChanged;
use App\Events\User\UserSuspended;
use App\Events\User\UserUpdated;
use App\Events\User\UserWebsiteAccessChanged;
use App\Models\PublicAdministration;
use App\Models\User;
use App\Models\Website;
use App\Notifications\VerifyEmail;
use App\Traits\InteractsWithRedisIndex;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * User events listener tests.
 */
class UserEventsSubscriberTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The user.
     *
     * @var User the user
     */
    private $user;

    /**
     * Pre-test setup.
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->user = factory(User::class)->create();
    }

    /**
     * Test email verified event handler.
     */
    public function testEmailVerified(): void
    {
        $this->expectLogMessage('info', [
            'User ' . $this->user->uuid . ' confirmed email address.',
            [
                'event' => EventType::USER_VERIFIED,
                'user' => $this->user->uuid,
            ],
        ]);

        event(new Verified($this->user));
    }

    /**
     * Test user activated event handler.
     */
    public function testActivated(): void
    {
        $publicAdministration = factory(PublicAdministration::class)->state('active')->create();
        $this->expectLogMessage('notice', [
            'User ' . $this->user->uuid . ' activated',
            [
                'event' => EventType::USER_ACTIVATED,
                'user' => $this->user->uuid,
                'pa' => $publicAdministration->ipa_code,
            ],
        ]);

        event(new UserActivated($this->user, $publicAdministration));
    }

    /**
     * Test user updated event handler.
     */
    public function testUpdated(): void
    {
        $this->partialMock(InteractsWithRedisIndex::class)
            ->shouldAllowMockingProtectedMethods()
            ->shouldReceive('updateUsersIndex')
            ->with($this->user);

        event(new UserUpdated($this->user));
    }

    /**
     * Test super-admin email changed event handler.
     */
    public function testEmailChangedRegisteredAndSuperAdmin(): void
    {
        Notification::fake();

        $this->expectLogMessage('notice', [
            'User ' . $this->user->uuid . ' email address changed',
            [
                'event' => EventType::USER_EMAIL_CHANGED,
                'user' => $this->user->uuid,
            ],
        ]);

        event(new UserEmailChanged($this->user));

        Notification::assertSentTo(
            [$this->user],
            VerifyEmail::class,
            function ($notification, $channels) {
                return null === $notification->publicAdministration;
            }
        );
    }

    /**
     * Test invited user email changed event handler.
     */
    public function testEmailChangedForInvited(): void
    {
        Notification::fake();

        $publicAdministration = factory(PublicAdministration::class)->state('active')->create();
        $this->user->status = UserStatus::INVITED;
        $this->user->save();
        $publicAdministration->users()->sync([$this->user->id]);

        event(new UserEmailChanged($this->user));

        Notification::assertSentTo(
            [$this->user],
            VerifyEmail::class,
            function ($notification, $channels) use ($publicAdministration) {
                return $publicAdministration->ipa_code === $notification->publicAdministration->ipa_code;
            }
        );
    }

    /**
     * Test user status changed event handler.
     */
    public function testUserStatusChanged(): void
    {
        $this->expectLogMessage('notice', [
            'User ' . $this->user->uuid . ' status changed from "' . UserStatus::getDescription(UserStatus::PENDING) . '" to "' . $this->user->status->description . '"',
            [
                'event' => EventType::USER_STATUS_CHANGED,
                'user' => $this->user->uuid,
            ],
        ]);
        event(new UserStatusChanged($this->user, UserStatus::PENDING));
    }

    /**
     * Test user website access changed event handler.
     */
    public function testWebsiteAccessChanged(): void
    {
        $publicAdministration = factory(PublicAdministration::class)->create();
        $website = factory(Website::class)->create([
            'public_administration_id' => $publicAdministration->id,
        ]);
        $accessType = WebsiteAccessType::getInstance(WebsiteAccessType::VIEW);

        $this->expectLogMessage(
            'notice',
            [
                'Granted "' . $accessType->description . '" access for website ' . $website->info . ' to user ' . $this->user->uuid,
                [
                    'event' => EventType::USER_WEBSITE_ACCESS_CHANGED,
                    'user' => $this->user->uuid,
                    'pa' => $website->publicAdministration->ipa_code,
                    'website' => $website->id,
                ],
            ]
        );

        event(new UserWebsiteAccessChanged($this->user, $website, $accessType));
    }

    /**
     * Test user login event handler.
     */
    public function testLogin(): void
    {
        $this->expectLogMessage(
            'info',
            [
                'User ' . $this->user->uuid . ' logged in.',
                [
                    'user' => $this->user->uuid,
                    'event' => EventType::USER_LOGIN,
                ],
            ]
        );

        event(new UserLogin($this->user));
    }

    /**
     * Test user logout event handler.
     */
    public function testLogout(): void
    {
        $this->expectLogMessage(
            'info',
            [
                'User ' . $this->user->uuid . ' logged out.',
                [
                    'user' => $this->user->uuid,
                    'event' => EventType::USER_LOGOUT,
                ],
            ]
        );

        event(new UserLogout($this->user));
    }

    /**
     * Test user suspended event handler.
     */
    public function testSuspended(): void
    {
        $this->expectLogMessage(
            'info',
            [
                'User ' . $this->user->uuid . ' suspended.',
                [
                    'user' => $this->user->uuid,
                    'event' => EventType::USER_SUSPENDED,
                ],
            ]
        );

        event(new UserSuspended($this->user));
    }

    /**
     * Test user reactivated event handler.
     */
    public function testReactivated(): void
    {
        $this->expectLogMessage(
            'info',
            [
                'User ' . $this->user->uuid . ' reactivated.',
                [
                    'user' => $this->user->uuid,
                    'event' => EventType::USER_REACTIVATED,
                ],
            ]
        );

        event(new UserReactivated($this->user));
    }

    /**
     * Test user deleted event handler.
     */
    public function testDeleted(): void
    {
        $this->expectLogMessage(
            'notice',
            [
                'User ' . $this->user->uuid . ' deleted.',
                [
                    'event' => EventType::USER_DELETED,
                    'user' => $this->user->uuid,
                ],
            ]
        );

        event(new UserDeleted($this->user));
    }

    /**
     * Test user restored event handler.
     */
    public function testRestored(): void
    {
        $this->expectLogMessage(
            'notice',
            [
                'User ' . $this->user->uuid . ' restored.',
                [
                    'event' => EventType::USER_RESTORED,
                    'user' => $this->user->uuid,
                ],
            ]
        );

        event(new UserRestored($this->user));
    }
}
