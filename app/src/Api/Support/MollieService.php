<?php

namespace App\Api\Support;

use App\Model\User;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Customer;
use Mollie\Api\Resources\Payment;
use Mollie\Api\Resources\Subscription;
use RuntimeException;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injectable;

/**
 * Thin wrapper around the Mollie PHP SDK for the premium subscription.
 * Prices are kept in cents (not floats) to avoid rounding drift, and are
 * configurable via app/_config/api.yml rather than hardcoded, since they're
 * a business decision that may change without a code deploy.
 */
class MollieService
{
    use Injectable;
    use Configurable;

    private static array $prices = [
        User::PREMIUM_INTERVAL_MONTHLY => 100,
        User::PREMIUM_INTERVAL_YEARLY => 1000,
    ];

    private static array $intervals = [
        User::PREMIUM_INTERVAL_MONTHLY => '1 month',
        User::PREMIUM_INTERVAL_YEARLY => '12 months',
    ];

    private ?MollieApiClient $client = null;

    private function client(): MollieApiClient
    {
        if ($this->client instanceof MollieApiClient) {
            return $this->client;
        }

        $apiKey = Environment::getEnv('MOLLIE_API_KEY');

        if (!is_string($apiKey) || $apiKey === '') {
            throw new RuntimeException('MOLLIE_API_KEY environment variable is not set');
        }

        $client = new MollieApiClient();
        $client->setApiKey($apiKey);

        return $this->client = $client;
    }

    /**
     * Creates the Mollie customer on first use and persists its ID on the
     * user, or simply returns the already-stored one on every later call –
     * a user only ever needs one Mollie customer, reused across
     * subscribe/cancel/resubscribe.
     */
    public function createOrGetCustomer(User $user): Customer
    {
        if ((string) $user->MollieCustomerId !== '') {
            return $this->client()->customers->get((string) $user->MollieCustomerId);
        }

        $customer = $this->client()->customers->create([
            'name' => (string) $user->Title,
            'email' => (string) $user->Email,
            'metadata' => ['userId' => (string) $user->ID],
        ]);

        $user->MollieCustomerId = $customer->id;
        $user->write();

        return $customer;
    }

    /**
     * The first payment of a new subscription. Mollie requires this
     * "sequenceType: first" payment to establish a mandate before a
     * recurring subscription can be created for the customer – see
     * {@see self::createSubscription()}, which is only ever called once
     * this payment's webhook reports it as paid.
     */
    public function createFirstPayment(User $user, string $interval): Payment
    {
        $customer = $this->createOrGetCustomer($user);

        return $this->client()->payments->create([
            'amount' => [
                'currency' => 'EUR',
                'value' => $this->priceValue($interval),
            ],
            'customerId' => $customer->id,
            'sequenceType' => 'first',
            'description' => $this->description($interval),
            'redirectUrl' => Director::absoluteURL('/premium') . '?premium=pending',
            'webhookUrl' => Director::absoluteURL('/api/v1/billing/webhook'),
            'metadata' => [
                'userId' => (string) $user->ID,
                'interval' => $interval,
                // Audit trail for the § 356 Abs. 4 BGB immediate-performance
                // consent InternalApiController::premiumCheckout() already
                // required before creating this payment – the payment's own
                // createdAt timestamp (visible in the Mollie dashboard) is
                // the "when" for this.
                'withdrawalWaiverAccepted' => true,
            ],
        ]);
    }

    /**
     * Called once the first payment above is confirmed paid (see
     * {@see \App\Api\MollieWebhookController}). No "times" limit is set –
     * it keeps renewing until explicitly canceled. $startDate lets
     * {@see self::reactivateSubscription()} defer the first charge of a
     * reactivated subscription to when the still-running grace period
     * actually ends, instead of billing immediately.
     */
    public function createSubscription(User $user, string $interval, ?string $startDate = null): Subscription
    {
        $customer = $this->client()->customers->get((string) $user->MollieCustomerId);

        $data = [
            'amount' => [
                'currency' => 'EUR',
                'value' => $this->priceValue($interval),
            ],
            'interval' => $this->mollieInterval($interval),
            'description' => $this->description($interval),
            'webhookUrl' => Director::absoluteURL('/api/v1/billing/webhook'),
        ];

        if ($startDate !== null) {
            $data['startDate'] = $startDate;
        }

        return $this->client()->subscriptions->createFor($customer, $data);
    }

    /**
     * A canceled Mollie subscription can never be un-canceled (see Mollie's
     * "update subscription" docs) – but the customer's payment mandate
     * from the original subscription is still valid, so this doesn't need
     * a new checkout/first payment at all. It creates a fresh subscription
     * against that existing mandate, with its first charge deferred to
     * $renewsAt (the date the already-paid-for grace period runs out) so
     * the user isn't charged twice for the same period.
     */
    public function reactivateSubscription(User $user, string $renewsAt): Subscription
    {
        $startDate = date('Y-m-d', max(strtotime($renewsAt), strtotime('today')));

        return $this->createSubscription($user, (string) $user->PremiumInterval, $startDate);
    }

    /**
     * Switches amount/interval on the existing subscription in place, rather
     * than canceling and recreating it – Mollie applies the change starting
     * with the next scheduled payment, so the currently paid-for period
     * always finishes out at the old interval first without us having to
     * track or schedule that ourselves.
     */
    public function updateSubscriptionInterval(User $user, string $newInterval): void
    {
        $this->client()->subscriptions->update(
            (string) $user->MollieCustomerId,
            (string) $user->MollieSubscriptionId,
            [
                'amount' => [
                    'currency' => 'EUR',
                    'value' => $this->priceValue($newInterval),
                ],
                'interval' => $this->mollieInterval($newInterval),
            ]
        );
    }

    public function cancelSubscription(User $user): void
    {
        $customer = $this->client()->customers->get((string) $user->MollieCustomerId);
        $this->client()->subscriptions->cancelFor($customer, (string) $user->MollieSubscriptionId);
    }

    /**
     * Fetches the payment fresh from Mollie by ID rather than trusting
     * anything in the webhook request body – the webhook only ever carries
     * an ID, precisely so that the actual status has to come from here.
     */
    public function getPayment(string $paymentId): Payment
    {
        return $this->client()->payments->get($paymentId);
    }

    private function priceValue(string $interval): string
    {
        return number_format($this->priceCents($interval) / 100, 2, '.', '');
    }

    private function priceCents(string $interval): int
    {
        $prices = (array) $this->config()->get('prices');

        if (!array_key_exists($interval, $prices)) {
            throw new RuntimeException("No price configured for interval '{$interval}'");
        }

        return (int) $prices[$interval];
    }

    private function mollieInterval(string $interval): string
    {
        $intervals = (array) $this->config()->get('intervals');

        if (!array_key_exists($interval, $intervals)) {
            throw new RuntimeException("No Mollie interval configured for interval '{$interval}'");
        }

        return (string) $intervals[$interval];
    }

    private function description(string $interval): string
    {
        return $interval === User::PREMIUM_INTERVAL_YEARLY
            ? 'Furdentity Premium (jährlich)'
            : 'Furdentity Premium (monatlich)';
    }
}
