<script setup>
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
</script>

<template>
  <div class="container legal">
    <h2>{{ t('footer.impressum') }}</h2>
    <p class="hint">
      Rechtlich verbindliche Angaben – dieser Text ist bislang nur ein Platzhalter und muss vor
      Live-Betrieb der Seite durch die tatsächlichen Angaben ersetzt werden.
    </p>

    <h3>Angaben gemäß § 5 TMG</h3>
    <p>
      [PLATZHALTER: Name/Firma]<br />
      [PLATZHALTER: Straße und Hausnummer]<br />
      [PLATZHALTER: PLZ und Ort]<br />
      [PLATZHALTER: Land]
    </p>

    <h3>Kontakt</h3>
    <p>
      Telefon: [PLATZHALTER: Telefonnummer]<br />
      E-Mail: [PLATZHALTER: E-Mail-Adresse]
    </p>

    <h3>Umsatzsteuer-ID</h3>
    <p>[PLATZHALTER: Umsatzsteuer-Identifikationsnummer gemäß § 27 a UStG, falls vorhanden]</p>

    <h3>Verantwortlich für den Inhalt nach § 18 Abs. 2 MStV</h3>
    <p>
      [PLATZHALTER: Name]<br />
      [PLATZHALTER: Anschrift wie oben]
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
