<script setup>
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
</script>

<template>
  <div class="container legal">
    <h2>{{ t('footer.agb') }}</h2>

    <h3>1. Geltungsbereich</h3>
    <p>
      Diese Allgemeinen Geschäftsbedingungen (AGB) gelten für den Abschluss einer kostenpflichtigen
      Premium-Mitgliedschaft auf der Plattform Furdentity, betrieben von Steffen Kahl, Ohlendamm 2,
      22926 Ahrensburg (siehe Impressum). Die Nutzung von Furdentity ohne Premium-Mitgliedschaft
      bleibt kostenlos und ist von diesen AGB nicht betroffen.
    </p>

    <h3>2. Leistungsbeschreibung</h3>
    <p>
      Die Premium-Mitgliedschaft ist derzeit in erster Linie eine freiwillige Unterstützung des
      Projekts. Darüber hinausgehende, exklusive Premium-Funktionen werden ergänzt, sobald es sie
      gibt; der Funktionsumfang kann sich im Rahmen der Weiterentwicklung ändern.
    </p>

    <h3>3. Vertragsschluss</h3>
    <p>
      Der Vertrag über die Premium-Mitgliedschaft kommt zustande, indem Sie auf der Seite
      "/premium" ein Abrechnungsintervall (monatlich oder jährlich) auswählen, dem sofortigen
      Beginn der Leistung samt dem damit verbundenen Verlust bzw. der Einschränkung Ihres
      Widerrufsrechts ausdrücklich zustimmen (siehe Ziffer 6) und den Zahlungsvorgang über unseren
      Zahlungsdienstleister Mollie erfolgreich abschließen. Nach erfolgreicher Zahlung erhalten Sie
      eine Bestätigung per E-Mail.
    </p>

    <h3>4. Preise und Zahlung</h3>
    <p>
      Die Premium-Mitgliedschaft kostet aktuell 1,00 € pro Monat oder 10,00 € pro Jahr (jeweils
      inkl. etwaiger gesetzlicher Umsatzsteuer). Die Zahlung erfolgt wiederkehrend über unseren
      Zahlungsdienstleister Mollie B.V. und verlängert sich automatisch um das gewählte Intervall,
      bis die Mitgliedschaft gekündigt wird (siehe Ziffer 5). Preisänderungen werden vor ihrem
      Inkrafttreten rechtzeitig angekündigt und gelten erst für danach beginnende
      Abrechnungszeiträume.
    </p>

    <h3>5. Vertragslaufzeit, Kündigung und Intervallwechsel</h3>
    <p>
      Die Premium-Mitgliedschaft läuft auf unbestimmte Zeit und kann jederzeit ohne Angabe von
      Gründen mit Wirkung zum Ende des bereits bezahlten Abrechnungszeitraums gekündigt werden. Die
      Kündigung erfolgt über den entsprechenden Button in Ihrem Konto unter "/premium" und wird
      Ihnen per E-Mail mit Datum bestätigt. Bis zum Ende des bezahlten Zeitraums bleibt die
      Mitgliedschaft aktiv nutzbar; eine anteilige Rückerstattung für den bereits bezahlten,
      ungenutzten Rest des Zeitraums erfolgt nicht. Ein Wechsel zwischen monatlicher und jährlicher
      Abrechnung ist jederzeit möglich und wird zum nächsten Abrechnungstermin wirksam.
    </p>

    <h3>6. Widerrufsrecht</h3>
    <p>
      Verbrauchern steht grundsätzlich ein 14-tägiges Widerrufsrecht zu. Da die Premium-Leistung
      auf Ihren ausdrücklichen Wunsch sofort mit Vertragsschluss beginnt, erklären Sie beim Kauf
      gesondert Ihre Zustimmung zum sofortigen Beginn und Ihre Kenntnis, dass Ihr Widerrufsrecht
      dadurch vorzeitig erlischt, sobald die Leistung vollständig erbracht wurde, bzw. Sie im Falle
      eines Widerrufs vor vollständiger Erbringung einen anteiligen Wertersatz für die bereits
      erbrachte Leistung schulden.
    </p>
    <p>
      <strong>Widerrufsbelehrung:</strong> Sie haben das Recht, binnen vierzehn Tagen ohne Angabe
      von Gründen diesen Vertrag zu widerrufen. Die Widerrufsfrist beträgt vierzehn Tage ab dem Tag
      des Vertragsschlusses. Um Ihr Widerrufsrecht auszuüben, müssen Sie uns (Steffen Kahl, siehe
      Impressum, E-Mail: mail@furdentity.com) mittels einer eindeutigen Erklärung (z. B. per
      E-Mail) über Ihren Entschluss, diesen Vertrag zu widerrufen, informieren. Zur Wahrung der
      Widerrufsfrist reicht es aus, dass Sie die Mitteilung über die Ausübung des Widerrufsrechts
      vor Ablauf der Widerrufsfrist absenden.
    </p>
    <p>
      Haben Sie verlangt, dass die Leistung während der Widerrufsfrist beginnen soll, so haben Sie
      uns einen angemessenen Betrag zu zahlen, der dem Anteil der bis zu dem Zeitpunkt, zu dem Sie
      uns von der Ausübung des Widerrufsrechts hinsichtlich dieses Vertrags unterrichten, bereits
      erbrachten Leistung im Vergleich zum Gesamtumfang der im Vertrag vorgesehenen Leistungen
      entspricht.
    </p>

    <h3>7. Haftung</h3>
    <p>
      Wir haften unbeschränkt für Vorsatz und grobe Fahrlässigkeit sowie nach Maßgabe des
      Produkthaftungsgesetzes. Für leichte Fahrlässigkeit haften wir nur bei Verletzung einer
      wesentlichen Vertragspflicht (Kardinalpflicht) und begrenzt auf den vorhersehbaren,
      vertragstypischen Schaden.
    </p>

    <h3>8. Änderung dieser AGB</h3>
    <p>
      Wir behalten uns vor, diese AGB anzupassen, damit sie stets den aktuellen rechtlichen
      Anforderungen entsprechen. Über wesentliche Änderungen informieren wir bestehende
      Premium-Mitglieder rechtzeitig vor deren Inkrafttreten.
    </p>

    <h3>9. Kontakt</h3>
    <p>Bei Fragen zur Premium-Mitgliedschaft erreichen Sie uns unter mail@furdentity.com.</p>
  </div>
</template>

<style scoped lang="scss" src="./legal.scss"></style>
