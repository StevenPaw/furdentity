<?php

namespace App\Api\Support;

use App\Model\User;
use SilverStripe\Control\Director;
use SilverStripe\Control\Email\Email;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injectable;

/**
 * Transactional emails around the premium subscription lifecycle – new
 * subscription, a successful renewal, and a requested interval switch.
 * Deliberately not sent for cancel/reactivate (not asked for, and a
 * canceled subscription already just keeps working silently until its
 * grace period runs out – see {@see \App\Model\User::isPremium()}).
 *
 * Mirrors {@see \App\Api\AuthController::sendLoginEmail()}: same "From"
 * address reasoning (SPF/DKIM alignment with the authenticated MAILER_DSN
 * mailbox), same _t()-translatable strings.
 */
class PremiumMailer
{
    use Injectable;

    public function sendSubscribed(User $user, string $interval): void
    {
        $this->send(
            $user,
            _t(self::class . '.SUBSCRIBED_SUBJECT', 'Welcome to Furdentity Premium!'),
            sprintf(
                '<p>%s</p><p>%s</p><p><a href="%s">%s</a></p>',
                _t(self::class . '.SUBSCRIBED_INTRO', 'Thank you for supporting Furdentity!'),
                $this->intervalSentence(
                    $interval,
                    _t(self::class . '.SUBSCRIBED_MONTHLY', 'Your premium membership is now active, billed monthly.'),
                    _t(self::class . '.SUBSCRIBED_YEARLY', 'Your premium membership is now active, billed yearly.')
                ),
                htmlspecialchars($this->premiumUrl(), ENT_QUOTES),
                _t(self::class . '.MANAGE_CTA', 'Manage your premium membership')
            )
        );
    }

    public function sendRenewed(User $user, string $renewsAt): void
    {
        $this->send(
            $user,
            _t(self::class . '.RENEWED_SUBJECT', 'Your Furdentity Premium membership was renewed'),
            sprintf(
                '<p>%s</p><p><a href="%s">%s</a></p>',
                _t(
                    self::class . '.RENEWED_INTRO',
                    'Your premium membership was renewed and stays active until {date}.',
                    ['date' => $this->formatDate($renewsAt)]
                ),
                htmlspecialchars($this->premiumUrl(), ENT_QUOTES),
                _t(self::class . '.MANAGE_CTA', 'Manage your premium membership')
            )
        );
    }

    public function sendCancelled(User $user, string $renewsAt): void
    {
        $this->send(
            $user,
            _t(self::class . '.CANCELLED_SUBJECT', 'Your Furdentity Premium membership was cancelled'),
            sprintf(
                '<p>%s</p><p><a href="%s">%s</a></p>',
                _t(
                    self::class . '.CANCELLED_INTRO',
                    'Your premium membership was cancelled and stays active until {date}. You can reactivate it any time before then.',
                    ['date' => $this->formatDate($renewsAt)]
                ),
                htmlspecialchars($this->premiumUrl(), ENT_QUOTES),
                _t(self::class . '.MANAGE_CTA', 'Manage your premium membership')
            )
        );
    }

    public function sendIntervalChanged(User $user, string $newInterval, string $effectiveDate): void
    {
        $this->send(
            $user,
            _t(self::class . '.INTERVAL_CHANGED_SUBJECT', 'Your Furdentity Premium billing is changing'),
            sprintf(
                '<p>%s</p><p><a href="%s">%s</a></p>',
                $this->intervalSentence(
                    $newInterval,
                    _t(
                        self::class . '.INTERVAL_CHANGED_MONTHLY',
                        'Starting {date}, your premium membership switches to monthly billing.',
                        ['date' => $this->formatDate($effectiveDate)]
                    ),
                    _t(
                        self::class . '.INTERVAL_CHANGED_YEARLY',
                        'Starting {date}, your premium membership switches to yearly billing.',
                        ['date' => $this->formatDate($effectiveDate)]
                    )
                ),
                htmlspecialchars($this->premiumUrl(), ENT_QUOTES),
                _t(self::class . '.MANAGE_CTA', 'Manage your premium membership')
            )
        );
    }

    private function intervalSentence(string $interval, string $monthly, string $yearly): string
    {
        return $interval === User::PREMIUM_INTERVAL_YEARLY ? $yearly : $monthly;
    }

    private function formatDate(string $datetime): string
    {
        return date('d.m.Y', strtotime($datetime));
    }

    private function premiumUrl(): string
    {
        return Director::absoluteURL('/premium');
    }

    private function send(User $user, string $subject, string $body): void
    {
        Email::create()
            ->setFrom(
                (string) Environment::getEnv('APP_SMTP_USERNAME'),
                _t(self::class . '.EMAIL_FROM_NAME', 'Furdentity')
            )
            ->setTo((string) $user->Email)
            ->setSubject($subject)
            ->setBody($body)
            ->send();
    }
}
