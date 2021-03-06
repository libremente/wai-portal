<?php

namespace App\Mail;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\PublicAdministration;
use App\Models\User;
use Illuminate\Support\Facades\Lang;

/**
 * Account verification email.
 */
class AccountVerification extends UserMailable
{
    /**
     * The signed url user for account verification.
     *
     * @var string
     */
    protected $signedUrl;

    /**
     * Create a new message instance.
     *
     * @param User $recipient the user to activate
     * @param string $signedUrl ths activation signed URL
     * @param PublicAdministration|null $publicAdministration the public administration the user belongs to
     */
    public function __construct(User $recipient, string $signedUrl, ?PublicAdministration $publicAdministration = null)
    {
        parent::__construct($recipient, $publicAdministration);
        $this->signedUrl = $signedUrl;
    }

    /**
     * Build the message.
     *
     * @return AccountVerification the email
     */
    public function build(): AccountVerification
    {
        if ($this->recipient->status->is(UserStatus::INVITED) && $this->recipient->isA(UserRole::SUPER_ADMIN)) {
            $this->subject(__('Invito su :app', ['app' => config('app.name')]));
            $mailTemplate = 'mail.admin_invited_verification';
        } else {
            $this->subject(__('Account su :app', ['app' => config('app.name')]));
            $mailTemplate = 'mail.verification';
        }

        return $this->markdown($mailTemplate)->with([
            'locale' => Lang::getLocale(),
            'user' => $this->recipient,
            'publicAdministration' => $this->publicAdministration,
            'signedUrl' => $this->signedUrl,
        ]);
    }
}
