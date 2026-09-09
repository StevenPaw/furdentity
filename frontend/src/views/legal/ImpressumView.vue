<script setup>
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
</script>

<template>
  <div class="container legal">
    <h2>{{ t('footer.impressum') }}</h2>

    <h3>Angaben gemäß § 5 TMG</h3>
    <p>
      Steffen Kahl<br />
      Ohlendamm 2<br />
      22926 Ahrensburg<br />
      Deutschland
    </p>

    <h3>Kontakt</h3>
    <p>
      E-Mail: mail@furdentity.com
    </p>

    <h3>Verantwortlich für den Inhalt nach § 18 Abs. 2 MStV</h3>
    <p>
      Steffen Kahl<br />
      Ohlendamm 2<br />
      22926 Ahrensburg<br />
      Deutschland
    </p>

    <h3>Streitschlichtung</h3>
    <p>
      Die Europäische Kommission stellt eine Plattform zur Online-Streitbeilegung (OS) bereit:
      <a href="https://ec.europa.eu/consumers/odr/" target="_blank" rel="noopener"
        >https://ec.europa.eu/consumers/odr/</a
      >. Wir sind nicht bereit oder verpflichtet, an Streitbeilegungsverfahren vor einer
      Verbraucherschlichtungsstelle teilzunehmen.
    </p>
  </div>
</template>

<style scoped lang="scss" src="./legal.scss"></style>
