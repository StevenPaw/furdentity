<?php

namespace App\Api;

use App\Api\Support\MollieService;
use App\Api\Support\PremiumMailer;
use App\Model\User;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Resources\Payment;
use SilverStripe\Control\HTTPResponse;

/**
 * Receives Mollie's payment webhook. Mollie POSTs here as
 * application/x-www-form-urlencoded with a single "id" field – never JSON,
 * unlike every other endpoint in this app – and never carries our session
 * cookie, since it's Mollie's own server calling us, not the frontend.
 *
 * The body is never trusted beyond that ID: the actual payment status is
 * always re-fetched from Mollie's API using our own secret key (the
 * standard, recommended way to validate a Mollie webhook), which is also
 * why this controller needs no auth of its own – a forged POST with a
 * made-up ID just fails the lookup or resolves to someone else's already
 * publicly-visible payment status.
 *
 *   POST /api/v1/billing/webhook   id=tr_xxx
 */
class MollieWebhookController extends ApiController
{
    private static array $allowed_actions = ['index'];

    public function index(): HTTPResponse
    {
        if (!$this->getRequest()->isPOST()) {
            $this->error('Method not allowed', 405);
        }

        $paymentId = (string) $this->getRequest()->postVar('id');

        if ($paymentId === '') {
            $this->error('Missing id', 400);
        }

        $mollie = MollieService::create();

        try {
            $payment = $mollie->getPayment($paymentId);
        } catch (ApiException) {
            $this->error('Unknown payment', 404);
        }

        $user = $this->findUser($payment);

        if (!$user instanceof User) {
            // Nothing we can do locally, but this isn't a malformed request –
            // acknowledge it so Mollie doesn't keep retrying.
            return $this->jsonResponse(['received' => true]);
        }

        if ($payment->sequenceType === 'first') {
            $this->handleFirstPayment($user, $payment, $mollie);
        } elseif ($payment->sequenceType === 'recurring') {
            $this->handleRecurringPayment($user, $payment);
        }

        return $this->jsonResponse(['received' => true]);
    }

    private function findUser(Payment $payment): ?User
    {
        if ((string) $payment->customerId !== '') {
            $user = User::get()->filter('MollieCustomerId', $payment->customerId)->first();

            if ($user instanceof User) {
                return $user;
            }
        }

        $metadata = $payment->metadata;
        $userId = is_object($metadata) ? ($metadata->userId ?? null) : null;

        if ($userId !== null) {
            $user = User::get()->byID((int) $userId);

            if ($user instanceof User) {
                return $user;
            }
        }

        return null;
    }

    /**
     * The first payment only ever establishes the mandate – the actual
     * subscription is created here, once it's confirmed paid, never
     * eagerly at checkout time (a payment that's abandoned or fails should
     * never leave a subscription behind).
     */
    private function handleFirstPayment(User $user, Payment $payment, MollieService $mollie): void
    {
        if (!$payment->isPaid()) {
            return;
        }

        $metadata = $payment->metadata;
        $interval = is_object($metadata) ? ($metadata->interval ?? null) : null;

        if (!in_array($interval, User::PREMIUM_INTERVALS, true)) {
            return;
        }

        $subscription = $mollie->createSubscription($user, $interval);

        $user->MollieSubscriptionId = $subscription->id;
        $user->PremiumStatus = User::PREMIUM_STATUS_ACTIVE;
        $user->PremiumInterval = $interval;
        $user->PremiumPendingInterval = null;
        $user->PremiumRenewsAt = $this->renewalDate($interval);
        $user->write();

        PremiumMailer::create()->sendSubscribed($user, $interval);
    }

    private function handleRecurringPayment(User $user, Payment $payment): void
    {
        if ($payment->isPaid()) {
            // A pending interval switch (see
            // InternalApiController::premiumInterval()) only becomes final
            // once a recurring payment actually confirms the new amount –
            // until then PremiumInterval must keep reflecting what's
            // currently being billed.
            $interval = $user->PremiumPendingInterval !== '' && $user->PremiumPendingInterval !== null
                ? $user->PremiumPendingInterval
                : $user->PremiumInterval;

            $user->PremiumStatus = User::PREMIUM_STATUS_ACTIVE;
            $user->PremiumInterval = $interval;
            $user->PremiumPendingInterval = null;
            $user->PremiumRenewsAt = $this->renewalDate($interval);
            $user->write();

            PremiumMailer::create()->sendRenewed($user, $user->PremiumRenewsAt);

            return;
        }

        if ($payment->isFailed() || $payment->isExpired()) {
            // Deliberately doesn't touch PremiumRenewsAt – access is kept
            // until the already-paid-for period actually runs out (see
            // User::isPremium()), Mollie will retry the charge itself.
            $user->PremiumStatus = User::PREMIUM_STATUS_PAST_DUE;
            $user->write();
        }
    }

    private function renewalDate(string $interval): string
    {
        $modifier = $interval === User::PREMIUM_INTERVAL_YEARLY ? '+1 year' : '+1 month';

        return date('Y-m-d H:i:s', strtotime($modifier));
    }
}
