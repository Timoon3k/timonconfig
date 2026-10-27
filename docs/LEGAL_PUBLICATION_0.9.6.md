# Publikacja regulaminów — Core 0.9.6 / motyw 0.11.2

**Aktualizacja:** właściciel podał adres i e-mail po wydaniu 0.9.6. Bieżąca wersja Core 0.9.7 uzupełnia je również w istniejących opublikowanych dokumentach; opis braku danych poniżej dotyczy historycznego stanu 0.9.6. Zobacz [LEGAL_CONTACT_UPDATE_0.9.7.md](LEGAL_CONTACT_UPDATE_0.9.7.md).

Kontynuacja `main` z `c08026b`; kod `2109f39` wysłano fast-forward na GitHub main i sprawdzono zdalny ref. Właściciel jawnie polecił zastąpić dotychczasowe treści, opublikować regulaminy bez oznaczeń Draft, połączyć zbędne dokumenty i wypchnąć zmiany na GitHub. Zapis statusu `publish` w lokalnej bazie został wykonany i sprawdzony; samo wysłanie kodu nie potwierdza instalacji na Hostingerze.

## Co zostało opublikowane lokalnie

- **Terms of Service** — oferta, ceny, wymagania Discord/hardware, płatności, dostarczenie, odnowienia, anulowanie i licencja Lifetime w sekcji `#lifetime-license`.
- **Privacy Policy** — administrator danych, cele/podstawy, Discord OAuth i role, rozliczenia, dostawcy, retencja, prawa użytkownika oraz cookies w sekcji `#cookies`.
- **Refund & Cancellation Policy** — odstąpienie konsumenckie, wzór oświadczenia, niezgodność, reklamacje, zwroty oraz rozróżnienie przerwania checkoutu i anulowania odnowień.

Treści są angielskie i edytowalne w natywnych blokach Gutenberg. Nie zawierają Draft ani niewypełnionych makr. Zachowano identyfikatory kanonicznych stron. Cookie Policy i Lifetime License Terms są przenoszone do kosza dopiero po udanej publikacji trzech dokumentów; ich stare adresy GET/HEAD zwracają 301 do odpowiednich sekcji. Stopka i odnośniki VIP prowadzą do kanonicznych stron. Odświeżono rzeczywisty screenshot Home w formacie 1200×900.

## Dane i granice treści

Wpisano dane dostarczone przez właściciela: **TimonConfigs — Tomasz Majewski, działalność nierejestrowana w Polsce**, końcowe kwoty **€29.99 / miesiąc i €80 jednorazowo**. Nie wymyślono adresu, e-maila, NIP, zwolnienia VAT ani danych rejestrowych. Właściciel odmówił podania adresu; działający bezpośredni e-mail pozostaje niepodany. Obecny odnośnik prowadzi do istniejącej strony Contact.

Sprzedaż cyfrowa nie zwalnia z obowiązków ujawnienia danych sprzedawcy i bezpośredniego kontaktu. Brak adresu/e-maila pozostaje istotnym brakiem informacyjnym, niezależnie od opublikowanego statusu. Publikacja nie jest poświadczeniem pełnej zgodności prawnej ani kwalifikacji działalności do wszystkich rynków/podatków. Dokumenty zachowują ustawowe prawa konsumenckie; obecny checkout nie wdraża odrębnej zgody i trwałego potwierdzenia utraty prawa odstąpienia po natychmiastowym dostarczeniu treści cyfrowej.

Teksty napisano na podstawie istniejącego kodu i dostarczonych faktów. Nie kopiowano coldz.pro. Aktualnego odczytu zewnętrznych źródeł prawnych nie udało się wykonać z tego środowiska. Szczegóły otwartych obowiązków: [LEGAL_PUBLICATION_CHECKLIST_2026.md](LEGAL_PUBLICATION_CHECKLIST_2026.md).

## Instalacja i podmiana istniejących treści

1. Zrób standardową kopię bazy oraz aktualnej wtyczki i motywu.
2. Wgraj `artifacts/timonconfigs-core-0.9.6.zip` i `artifacts/timonconfigs-theme-0.11.2.zip`, zastępując obecne wersje. Nie usuwaj wtyczki ani danych klientów.
3. Otwórz Kokpit jako administrator WordPressa. Jednorazowy `admin_init` wykona zatwierdzoną przez właściciela podmianę/publikację; anonimowe odwiedziny nie zapisują stron.
4. Sprawdź trzy publiczne regulaminy, stopkę, przekierowania i cache. Uzupełnienie rzeczywistych danych i ponowna publikacja jest w **Ustawienia → Legal documents**. Zmiana tam zastępuje treści trzech stron, więc zachowaj ich ewentualne późniejsze autorskie edycje przed ponowną publikacją.

Moduł najpierw zapisuje prywatne, nieautoloadowane archiwum `_tc_legal_archive_*` z treściami/statusami/metadanymi i dotkniętymi linkami menu. Następnie publikuje dokumenty, aktualizuje stopkę i dopiero potem usuwa aktywne duplikaty. Znacznik `timonconfigs_owner_legal_release` jest ustawiany dopiero po sukcesie. To wieloetapowa operacja CMS z archiwum i ponowieniem, nie atomowa transakcja bazy. Formularz administracyjny wymaga uprawnień, nonce i HTTPS poza localhost.

Nie zmieniano ustawień Stripe/PayPal, sekretów, subskrypcji, aktualnych praw klientów ani Discorda. Nie zaznaczano fikcyjnego ukończenia testów Live lub przeglądu prawnego. Do wdrożenia nie potrzeba nowych sekretów. Połączenie Hostinger MCP nie jest w tej sesji dostępne, więc zdalna instalacja i cache produkcyjny pozostają niezweryfikowane.

## Sprawdzenie wydania

Zaliczono 283 asercje PHP, 139 asercji przeglądarkowych w 12 widokach i 2 przekierowania 301 oraz 97 kontroli świeżej instalacji ZIP. Lint 111 plików PHP, JavaScript i whitespace check jest poprawny. Szczegółowy podział jest na początku `PROGRESS.md`. Testy publikacji uruchamiają się w transakcji na prawdziwym WordPressie i wycofują syntetyczne dane; test przeglądarkowy odczytuje działające strony. Manifest `artifacts/legal-096-release-manifest.json` zawiera sumy SHA256 i liczbę plików paczek. Logi `artifacts/legal-096-*.log` oraz raport `artifacts/legal-096-browser.json` pozostają poza Git.

```bash
docker compose -f docker-compose.local.yml run --rm -T cli wp eval-file - < tests/legal-publication-integration.php
node tests/legal-browser.cjs
bash tests/release-installation.sh
```

Test instalacji używa osobnego, odciętego od Internetu WordPressa 6.8.3 / PHP 8.2 i własnej bazy; nie korzysta z lokalnej ani produkcyjnej bazy klientów. Testy nie wykonują prawdziwej płatności ani anulowania subskrypcji.
