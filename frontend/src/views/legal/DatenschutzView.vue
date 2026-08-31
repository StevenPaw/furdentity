<script setup>
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
</script>

<template>
  <div class="container legal">
    <h2>{{ t('footer.privacy') }}</h2>
    <p class="hint">
      Dieser Text ist bislang nur ein Platzhalter-Gerüst und muss vor Live-Betrieb der Seite durch
      eine rechtlich geprüfte, vollständige Datenschutzerklärung ersetzt werden.
    </p>

    <h3>1. Verantwortlicher</h3>
    <p>
      [PLATZHALTER: Name/Firma]<br />
      [PLATZHALTER: Anschrift]<br />
      E-Mail: [PLATZHALTER: E-Mail-Adresse]
    </p>

    <h3>2. Allgemeines zur Datenverarbeitung</h3>
    <p>
      Wir verarbeiten personenbezogene Daten unserer Nutzer grundsätzlich nur, soweit dies zur
      Bereitstellung einer funktionsfähigen Website sowie unserer Inhalte und Leistungen
      erforderlich ist.
    </p>

    <h3>3. Ihre Rechte</h3>
    <p>
      Sie haben jederzeit das Recht auf Auskunft, Berichtigung, Löschung, Einschränkung der
      Verarbeitung, Datenübertragbarkeit sowie Widerspruch gegen die Verarbeitung Ihrer
      personenbezogenen Daten. Zudem steht Ihnen ein Beschwerderecht bei einer
      Datenschutz-Aufsichtsbehörde zu.
    </p>

    <h3>4. Server-Logfiles</h3>
    <p>
      Beim Aufruf dieser Website werden automatisch Informationen erfasst, die Ihr Browser an den
      Server übermittelt (z. B. IP-Adresse, Datum und Uhrzeit der Anfrage, Browsertyp).
      [PLATZHALTER: konkrete Speicherdauer und Rechtsgrundlage ergänzen]
    </p>

    <h3>5. Lokale Datenspeicherung im Browser</h3>
    <p>
      Nach der Anmeldung speichert diese Anwendung Zugangs- und Aktualisierungs-Tokens im
      LocalStorage Ihres Browsers, um Sie eingeloggt zu halten. Diese Daten verbleiben
      ausschließlich auf Ihrem Gerät und werden bei der Abmeldung gelöscht.
    </p>

    <h3>6. Registrierung und Nutzerkonto</h3>
    <p>
      Bei der Registrierung erheben wir Ihre E-Mail-Adresse und ein von Ihnen gewähltes Passwort
      (dieses wird ausschließlich verschlüsselt gespeichert). Die Verarbeitung erfolgt zur
      Erfüllung des Nutzungsvertrags (Art. 6 Abs. 1 lit. b DSGVO).
    </p>

    <h3>7. Kontakt</h3>
    <p>Bei Fragen zum Datenschutz erreichen Sie uns unter [PLATZHALTER: E-Mail-Adresse].</p>

    <h3>8. Änderung dieser Datenschutzerklärung</h3>
    <p>
      Wir behalten uns vor, diese Datenschutzerklärung anzupassen, damit sie stets den aktuellen
      rechtlichen Anforderungen entspricht.
    </p>
  </div>
</template>

<style scoped lang="scss" src="./legal.scss"></style>
