<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { api, isAuthenticated } from '../api/client'

const route = useRoute()
const { t } = useI18n()

const me = ref(null)
const loading = ref(false)
const error = ref('')
const pendingHint = ref(false)
const withdrawalWaiverAccepted = ref(false)

const otherInterval = computed(() =>
  me.value?.premiumInterval === 'yearly' ? 'monthly' : 'yearly',
)

async function loadMe() {
  if (!isAuthenticated()) return
  try {
    me.value = await api.me()
  } catch (e) {
    error.value = e.message
  }
}

// Mollie redirects the browser back here right after checkout, but the
// webhook that actually confirms the payment can lag slightly behind that
// redirect – so this just re-fetches once and shows a "may take a moment"
// hint rather than polling in a loop.
async function handleReturnFromMollie() {
  if (route.query.premium === undefined) return
  pendingHint.value = true
  await loadMe()
}

async function startCheckout(interval) {
  loading.value = true
  error.value = ''
  try {
    const result = await api.startPremiumCheckout(interval, withdrawalWaiverAccepted.value)
    // Deliberately a full page redirect, not router.push – the destination
    // is Mollie's own hosted checkout, not a route in this SPA.
    window.location.href = result.checkoutUrl
  } catch (e) {
    error.value = e.message
    loading.value = false
  }
}

async function cancel() {
  loading.value = true
  error.value = ''
  try {
    me.value = await api.cancelPremium()
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

async function switchInterval(interval) {
  loading.value = true
  error.value = ''
  try {
    me.value = await api.changePremiumInterval(interval)
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

// A canceled Mollie subscription can't be un-canceled or updated (switching
// interval on it 422s) – this creates a fresh one against the still-valid
// mandate instead, billed starting when the current grace period ends
// rather than immediately. See MollieService::reactivateSubscription().
async function reactivate() {
  loading.value = true
  error.value = ''
  try {
    me.value = await api.reactivatePremium()
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  handleReturnFromMollie()
  loadMe()
})
</script>

<template>
  <div class="container">
    <h2>{{ t('premium.heading') }}</h2>

    <div v-if="isAuthenticated()" class="premium-status">
      <p v-if="pendingHint" class="hint">{{ t('premium.pendingHint') }}</p>
      <p v-if="error" style="color: crimson">{{ error }}</p>

      <template v-if="me?.premium">
        <p>
          {{
            me.premiumStatus === 'canceled'
              ? t('premium.cancelledUntil', { date: me.premiumRenewsAt })
              : t('premium.activeUntil', { date: me.premiumRenewsAt })
          }}
        </p>
        <p v-if="me.premiumPendingInterval" class="hint">
          {{
            t('premium.pendingSwitchHint', {
              date: me.premiumRenewsAt,
              interval: t(`premium.interval.${me.premiumPendingInterval}`),
            })
          }}
        </p>

        <!-- Canceled: the Mollie subscription itself is gone, so switching
             interval isn't possible anymore – only reactivating is. -->
        <p v-if="me.premiumStatus === 'canceled'" class="premium-actions">
          <button type="button" :disabled="loading" @click="reactivate">
            {{ t('premium.reactivateButton') }}
          </button>
        </p>
        <p v-else class="premium-actions">
          <button
            v-if="!me.premiumPendingInterval"
            type="button"
            :disabled="loading"
            @click="switchInterval(otherInterval)"
          >
            {{ t(`premium.switchTo.${otherInterval}`) }}
          </button>
          <button type="button" class="danger-btn" :disabled="loading" @click="cancel">
            {{ t('premium.cancelButton') }}
          </button>
        </p>
      </template>

      <template v-else>
        <!-- Required before purchase: without this express consent to
             immediate performance, a consumer keeps a 14-day withdrawal
             right even after using premium (§ 356 Abs. 4 BGB) – see
             InternalApiController::premiumCheckout(), which rejects the
             request server-side if this wasn't sent as true. -->
        <label class="withdrawal-waiver">
          <input
            type="checkbox"
            v-model="withdrawalWaiverAccepted"
            :aria-label="t('premium.withdrawalWaiverLabel')"
          />
          <span>
            {{ t('premium.withdrawalWaiverLabel') }}
            <RouterLink to="/legal/agb">{{ t('premium.withdrawalWaiverLink') }}</RouterLink>
          </span>
        </label>

        <p class="premium-actions">
          <button
            type="button"
            :disabled="loading || !withdrawalWaiverAccepted"
            @click="startCheckout('monthly')"
          >
            {{ t('premium.becomeMonthly') }}
          </button>
          <button
            type="button"
            :disabled="loading || !withdrawalWaiverAccepted"
            @click="startCheckout('yearly')"
          >
            {{ t('premium.becomeYearly') }}
          </button>
        </p>
      </template>
    </div>

    <div class="premium-info">
      <h3>{{ t('premium.infoHeading') }}</h3>
      <p>{{ t('premium.infoText') }}</p>

      <RouterLink v-if="!isAuthenticated()" :to="{ name: 'login', query: { redirect: '/premium' } }">
        {{ t('premium.loginToBuy') }}
      </RouterLink>
    </div>
  </div>
</template>

<style scoped>
.withdrawal-waiver {
  display: flex;
  align-items: flex-start;
  gap: 0.5rem;
  margin-top: 1rem;
  font-size: 0.9rem;
}

.withdrawal-waiver input {
  margin-top: 0.2rem;
}
</style>
