## Audyt SEO i przegląd Rank Math — Core1.1.11 / theme0.14.4, 2026-10-30

- Właściciel podał wynik inspekcji Home „URL jest w Google”: zgłoszenie dotyczy więc również widoczności na wybrane frazy, nie potwierdzonej globalnej blokady indeksowania. Brak niezależnego dostępu GSC/pozycji/CWV.12publicznychodczytów zatrzymał proxyCONNECT403; nie była to odpowiedź403 witryny.
- Przejrzano eksport Rank Math; globalny index i mapy są włączone. Przygotowano pełną kopię i zalecany wariant SEO-only (`docs/seo/`),14udokumentowanychzmian, bez zmiany modułów/ról/przekierowań. Import zastępuje całe przekazane sekcje; nie nadpisuje indywidualnych metadanych stron ani WordPress blog_public.
- Adapter Rank Math zachowuje jawne meta autora, następnie tc_seo, skonfigurowane globals statycznejHome i defaults motywu. Home celuje w XIM Matrix Apex Legends, Contact/#community w Cronus Zen Discord; dodano rzeczywiste treści wsparcia i linkowanie bez nowych pustych URL-i. Konta, pliki i puste kolekcje pozostają chronione/noindex.
- Audyt i instrukcja: `docs/SEO_AUDIT_2026-10-30.md`; szczegóły JSON w `docs/seo/RANK_MATH_EXPORT_REVIEW_2026-10-30.md`. Lokalny test natywnego importera Rank Math1.0.280 i integracji SEO jest w toku; końcowe wyniki uzupełnia root. Nie wykonano importu ani zmian na produkcji, nie potwierdzono pozycji ani TOP1.

---

## Spójność anulowania i ról — Core 1.1.10, 2026-10-30

- Baza main `7ea5e2b`, motyw0.14.3 bez zmian. Właściciel potwierdził natychmiastowe anulowanie w Stripe; nie mylić z wyłączeniem przyszłych odnowień i zachowaniem opłaconego okresu.
- Dodano blokadę jednoczesnych synchronizacji członka, sprawdzanie właściciela blokady przy zapisach, ponowną kontrolę nadawanego dostępu, kolejność Free przed odebraniem wygasłego VIP i unieważnienie cache po cofnięciu przez Owner/Staff. Zdarzenia statusu/dat oraz świeży odczyt Discorda kolejkują sprawdzenie; zapasowe pięć minut pozostaje.
- Profil poprzednio opłaconego członka między rolami pokazuje Membership update pending zamiast ponownej weryfikacji. Autoryzacja i download allowlists pozostają bez zmian; nie tworzono fikcyjnego Free/VIP. reCAPTCHA nadal usunięta.
- Zaliczone **353 asercje PHP**: role71, audyt4, rzeczywista blokada SQL8, profil74, OAuth127, pobrania69. **27 widoków profilu** w przeglądarce (9stanów×3szerokości), bez błędów JS i overflow; lint117PHP/static/JS/YAML/diff bez ostrzeżeń. **98 kontroli świeżej instalacji ZIP** na oddzielnym offline WordPressie6.8.3/PHP8.2. ZIPCore20plików zgodny ze źródłami, manifest `artifacts/sync1110-release-manifest.json`. Astra Medium przeglądała współbieżność i zachowanie przy błędach; rzeczywisty ArraySubs/Stripe acceptance pozostaje niepotwierdzony.
- Instrukcja: `docs/MEMBERSHIP_SYNC_1.1.10.md`. Nie zmieniono produkcyjnych płatności, ról, klientów ani konfiguracji; Hostinger nie był aktualizowany. Kod etapu `66fe347`; publikację main potwierdza końcowy Git ref. Paczka `artifacts/timonconfigs-core-1.1.10.zip`.

---

## Informacja o czasie aktywacji przy checkout — theme 0.14.3, 2026-10-30

- Dodano przy podsumowaniu płatności: “Payment verification and automatic Discord role assignment may take 1–10 minutes.” Usunięto obietnicę Instant z nagłówka checkoutu. Core pozostaje1.1.9; logika płatności i synchronizacji nie zmienia się.
- Zmiana tekstowa: kontrola składni zmienionego PHP i diff; bez zakupów lub zmian na Hostingerze. Paczka motywu `artifacts/timonconfigs-theme-0.14.3.zip`.

---

## Usunięcie reCAPTCHA — Core 1.1.9, 2026-10-30

- Na wyraźne polecenie właściciela usunięto moduł reCAPTCHA, jego inicjalizację, widget, Google siteverify i blokady klasycznego checkoutu/Store API. Brak kluczy reCAPTCHA nie blokuje już zakupu. Motyw pozostaje0.14.2.
- Zachowano niezależne sprawdzanie Discorda, uprawnienia, nonce, walidację WooCommerce i potwierdzanie płatności przez operatora. Nie zmieniano konfiguracji ani danych produkcyjnych. Zewnętrzne wtyczki CAPTCHA nie są zarządzane przez repozytorium.
- Usunięto nieaktualny test/etap CI CAPTCHA i dostosowano test świeżej instalacji. Zaliczone **134 asercje** (prezentacja24, role35, rzeczywisty Woo75), **98 kontroli świeżej instalacji ZIP**, lint zmienionego PHP/JS/static/YAML/diff. Paczka `artifacts/timonconfigs-core-1.1.9.zip`:20plików zgodnych ze źródłami, bez modułu CAPTCHA. Stan publikacji potwierdza końcowy Git ref; nie wykonano instalacji na Hostingerze.

---

## Hotfix błędu krytycznego Core 1.1.8 — 2026-10-29

- Kontynuacja najnowszego GitHub `main` (`1b8af17`, Core 1.1.7 / theme 0.14.2). Wcześniejsza lokalna praca checkoutu zachowana w `e894246` na `fix/woocommerce-checkout-en-wallets`; stary native billing w checkpoint `f60f228`. Nie cofano migracji WooCommerce/ArraySubs z 1.1.3.
- Odtworzono na rzeczywistym lokalnym WordPressie `Undefined constant TC_Woo_Bridge::REVOKED`; brakowało także `ROLE_GRANTED_AT`. Core 1.1.8 definiuje oba klucze. Osłony wyjątków ArraySubs chronią profil/anulowanie/moderację; błędy pozostają do weryfikacji, bez fałszywego potwierdzania anulowania u operatora.
- Lifetime nie ukrywa już przycisku anulowania istniejącej Monthly. Stary wpis audytu Discorda nie może odebrać dostępu z nowego zakupu. Zachowano finansową historię, reCAPTCHA i granice autoryzacji; wymagane pola bramek oraz klasy kraju/regionu nie są usuwane.
- Zaliczone lokalnie: **354 asercje PHP** (Woo75, profil56, role35, checkout28, downloads69, OAuthreturn53, standalone38), lint118 PHP/JS/YAML/static/diff bez ostrzeżeń; **64 asercje browser /12 widoków**, **98 kontroli świeżej instalacji ZIP**. Core21/theme96plików zgodnych ze źródłami, manifest `artifacts/core118-release-manifest.json`. Astra Medium bez blokera wąskiego hotfixu; API ArraySubs i operatorów symulowane, bez realnego acceptance. Workflow integracyjny dostosowano do obecnego Woo zamiast wycofanych modułów TC_Billing; stare testy zachowano dla historii.
- Kod `528c12d` wypchnięto fast-forward na GitHub main i potwierdzono zdalny ref. Instrukcja instalacji i konfiguracji: `docs/HOTFIX_CORE_1.1.8.md`; gotowy `artifacts/timonconfigs-core-1.1.8.zip`. Motyw pozostaje0.14.2. Nie wykonano zmian płatności/ról ani instalacji na produkcji; brak aktywnego dostępu Hostinger/Stripe/PayPal i produkcyjnego logu. Push nie potwierdza aktualizacji hostingu.

---

## Historyczne etapy — aktualna architektura i wersja powyżej mają pierwszeństwo

## Lifetime, PayPal pending i diagnostyka wyszukiwania — 2026-10-27

- Zdalny main miał nowe commity `cc1d6df..8c15edd`: bezpieczne odzyskiwanie brakującej referencji Stripe przez administratora, poprawione przechwycenie danych w closure i prefiksy Test/Live. Scalono bez konfliktów z kontaktem prawnym `7e1b602` w checkpoint `c56ada3`; nie cofano cudzych zmian. Wspólne wydanie **Core0.9.8 / theme0.11.3** zapobiega kolizji dwóch różnych lokalnych/zdalnych wersji0.9.7.
- Lifetime **€80 one-time** jest domyślnym Most Popular, Monthly pozostaje **€29.99/month**. Oba VIP mają wspólny, edytowalny benefit: 250 unique VIP scripts, library valued at €10,000 by TimonConfigs. Liczba i wycena pochodzą od właściciela, nie są licznikiem bazy/sprzedaży ani niezależną wyceną. Mały `inc/plans.php` centralizuje defaults; zachowano dotychczasowe autorskie pola, wspólny benefit działa także przy starych zapisanych opisach.
- Po zgłoszeniu PayPal pending dodano jawną próbę zamknięcia nieopłaconej Monthly approval przez klienta/admina. APPROVAL_PENDING/APPROVED → binding/plan/cena/pełna pusta historia → POST cancel → ponowny GET CANCELLED/EXPIRED i pusta historia. Dopiero potem lokalne zamknięcie. Błąd API, brak referencji lub ślad płatności zachowuje blokadę. Provider switch sam nie anuluje zgody. Lifetime CAPTURE bez potwierdzonego zamknięcia pozostaje do wznowienia/weryfikacji; nie dodano fikcyjnego void API ani resetu ledgeru. Status Live nie został zmieniony, żadnej rzeczywistej płatności/anulowania nie wykonywano.
- Nowe screeny PayPal pokazują właściwy endpoint produkcyjny i zgodne eventy. GET plan HTTP200 potwierdza tylko jego odczyt, nie zapłatę/delivery. Wcześniejszy Stripe destination pokazuje ucięty staging URL; konfigurację produkcji i Signing secret należy sprawdzić. Dokładny komunikat po kliknięciu Close nadal oczekuje na odpowiedź użytkownika. Brak przycisków nie wynika ze sprawdzonego diffu Stripe; pomoc: Administrator: payment setup i prywatne ustawienia Live.
- Wąski przegląd oraz implementacja płatnicza Astra Medium; **850 asercji PHP** zaliczonych: recovery53, Store143, facade87, public39, presentation49, legal61, site77, SEO22, PayPal163, Stripe156. **242 asercje przeglądarkowe /24widoki/2redirecty** (oferta79 + regulaminy163), bez błędów JS/overflow. Lint112PHP/JS/static/diff poprawny. **100 kontroli świeżej instalacji ZIP** na odrębnym offline WP6.8.3/PHP8.2; paczki Core25/theme90plików zgodne ze źródłami, manifest `artifacts/offer-098-release-manifest.json`. Realnego Live cancel APPROVAL_PENDING/APPROVED nie potwierdzono; testy używają mocków i rzeczywisty PayPal może odmówić.
- Publiczne odczyty witryny i Google blokuje proxy środowiska CONNECT403; nie jest to HTTP403 strony. Nie potwierdzono indeksowania, pozycji, Search Console ani wdrożenia Hostinger. Z kodu: sprawdzić oba URLWordPressa na timonconfigs.com, Reading/noindex, aktywną wtyczkę SEO, sitemap i dostępność publicznych kolekcji. Top1 nie jest gwarantowany. Instrukcja `docs/LIVE_WEBHOOKS_AND_SEARCH_2026-10-27.md`; zachowano kontakty w trzech opublikowanych regulaminach.

## Kontakt w opublikowanych regulaminach — 2026-10-27

- Core **0.9.7**, motyw **0.11.2** bez zmian. Właściciel uzupełnił e-mail `contact@timonconfigs.com` i adres **TimonConfigs, ul. Dąbrowica 21, 05-326 Poświetne, Poland**. Zachowano podaną pisownię miejscowości, nazwę Tomasz Majewski oraz wcześniejszą formę działalności nierejestrowanej. Nie poświadczano niezależnie adresu ani działania skrzynki.
- Nowa jednorazowa migracja `legal-v2` zastępuje dwa pola kontaktowe również w instalacji 0.9.6 z zapisanymi pustymi wartościami i ukończonym `v1`. Zachowuje inne dane administratora, ID i status publish wszystkich trzech stron oraz prywatne archiwum. Późniejsze wejścia po sukcesie nie nadpisują edycji. Brak zmian billing/OAuth, sekretów i zamówień.
- **61 asercji publikatora** zaliczonych, w tym aktualizacja v1, błąd bez znacznika sukcesu i idempotencja. **161 asercji przeglądarkowych**, 12 widoków/2przekierowania: rzeczywisty adres i link mailto na wszystkich stronach. Lint3 zmienionych PHP/JS/diff poprawny. **100 kontroli świeżej instalacji ZIP** zaliczonych na osobnym offline WP6.8.3/PHP8.2. Lokalnie dane już opublikowane; Hostinger nie był aktualizowany. Core25plików zgodnych ze źródłami; `artifacts/legal-097-release-manifest.json`, instrukcja `docs/LEGAL_CONTACT_UPDATE_0.9.7.md`.

## Opublikowane regulaminy — 2026-10-26

- Kontynuacja GitHub main `c08026b`, Core **0.9.6** / motyw **0.11.2**. Właściciel jawnie zlecił podmianę treści, status published i push. Kod `2109f39` wypchnięto fast-forward na GitHub main i potwierdzono zdalny ref. Trzy pełne angielskie dokumenty: Terms of Service, Privacy Policy, Refund & Cancellation Policy, bez Draft i niewypełnionych makr. Lokalnie opublikowano je i sprawdzono w działającym WordPressie; to nie jest potwierdzenie instalacji na Hostingerze.
- Dane: TimonConfigs, Tomasz Majewski, działalność nierejestrowana w Polsce, końcowe ceny €29.99 / €80. Właściciel odmówił adresu; e-mail nie został podany. Nie wymyślono danych. Contact jest aktualną ścieżką kontaktu; brak adresu i bezpośredniego e-maila pozostaje brakiem informacyjnym mimo publikacji. Nie zadeklarowano pełnej zgodności prawnej ani fałszywego waivera prawa odstąpienia.
- Cookie Policy i Lifetime License Terms połączono z Privacy/Terms i przekierowaniami 301 do sekcji. Zachowano kanoniczne ID stron, archiwum poprzednich treści/metadanych/menu i niezwiązane linki. Jednorazowa migracja na `admin_init` administratora publikuje istniejące strony po instalacji, bez zapisu na publicznym GET. Późniejsze dane edytuje **Ustawienia → Legal documents**. Nie zmieniono płatności, sekretów, umów klientów ani Discorda.
- **283 asercje PHP** zaliczone: legal publication55, site77, checkout-copy42, SEO22, billing87. **139 asercji przeglądarkowych**, 12 widoków (3 strony × 1440/768/390/320), 2 przekierowania; brak overflow i JS runtime errors. Lint **111 plików PHP**, JS i diff check poprawne. **97 kontroli świeżej instalacji ZIP** na osobnym offline WP6.8.3/PHP8.2 zaliczonych. Oba ZIP-y zgodne bajtowo ze źródłami (Core25 / theme89 plików); manifest `artifacts/legal-096-release-manifest.json`. Odświeżono prawdziwy screenshot Home1200×900.
- Wąski przegląd publikatora/uprawnień i zgodności opisów z kodem nie wykazał blockera. Oficjalne zewnętrzne źródła prawne i coldz.pro były niedostępne z tego środowiska; tekstów nie kopiowano. Dokument: `docs/LEGAL_PUBLICATION_0.9.6.md`. Hostinger MCP nie jest połączony; nie potwierdzono zdalnej instalacji ani cache. Następny etap: aktualizacja rzeczywistego WP, sprawdzenie publicznych regulaminów i uzupełnienie danych kontaktowych; zachować obecne ustawienia billing/OAuth.

## Odzyskiwanie checkoutu i obsługa zamówień klientów — 2026-10-26

- Kontynuacja bazy GitHub main `6c995fb`, etap Core0.9.5/theme0.11.1. Właściciel potwierdził obie potrzeby: zamykanie niedokończonej próby oraz anulowanie przyszłych odnowień klienta. Kod `4f051d4` opublikowano fast-forward na GitHub main i potwierdzono zdalny ref.
- Dodano panel **Customer orders** z wyszukiwaniem po WP user ID, Discord ID albo UUID zamówienia, odświeżeniem statusu, zamknięciem potwierdzonego nieopłaconego checkoutu i anulowaniem odnowień. Finansowe operacje administracyjne wymagają manage_options/nonce/HTTPS i potwierdzenia; sam Discord Owner nie otrzymuje tych praw. Opłacony czas i historia pozostają zachowane; brak ręcznego ustawiania paid lub zwrotów.
- Klient widzi niedokończony checkout i przycisk zamknięcia. Zmiana operatora zamyka poprzednią próbę wyłącznie po potwierdzeniu braku możliwości obciążenia. Stripe wymaga kanonicznego expired; PayPal Monthly już CANCELLED/EXPIRED i kompletnej pustej historii, Lifetime wyłącznie VOIDED bez płatności. Otwarte zgody PayPal i brakujące identyfikatory nie są wymuszonym resetem odblokowywane.
- Nowa lokalna próba z udowodnionym błędem przed POST tworzącym checkout może przejść do failed. Stare niepewne próby i błędy po POST pozostają do weryfikacji u operatora. Diagnostyka Stripe pokazuje bezpieczny kod/HTTP/etap; faktycznej przyczyny zrzutu nie potwierdzono na Live.
- Instrukcja: `docs/CHECKOUT_RECOVERY_0.9.5.md`. Nie wykonano realnej płatności, anulowania, zmiany sekretów/ról ani instalacji na Hostingerze. **857 asercji PHP** zaliczone: Store143, recovery34, facade87, pilot44, public39, Discord93, checkout28, presentation38, downloads69, Stripe156 i PayPal126. **32 widoki responsywne**, 2 lokalne checkouty i 6 stron publicznych poprawne; lint73 PHP, JS i diff check poprawne. **97 kontroli świeżej instalacji ZIP** na osobnym WP6.8.3/PHP8.2; Core21/theme89 plików zgodnych ze źródłami, manifest `artifacts/checkout-recovery-095-release-manifest.json`. Przegląd Astra Medium: poprawiono idempotencję rekoncyliacji zamkniętego Stripe i widoczność anulowania odnowień przy lokalnym expired; brak dalszego zgłoszonego blokera. Testy operatorów są symulowane.

## Diagnostyka błędów Discord przed checkoutem — 2026-10-26

- Core 0.9.4 / theme 0.11.1; baza `a91391f`, kod `5c4e510` opublikowano na GitHub main. Komunikat ze zrzutu pochodzi z preflight bota, lecz screenshot nie rozstrzyga statusu ani przyczyny na Hostingerze. Publiczny checkout 0.9.3 pozostaje zachowany.
- Rozróżniono HTTP401/403/404/5xx, problemy sieci/429 i niepoprawny JSON. Błędy żądań zawierają dozwolony stały etap i HTTP; bez tokenów, odpowiedzi API i identyfikatorów z URL. Istniejące WP_Error codes, retry, autoryzacja i kolejność preflight → zamówienie → operator nie zmieniły się.
- **263 asercje PHP** zaliczone: public Live39, Discord billing93, pilot44, facade87. Nowe przypadki symulują 401/403/404/500 i uszkodzony JSON, sprawdzając brak zamówienia/zapisu u operatora oraz brak sekretów w komunikacie. Lint zmienionego PHP i diff check poprawne; **97 kontroli instalacji ZIP** zaliczonych na osobnym WP6.8.3/PHP8.2. Core21 plików zgodnych ze źródłami; `artifacts/discord-094-release-manifest.json` i logi `artifacts/discord-094-*.log`.
- Nie potwierdzono rzeczywistej konfiguracji bota ani nie zmieniono Hostingera/sekretów/ról. Przyczynę ustala się z HTTP/Reference po ponowieniu na aktualnej wtyczce. Instrukcja: `docs/DISCORD_CHECKOUT_DIAGNOSTICS_0.9.4.md`.

## Publiczne zakupy dla zwykłych klientów — 2026-10-26

- Nowe polecenie właściciela otwiera sprzedaż dla wszystkich klientów; zastępuje wcześniejsze ograniczenie do jednego konta. Baza GitHub `main`: `7688fc5`; Core 0.9.3 / motyw 0.11.1. Kod `fb2142e` wypchnięto fast-forward na GitHub `main` i potwierdzono zdalny ref, bez force push.
- Administrator w HTTPS wybiera Live, Public website checkout i zatwierdza `public_live_enabled`. Domyślnie flaga pozostaje wyłączona; zapis chronią uprawnienia manage_options i nonce WordPressa. Nie trzeba zapraszać każdego Discord ID ani nadawać klientowi Ownera. Nowa zgoda na publiczny Live nie tworzy fikcyjnych potwierdzeń testów lub przeglądu prawnego; stara metoda przez stałą serwerową pozostaje obsługiwana.
- Publiczne zatwierdzenie obejmuje również OAuth, pomijając dawną listę pilota. Nadal wymagane są poprawne ustawienia i włączone OAuth; nowy klient otrzymuje subscriber. Weryfikacja ról Discord, chronione pliki, lokalna lista zatwierdzonych Ownerów, nonce checkoutu, GET-only preflight bota, webhooki i ochrona przed duplikacją umów pozostają aktywne.
- Zachowano automatyczny powrót do Monthly/Lifetime i przyciski Stripe/PayPal bez checkboxów. Puste Live guild/VIP IDs korzystają z istniejącej konfiguracji Discord. PayPal nadal wymaga rzeczywistego Stripe acceptance i własnych ustawień. Instrukcja: `docs/PUBLIC_LIVE_CHECKOUT_0.9.3.md`.
- Zatrzymanie nowych zakupów przez routing disabled zachowuje publiczne logowanie do istniejących umów. Ochrona maintenance nie pozwala przypadkowo przywrócić dawnej listy pilota dla opłaconych klientów przy wyłączeniu publicznej flagi.
- Nie wpisano rzeczywistych sekretów, nie pobrano opłaty, nie nadano zdalnych ról i nie aktywowano publicznego Live na Hostingerze. **595 asercji PHP zaliczone:** public checkout 29, public OAuth 27, pilot 44, OAuth regresje 127, OAuth return 53, facade 87, Discord billing 93, checkout 28, presentation 38, private downloads 69. **24 widoki responsywne**, 2 lokalne checkouty i 6 stron publicznych poprawne; lint 73 plików PHP/JS i diff check. **97 kontroli świeżej instalacji ZIP** na osobnym WP 6.8.3/PHP 8.2; Core21/theme89 plików zgodnych ze źródłami. Manifest `artifacts/public-live-093-release-manifest.json`, logi `artifacts/public-093-*.log`. Astra Medium wykryła i potwierdziła poprawkę zachowania publicznego logowania przy zapisanych starych flagach pilota; dalszych blokujących usterek nie zgłoszono.

## Ograniczony pilot Live i powrót prosto do checkoutu — 2026-10-26

- Kontynuacja istniejącego projektu: baza GitHub `main` odczytana jako `44cd841`, zachowany checkpoint `5acad52` na `feature/simpler-payment-setup`. Core 0.9.2 / theme 0.11.1. Kod etapu `9527129` wypchnięto fast-forward na GitHub `main` i potwierdzono zdalny ref; bez force push.
- Właściciel wybrał rzeczywiste płatności Live dostępne początkowo tylko dla własnego konta Discord. Ten zakres zastępuje historyczny wymóg pierwszej próby wyłącznie na stagingu; nie stanowi zgody na publiczne uruchomienie sprzedaży ani potwierdzenia przeprowadzonych testów. Ograniczenie konta działa po stronie serwera także dla POST i komend Discord.
- Panel administracyjny zapisuje poświadczenia w szyfrowanym, nieautoloadowanym magazynie bez ich ponownego wyświetlania. Dwa Stripe Price IDs są sprawdzane odczytem i uzupełniają Product IDs. Puste pola sekretów zachowują zapisane wartości; stałe serwerowe mają pierwszeństwo. Szczegóły i krótka konfiguracja: `docs/SIMPLER_LIVE_CHECKOUT_0.9.2.md`.
- OAuth zachowuje wybrany Monthly/Lifetime i poprawny podpisany intent w jednorazowym stanie. Po zalogowaniu klient automatycznie wraca do checkoutu i widzi dostępne przyciski Stripe/PayPal bez checkboxów. Cena, odnowienie i warunki są przedstawione przed świadomym przyciskiem POST; samo logowanie ani GET powrotu nie obciążają konta i nie przyznają VIP. Przegląd zasad konsumenckich pozostaje wymagany.
- Brak metod płatności ma widoczny komunikat oraz szczegółową diagnostykę tylko dla administratora. Przed utworzeniem checkoutu Live wykonywany jest GET-only preflight bota i ról Discord; właściciel serwera nie jest już bezwarunkowo odrzucany. Zachowano ochronę innych ról, sprawdzanie hierarchii VIP i niezależnych praw dostępu.
- Zatrzymanie nowych zakupów zachowuje Live i obsługę istniejących umów; niezabezpieczony zapis ustawień nie może wyłączyć tego trybu. PayPal nadal wymaga ukończonego Stripe acceptance, własnych poświadczeń i webhooka; Monthly także planu/produktu.
- Testy bieżącego etapu: **817 asercji PHP** (OAuth powrót 53, OAuth regresje 127, Live pilot 44, credentials 57, facade 87, Discord billing 93, Store 112, Stripe 99, PayPal 79, checkout 28, presentation 38); **16 responsywnych widoków +2 adresy checkoutu +6 stron**; lint 73 plików PHP/JS i diff check. **97 kontroli świeżej instalacji ZIP** na osobnym WP 6.8.3/PHP 8.2; 89 plików motywu i 21 Core zgodnych ze źródłami. Manifest `artifacts/simpler-checkout-release-manifest.json`. Ukierunkowany przegląd Astra Medium nie zgłosił blokujących problemów; naprawiono odrzucanie guild ownera i zmianę środowiska Live przez HTTP. Nie wykonano rzeczywistego obciążenia, zdalnej zmiany ról, konfiguracji sekretów ani aktywacji Live na Hostingerze. Push GitHub nie jest dowodem instalacji na hostingu.

## Publikacja website-only checkout na GitHub main — 2026-10-25

- Na wyraźne polecenie właściciela „wypchnij na main, produkcje” opublikowano przetestowany kod `93eafde` na GitHub `main`, fast-forward z `664333f`, bez force push i bez nowych zmian kodu.
- Ponownie potwierdzono istniejące wyniki 553 asercji PHP, 96 świeżej instalacji i 16 widoków przeglądarkowych oraz zgodność każdego pliku źródłowego z przetestowanymi ZIP-ami. Core 0.9.1 / theme 0.11.1.
- Nie włączono Live ani synchronizacji ról; brak instalacji paczek na Hostingerze i brak potwierdzenia zdalnego automatycznego deploymentu. Push na main nie zastępuje konfiguracji Stripe Test i aktualizacji działającego WordPressa.

## Website-only checkout + Ticket Tool — Core 0.9.1 / theme 0.11.1 (2026-10-25)

- Na nowe polecenie właściciela wycofano Upgrade.chat z NOWYCH zakupów i aktywnego UI. Domyślny checkout jest `disabled`; dawne `legacy`/nieznane wartości są mapowane na `disabled`, bez kasowania zapisanych opcji. `get_destination` i stary sanitizer zawsze zwracają pustą wartość, więc żaden stary URL nie może przekierować klienta. Wyłączony checkout renderuje czytelny widok w motywie, bez formularza płatności, zamiast błędu WordPressa.
- Własny checkout pozostaje na `/checkout/monthly/` i `/checkout/lifetime/`; główny katalog pakietów na `/pricing/`. Ustawienia → TimonConfigs pokazują trzy gotowe adresy tylko do odczytu, do wklejenia w Ticket Tool. Wiadomość po angielsku i instrukcja: `docs/WEBSITE_CHECKOUT_TICKET_TOOL.md`. Nie konfigurowano zdalnie Discorda/Ticket Tool ani nie wysyłano wiadomości.
- Publiczne szablony, FAQ, SEO, panel klienta i nowe seedy opisują płatność na stronie oraz pomoc przez ticket. Centralne ceny zawsze €29.99 / miesiąc i €80 / raz, także przy wyłączonej sprzedaży. SEO nie emituje aktywnej oferty checkoutu, gdy jest wyłączony. Dane wcześniejszych zakupów, niezależne uprawnienia, stare subskrypcje i aktualna weryfikacja ról pozostają zachowane.
- Dokładnie pięć rozpoznanych całych stock stron ma migrację na admin_init wyłącznie dla manage_options. Backup prywatny, compare-and-swap chroniący równoległą edycję, bez zmiany tytułów, statusów, dat i zatwierdzeń prawnych. Autorskie treści nie są przepisywane; lista przeglądu jest w Tools → TimonConfigs setup. Brak zapisów treści na publicznych requestach. Niezależny od fixture test porównał pięć zamrożonych snapshotów z faktycznym `default_content` poprzednio opublikowanego main `664333f`: identyczne bajtowo. Lokalne pięć domyślnych stron zaktualizowano i potwierdzono stan current.
- **553 asercje PHP zaliczone:** checkout 28, presentation 38, billing facade 85, OAuth 127, downloads 69, member UI 56, SEO 22, stock migration 42, Discord billing 86. Odkryty stary test Owner copy dostosowano do już wcześniej wprowadzonego komunikatu lokalnie zatwierdzonego Ownera; nie zmieniano autoryzacji. Operatorzy/Discord nadal symulowane; żadnych zakupów ani zdalnych zmian ról.
- Browser: **16 widoków responsywnych**, **2 rzeczywiste adresy checkoutu**, **6 stron publicznych**: brak starych instrukcji/linków, ceny i layout poprawne. Nowy prawdziwy screenshot motywu **1200×900** z działającego WP i aktualnymi cenami; tryb kompaktowy DOM, pełne niezmienione zrzuty zachowane w artifacts. Lint72, static/JS i diff check poprawne.
- ZIP-y 0.11.1 /0.9.1 zgodne ze źródłami (89 theme /20 Core); **96 asercji świeżej instalacji** na oddzielnym WP 6.8.3/PHP 8.2. Manifest: `artifacts/website-checkout-release-manifest.json`, raporty `artifacts/site-only-*.log`. Nie zmieniono kodu po testach/pakowaniu poza testami przeglądarki i dokumentacją.
- Gałąź `feature/site-only-checkout`, checkpoint kodu `068ff98`, baza GitHub main `664333f`. Nowego etapu nie wdrożono na produkcję. Live pozostaje zablokowany do konfiguracji operatorów, prawdziwego staging acceptance, przeglądu bezpieczeństwa/VAT i osobnej zgody; wycofanie starego checkoutu nie włącza nowego operatora automatycznie.

## Autoryzowana publikacja native billing na GitHub main — 2026-10-25

- Na wyraźne polecenie właściciela wypchnięto przetestowany kod `3d38fb7` z `feature/direct-billing-test` na `main`, fast-forward z `ea566d4`, bez force push i bez nowych zmian kodu.
- Obowiązują dotychczasowe wyniki: 949 asercji PHP, 91 instalacji ZIP, 12 widoków przeglądarkowych. Core 0.9.0 / theme 0.11.0. Testy operatorów pozostają symulowane.
- Nie włączono płatności Live, nowych poświadczeń ani synchronizacji ról. Nie wykonano instalacji paczek na Hostingerze; sam push nie potwierdza aktualizacji działającego WordPressa. Następny krok: instalacja na stagingu i rzeczywiste Stripe Test według `docs/DIRECT_BILLING_SETUP.md`.

## TimonConfigs 2.0 — Core 0.9.0 / theme 0.11.0, etap testowy (2026-10-25)

- Zachowano istniejący projekt i aktualne rozwiązanie surowych plików oraz lokalnej autoryzacji Ownera. Początek prac: main `7990e20`; ponownie sprawdzono GitHub i dołączono najnowszy main `ea566d4` (PR #11: dokumentacja SEO/prawna). Starsza lokalna praca pozostaje w `158cc91`, bez nadpisywania nowszego kodu.
- Gałąź: `feature/direct-billing-test`. Checkpointy: `a6175c0` (adaptery/ledger), `a0988d8` (integracja i panel), `be2f391` (ochrona migracji i kolejki), `3de6024` (aktualny main), `ad539de` (nierozstrzygnięte checkouty). Nowe polecenie dopuszcza własne płatności, lecz NIE produkcję, realne obciążenia ani migrację istniejących klientów.
- Gotowy kod: sześć tabel InnoDB, niezmienne powiązanie WP/Discord/zamówienie, Stripe Checkout/Billing/Portal, PayPal Checkout/Subscriptions, podpisane idempotentne webhooki, kanoniczna rekoncyliacja, okresy opłacone/zwroty/spory, outbox Discord z dokładnie dwoma rolami VIP i podpisane komendy `/buy`, `/membership`, `/manage`, `/link`. Istniejący OAuth, upload Ownera i chroniony downloader zachowano.
- Panel klienta: operator, pakiet i status, opłacony termin, następna płatność, historia, anulowanie/zarządzanie i status synchronizacji. Panel administracyjny: identyfikatory produktów, gotowość bez ujawniania sekretów, bezpieczne ponowienia i zweryfikowane stare/grantowe dostępy. Układ responsywny dark/orange, publiczne treści po angielsku.
- Sprzedawca: Polska, EUR. Nowe centralne kwoty: Monthly 2999 centów / miesiąc, Lifetime 8000 centów / raz. Domyślnie pozostaje legacy Upgrade.chat z dotychczasową ofertą €40/€100; nowe ceny pokazują się po świadomym przełączeniu direct. Test jest ograniczony do local/development/staging; PayPal wymaga potwierdzonych rzeczywistych testów Stripe. Live wymaga osobnej zgody serwerowej, HTTPS, migracji i przeglądu prawnego. Nie włączono tych potwierdzeń.
- Naprawiono wykryte przypadki: zapis zgody musi poprzedzać API, stare odnawialne umowy blokują nową subskrypcję także poza ostatnimi 20 zamówieniami, nierozstrzygnięty checkout nie znika po czasie retencji idempotencji, stare role VIP bez przeglądu blokują pokrywający się zakup. Niezależne uprawnienia sumują się; awaria odczytu ledgeru/ownership niczego nie usuwa. Błąd zapisu po udanym zdalnym nadaniu roli nie jest udawanym sukcesem. Filtr trybu działa przed LIMIT w każdej kolejce, więc Test nie zagłodzi Live.
- **949 asercji PHP zaliczonych:** nowe billing 459 (Store 112, Stripe 66, PayPal 79, Discord 86, facade 83, presentation 33) oraz regresje 490 (Checkout 14, OAuth 127, Downloads 69, Owner 169, Owner identity 20, Catalog 45, Shared VIP 24, SEO 22). Transport operatorów i Discord jest symulowany; baza WP, transakcje, prywatny magazyn, sesje i kryptografia są rzeczywiste lokalne. Nie wykonano prawdziwego zakupu ani zdalnej zmiany roli.
- Przeglądarka: **12 widoków** rzeczywistego renderu PHP checkoutu/panelu przy 1440/768/390/320 px, bez overflow i błędów JS; wymagane zgody, focus klawiatury i tabela przewijana w kontenerze sprawdzone. To fixtures z symulowanymi operatorami, nie logowanie do rzeczywistego Stripe/PayPal. Zrzuty i raport: `artifacts/billing-*.png`, `artifacts/billing-browser-report.json`. Bieżący screenshot motywu zachowano.
- Lint **72 plików PHP**, static/JS i diff check przechodzą. Paczki `artifacts/timonconfigs-theme-0.11.0.zip` (89 plików) i `artifacts/timonconfigs-core-0.9.0.zip` (20 plików), zgodne bajtowo ze źródłami. **91 asercji świeżej instalacji** zaliczonych na osobnym WP 6.8.3/PHP 8.2; wynik i SHA256: `artifacts/billing-release-installation.log`, `artifacts/direct-billing-release-manifest.json`. CI rozszerzone o sześć zestawów billingowych.
- Instrukcja: `docs/DIRECT_BILLING_SETUP.md`; raport i jawne braki: `docs/DIRECT_BILLING_TEST_REPORT.md`; kontrakt: `docs/DIRECT_BILLING_CONTRACT.md`. Aktualne taryfy PL/EUR i API dokumentacje są niepotwierdzone, bo oficjalne strony zwróciły proxy CONNECT403. Nie zmyślono prowizji ani gwarancji wypłat EUR. Brakuje realnego staging acceptance, poświadczeń, uzgodnienia VAT/faktur i rzeczywistej migracji/monitoringu. Produkcja i istniejące płatności nie były modyfikowane.

## Publikacja Core 0.8.6 / theme 0.10.5 na GitHub main — zatwierdzona przez właściciela

- Na polecenie „WYPCHNIJ to na produkcje na gicie” przeniesiono `fix/owner-upload-recovery` do `main` przez fast-forward, bez konfliktów i force push. Push został przyjęty; osobny odczyt `refs/heads/main` potwierdził kod `b5d4de2`.
- Brak nowych zmian kodu od testów: 726 asercji PHP, 39 Owner browser, 24 widoki i 76 asercji instalacji ZIP. Potwierdzono zgodność paczek i źródeł. Aktualne wersje: Core 0.8.6 / motyw 0.10.5.
- Nie wykonano instalacji paczek na Hostingerze ani nie potwierdzono automatycznego deploymentu hostingu. Nie modyfikowano produkcyjnej bazy, płatności ani ról Discord.

## Upload recovery — Core 0.8.6 / theme 0.10.5 (2026-10-24)

- Baza: najnowszy `main` (`6d1e20e`, kod `19ae648`). Praca na `fix/owner-upload-recovery`; dotychczasowe zmiany zachowano.
- Rozszerzono inspekcję tekstowych GPC/CFG/INI o BOM UTF-16 i Windows ANSI, zachowując bajty, rozmiar i SHA-256. Heurystyka języka MIME nie odrzuca zwykłego tekstu źródłowego. Błędy typu pliku zawierają bezpieczną informację MIME zamiast ogólnego komunikatu ze zrzutu.
- **59 asercji plików** zaliczonych na lokalnym WP. Testy obejmują kodowanie GPC/TXT, niezmienione bajty i odrzucanie zakodowanych aktywnych payloadów. Nie otrzymano oryginalnego pliku użytkownika, więc nie potwierdzono jego konkretnego formatu.
- Weryfikacja Owner: lokalne **22 asercje** rozróżniają awarię Discorda od usunięcia roli; nie ma obejścia autoryzacji. Formularz AJAX zachowuje dane i File po błędzie 503 lub sieci. Naprawiono też odczyt action formularza przesłonięty przez ukryty input WordPressa. Zwykły POST bez JS zachowuje 303 fallback.
- Łącznie **726 asercji PHP** zaliczonych: private-files 73, policy 28, Owner 223, downloads 69, Discord 122, shared VIP 24, catalog 45, routes 5, member UI 56, tekst 59, Owner recovery 22. Przeglądarka: **39** rzeczywistych HTTP upload/karta/download/503 retry oraz **24** layouty panelu, bez błędów, fixtures usunięte. TXT UTF-16, GPC ANSI i ZIP ZNBK publikują od pierwszego zapisu i pobierają identyczne bajty. Transport Discord jest mockowany. Końcowy przebieg 39 asercji ponowiono po dodaniu przywracania diagnostyki sprzed testu; również zaliczony.
- Statyczna kontrola projektu i lint 66 plików PHP przechodzą. ZIP-y są zgodne ze źródłami (88 theme / 14 Core) i zaliczyły **76 asercji świeżej instalacji** na odizolowanym WordPress 6.8.3/PHP 8.2. Manifest: `artifacts/owner-upload-086-release-manifest.json`. Checkpointy kodu: `1deb95a`, `abc2287`. Hosting produkcyjny, płatności i role Discord niezmienione. Szczegóły: `docs/OWNER_UPLOAD_RECOVERY_0.8.6.md`.

# TimonConfigs project progress

## Owner-authorized publication of 0.8.5 to GitHub main

- Na polecenie właściciela „wypchnij na produkcje” scalono fast-forward `fix/upload-text-and-shared-vip` do `main` i potwierdzono GitHub main na `19ae648`. Kod: Core 0.8.5 / motyw 0.10.4. Nie było nowych zmian kodu od testów i kontroli paczek.
- Hostinger nadal nie udostępnia callable narzędzi. Nie wykonano bezpośredniej instalacji ZIP na hostingu ani nie potwierdzono zdalnego automatycznego deploymentu. Push GitHub nie jest dowodem aktualizacji działającego WordPressa.


## ZNBK/TXT uploads, shared VIP and simple Owner form — Core 0.8.5 / theme 0.10.4

- Sprawdzono najnowszy GitHub main `b10f360` i zachowano PR #2–#6, bez przepisywania ich implementacji. Niedokończone wcześniejsze zmiany zabezpieczono checkpointem `f376b8e`; aktualna praca: `fix/upload-text-and-shared-vip`.
- Na WYRAŹNE nowe polecenie właściciela ZNBK nie wymaga już zewnętrznej zgody inspektora. Obowiązują automatyczne kontrole pliku i opakowania ZIP, limity i prywatny magazyn; opcjonalny skaner może odrzucić plik. XIMMR zachowuje dotychczasowy wymóg inspektora. Nie dodano fałszywego callbacku `return true`. Dokument 0.8.4 oznaczono jako historyczny.
- TXT obsługuje UTF-8/BOM oraz Windows UTF-16 LE/BE. Walidacja nie przepisuje bajtów ani SHA-256. Poprawny tekst zaczynający się od MZ nie jest traktowany jak program tylko przez te dwa znaki; binarne/wykonywalne/zakodowane aktywne payloady pozostają odrzucone.
- Wybór CONFIG access: Free Member / VIP — Monthly & Lifetime / VIP Lifetime only. Wspólny wariant wykorzystuje dotychczasowy `monthly` i uprawnia oba płatne pakiety, bez migracji danych ani zmian cen/Discord ról/Upgrade.chat.
- Prosty formularz: tytuł, gra, urządzenie, dostęp, plik; reszta w Advanced options. Plik można dodać w tym samym zapisie co publikację. Brak pliku nie publikuje gotowej karty; zachowuje recoverable draft. Owner bez VIP widzi Owner tools enabled. Krótki cooldown weryfikacji jest obsługiwany w przeglądarce, backend nadal wymusza autoryzację.
- Test przeglądarkowy wykrył brak cache reguły `/configs/` w lokalnym WP; jednorazowa soft migracja reguł po aktualizacji przywraca katalog i nie zmienia Home/URL/ustawień integracji. Dodatkowe testy oraz CI obejmują tę regresję.
- **671 asercji PHP**: pliki 73, polityka 28, TXT 26, wspólny VIP 24, Owner 223, pobieranie 69, OAuth 122, katalog 45, reguły 5, panel 56 — wszystkie zaliczone. Przeglądarka: **29 asercji realnego Owner uploadu/kart/pobierania** + **24 widoki panelu**. ZNBK został wysłany i pobrany bez inspektora; TXT zachował oryginalne bajty UTF-16. Ostatni Owner browser nie resetował sztucznie cooldownu. Discord transport jest symulowany, sesje WP i HTTP/TLS/file operations są rzeczywiste lokalne; fixtures posprzątano.
- Faktyczne ZIP-y (88 plików theme / 14 Core) zgodne ze źródłami przeszły **76 asercji świeżej instalacji** na izolowanym WordPress 6.8.3/PHP 8.2. PHP lint, JS/static oraz diff check poprawne. Manifest: `artifacts/upload-085-release-manifest.json`; raport: `docs/UPLOAD_AND_SHARED_VIP_0.8.5.md`. Nie wdrożono Hostinger ani nie zmieniono produkcyjnych ról/płatności.


## Compact click-to-download catalog + Matrix TXT (2026-10-24; PR #3)

- Simplified catalogue cards: removed long preview copy, publisher, dates and download counters; full-card click issues the existing signed, session-bound, one-use download POST when access is verified. Login/VIP redirects remain explicit; Coming Soon and missing-file cards are non-interactive previews. Guide/Values modal buttons remain separately actionable.
- XIM Matrix automatically selects a private TXT attachment or generates a bounded `.txt` from authorized Owner Values. No unsafe binary XIMMR relabelling. Signed TXT tokens include a content digest and revoke when Values, release state or device changes; Free and Unverified authorization rules still apply.
- Release versions: theme 0.10.1 and Core 0.8.1 for WordPress asset cache busting. No automatic hosting deployment or Discord/Upgrade.chat role/payment modifications.
- GitHub Actions WordPress download integration run `40043541966`: **success** — disposable WordPress bootstrap, catalog filters (45 assertions), private downloads + Matrix TXT (69 assertions, mocked Discord) and member catalog/card presentation all passed. PHP syntax workflow run `40043541971`: success. These do **not** verify production Hostinger cache, real Discord tier changes or a real browser download.
- Handoff: `docs/CLICK_TO_DOWNLOAD_CARDS_AND_XIM_TXT.md`. Before updating Hostinger, review backups and test the new version with real Free/VIP account and a harmless Matrix Values example/TXT. Avoid editing real customer memberships.


## Config catalog visibility + Owner workflow (2026-10-24; PR #2 Draft)

- Identified source-level issue: Public catalogue excluded posts with WordPress `publish` and `tc_release_status=coming-soon`. New `catalog_visible_meta_query()` includes these as non-downloadable public preview cards but continues to hide Archived; released-only/SEO and `TC_Downloads` authorization remain protected.
- Owner interface simplified: single Game and Device selectors (required for new publishing), expanded published/coming/archived diagnostics and relevant links, per-game cover as default with explicit per-config custom override, optional first private upload during initial creation. New uploads are staged as drafts and published only after validated storage. Existing admin editor remains a fallback.
- Added Configs admin list diagnostics, CSS, updated catalogue and Owner regression-test source and new PHP-lint workflow. No production database/files/user roles/billing or Hostinger settings were changed.
- GitHub Actions PR #2 workflow `php-lint` run `40041566716` completed successfully (PHP syntax + whitespace check). **WordPress integration/browser tests on disposable staging and actual Hostinger access remain unverified**, as does the live availability of any owner-created configuration.
- Handoff: `docs/CONFIG_CATALOG_VISIBILITY_AND_OWNER_WORKFLOW.md`, `NEXT_SESSION.md`. PR is a Draft and must not be merged or deployed without review and further tests.

Last updated: 2026-10-24

## Publikacja kodu odzyskiwania uploadu

- `fix/owner-upload-recovery` wypchnięto na GitHub; commity implementacji `1deb95a`, `abc2287`, weryfikacja paczek `c7be2a2`.
- `main` i instalacja Hostinger nie były aktualizowane. Automatyczne utworzenie PR przez GraphQL i REST zwróciło `Forbidden`; gałąź można przeglądać i porównać z main w GitHub.
- Paczki Core 0.8.6 / theme 0.10.5 są zgodne z kodem. Dalszy krok: zatwierdzona instalacja obu paczek, purge cache i sprawdzenie oryginalnego skryptu właściciela. Brak oryginalnego pliku uniemożliwia potwierdzenie dokładnej przyczyny odrzucenia z jego zrzutu.

## Publication to GitHub main — owner authorized

- Na polecenie „wypchnij w produkcje” przeniesiono cztery przetestowane commity do `main` przez fast-forward bez konfliktów i force push. GitHub main potwierdzono na `17441cb` (motyw 0.10.0 / Core 0.8.0); gałąź feature zachowano.
- Nie było nowych zmian kodu od pełnej weryfikacji wydania. Brak callable narzędzi Hostinger nadal uniemożliwia bezpośrednią instalację paczek i weryfikację działającego WordPressa. Nie potwierdzono zdalnego automatycznego wdrożenia ani nie zmieniano bazy, ról lub płatności.

## Release verification — theme 0.10.0 / Core 0.8.0

- Wymagania nowych briefów wdrożono etapami na gałęzi `feature/owner-catalog-guides-seo`, zachowując bazę `main` i wszystkie istniejące integracje. Checkpointy: `2d08a00` (role/formaty), `d8d76dc` (edytor/katalog/treści), `7528775` (Home/SEO). Ostatni checkpoint obejmuje dokumentację i weryfikację wydania.
- Łącznie **891 asercji integracyjnych** w lokalnym WordPressie oraz **76 asercji instalacji** faktycznych ZIP-ów na oddzielnym WordPressie 6.8.3/PHP 8.2. Przeglądarka: 18 Owner, 27 Guide/Values, 34 multipart, 18 layoutów panelu, 12 przypadków panelu + 4 nagłówka + 8 logout/replay, 9 Home/collection, 35 storefront, 55 full-site i 10 regresji Home; dodatkowo byte/replay/descriptor delivery oraz inline/optional video. Wymienione kontrole zaliczone. PHP lint, JS/static i diff check poprawne.
- Poprawiono obsługę małych obrazów, walidację częściowego Guide/Values przed publikacją oraz zgodność typów bool z deklarowanym minimum PHP. Wielokrotne zweryfikowane role są widoczne jako odznaki, a najwyższy tier nadal steruje płatnym dostępem. Testy wideo rozróżniają dialog tekstu od modala wideo; domyślny film pozostaje w karcie.
- ZIP-y: motyw 0.10.0 (87 plików), Core 0.8.0 (14), zgodne bajtowo ze źródłami. Manifest i logi w ignorowanych artifacts. Odświeżono rzeczywisty screenshot motywu 1200×900 i pełne nietknięte Home oraz zrzuty nowych UI; kompaktowy screenshot nie zastępuje porównania z brakującymi binarnymi A–E.
- Pełny handoff: `docs/FEATURE_RELEASE_0.10.md`, `docs/CMS_GUIDE.md`, `NEXT_SESSION.md`. Testy Discorda są lokalne/mock; Owner browser korzysta z tymczasowego logowania fixture i prawdziwej sesji WP, nie consent providera. Produkcja/role/płatności nie zostały zmienione. Właściciel musi zatwierdzić wdrożenie oraz wykonać opisane testy i konfigurację hostingu. Ponowny publiczny HEAD obu domen odrzucił proxy na CONNECT (curl 56 / proxy 403); to nie jest status zdalnej witryny.

## Home, search intent and preview-domain SEO

- Domyślne H1/title/copy i CTA kierują do rzeczywistych kolekcji XIM Matrix/Cronus Zen dla Apex/CoD. Rozwinięto istniejące kolekcje o sekcje urządzeń i praktyczną orientację zamiast mnożyć cienkie strony. Zachowano nadpisania Gutenberga/Customizera i provider-aware SEO.
- Końcowe CTA ma jednolite ciemne tło bez obrazu i pseudo-overlay; FAQ zachowuje krajobraz. Edytor nie proponuje już obrazu dla tego konkretnego CTA. Odnośniki tematyczne i mobile wrapping zweryfikowano.
- `*.hostingersite.com` ma noindex w nagłówku i obsługiwanych providerach oraz wyłączone reklamowanie sitemap. Nie zmieniono DNS, home/siteurl ani przekierowań produkcyjnych. Realne aliasy/cache i Search Console nadal wymagają weryfikacji hostingu.
- Testy: 21 Home/SEO, 20 tymczasowej domeny, 9 rzeczywistych widoków Home/Apex/CoD przy 390/768/1440. Wszystkie zaliczone. Binarne referencje A–E nie są w repo; zaimplementowano ich opisane wymagania, bez deklaracji porównania pikselowego. Handoff: `docs/SEARCH_INTENT_HANDOFF_2026-10-24.md`.

## Frontend Owner editor, catalog and protected Guide/Values

- Dodano frontend Manage Configs dla zweryfikowanego Ownera: tworzenie/edycja/publikacja/Trash, taksonomie i metadane, Guide/Values, bezpieczne obrazy podglądu oraz wieloplikowy magazyn prywatny z wersjami, kolejnością, potwierdzeniem zmian i audytem. Osobne capabilities CPT są przyznawane Subscriberowi wyłącznie w autoryzowanej operacji; wp-admin pozostaje fallbackiem administratora. Wcześniejsza walidacja zapobiega częściowej publikacji po błędnych Guide/Values.
- Dashboard nie zawiera My Downloads ani Recent downloads; stary adres pozostaje prywatnym widokiem z odsyłaczem do konfiguracji. Zachowano dane historii, podpisane pobieranie i kontrolę tierów. Staff/Owner mają osobne odznaki i nie uzyskują automatycznie VIP.
- Karty zachowują filtry i układ 3/2/1. Dodano atomowy licznik zakończonych dostaw oraz sortowanie Downloads, bez fikcyjnego uzupełniania historii. Chronione Guide/Values są pobierane dopiero po autoryzacji; osobne poziomy widoczności, rewizje, natywny dialog, kopiowanie i klawiatura.
- Nowe testy: Owner 211 asercji, treści 47, agregacja 16, dashboard 42. Przeglądarka: 27 kontroli treści, 18 stanów panelu i 18 realnych operacji Ownera przez lokalny TLS (Discord API mock; tymczasowe konta/MU plugin/proxy usunięte). Zrzuty w ignorowanych artifacts. Test nie zastępuje rzeczywistego OAuth/hostingu. Wszystkie wymienione kontrole zaliczone; bez produkcji.

## Owner/Staff policy and file formats — first feature checkpoint

- Kontynuacja `main` po nowych dokumentach (baseline `c32fefd`), gałąź `feature/owner-catalog-guides-seo`. Użytkownik potwierdził poprawny Free Member po ponownym logowaniu; to oczekiwane zachowanie, nie błąd wylogowania.
- Rozdzielono zweryfikowane odznaki Staff/Owner od płatnego tieru. Zarządzanie wymaga aktualnej roli Owner, zatwierdzonego Discord User ID oraz poprawnego powiązania konta. Każdy zapis wymusza nowe sprawdzenie serwerowe; awarie i throttling odmawiają dostępu.
- Rozszerzono prywatną politykę formatów o tekstowe CFG/INI i administratorowy wybór bezpiecznego podzbioru. Nadal wymagany inspektor ZNBK/XIMMR; wyłączenie formatu blokuje upload/resolve bez usuwania danych. Adapter dla edytora nie omija walidacji ani audytu.
- Lokalne testy WordPress: OAuth/role 122, polityka formatów 28, prywatne pliki 65 — wszystkie zaliczone. Transport Discord jest symulowany. Produkcji nie modyfikowano. Dalsze moduły są implementowane w kolejnych checkpointach.

## Logout regression verification

- Po zgłoszeniu „po wylogowaniu dalej Free Member” poproszono o rozróżnienie widoku bez ponownego logowania od prawidłowego przywrócenia statusu po nowym logowaniu. Wynik z hostingu pozostaje nieustalony; nie założono automatycznie błędu sesji ani LiteSpeed.
- Rozszerzono rzeczywisty test przeglądarkowy o kliknięcie Logout, powrót do karty gościa, usunięcie cookie, odświeżenie oraz próbę użycia poprzedniego cookie w nowym kontekście. Wszystkie 8 asercji przeszło, podobnie dotychczasowe 6 widoków gościa, 6 właściciela i 4 szerokości nagłówka. Test używa lokalnego konta WordPress, nie prawdziwego Discorda ani LiteSpeed. Kod runtime i wersja Core 0.7.3 bez zmian.

## OAuth callback diagnostics — Core 0.7.3

- Właściciel zgłosił ogólny błąd callbacku po consent mimo poprawnego pilot User ID. Bez zdalnego dostępu przyczyna nie jest ustalona; nie przypisano jej brakowi bota ani nie osłabiono kontroli OAuth.
- Dodano administrator-only panel Last Discord sign-in failure w ustawieniach. Ostatnia nieudana próba callbacku zapisuje na maksymalnie 24 godziny wyłącznie czas UTC, etap, allowlistowany powód i status HTTP. Nie zapisuje treści odpowiedzi, URL, code/state, tokenów, sekretów ani tożsamości użytkownika. Komunikat publiczny pozostaje ogólny.
- Rozróżniono token/identity/binding, transport, błędy HTTP, invalid_client/invalid_grant, malformed_response, scope_mismatch, pilot_denied i konflikt konta. Zachowano ograniczenia pilota, rolę Upgrade.chat i istniejące dane konfiguracyjne.
- Lokalne testy: OAuth 93 asercje (11 nowych diagnostyki i braku wycieku), pobieranie 59 — zaliczone z symulowanym Discordem. PHP lint i diff check poprawne. ZIP zgodny bajtowo ze źródłami; świeża instalacja WordPress 6.8.3/PHP 8.2 zaliczyła 68 asercji. Następny krok właściciela: aktualizacja Core, świeża próba logowania, odczyt Stage/Reason/HTTP w panelu. Faktyczny błąd hostingu wymaga tego wyniku.

## Restricted Discord pilot — Core 0.7.2

- Na prośbę właściciela, który uzupełnił poświadczenia i chce rozpocząć testy na głównej stronie, dodano oddzielny tryb pilotażowy. Nie wymaga fałszywego potwierdzenia stagingu. OAuth nadal domyślnie OFF; wdrożenie/konfiguracja produkcyjna nie zostały wykonane.
- Administrator może wskazać maksymalnie 10 Discord User IDs. Kontrola serwerowa działa przed utworzeniem/powiązaniem konta i przed odczytem cache ról/autoryzacją pobrań. Usunięcie ID blokuje istniejący dostęp członkowski. Błędna/pusta lista blokuje OAuth; pilot nie nadaje ról VIP. Publiczny endpoint może pokazać consent, ale nieznana tożsamość nie dostaje konta WordPress.
- Testy lokalne: OAuth 82, pobieranie 59, UI członka 37 — 178 asercji zaliczonych, transport Discord symulowany. ZIP Core 0.7.2 zgodny bajtowo ze źródłami; świeża instalacja z motywem 0.9.0 przeszła 68 asercji. PHP lint i git diff --check poprawne. Instrukcja właściciela: `docs/DISCORD_PILOT_0.7.2.md`. Zachowano konfigurację Upgrade.chat, dane istniejących kont i dotychczasowy motyw 0.9.0.

## HTTPS staging — blokada połączenia z Hostingerem (2026-10-23)

- Rozpoczęto następny etap zgodnie z potwierdzeniem właściciela, że staging jeszcze nie istnieje. Sprawdzono instrukcję konektora: Node 24 spełnia wymagania, ale sesja nie udostępnia narzędzi Hostinger i nie ma rejestracji dziewięciu serwerów MCP.
- Próba `codex mcp add` po uzyskaniu uprawnień do katalogu konfiguracji zakończyła się błędem `Read-only file system` dla rzeczywistego katalogu Codex `/run/codex-environment/codex-home`. Nie zarejestrowano serwerów; sam restart nie jest potwierdzonym rozwiązaniem tej blokady. Nie zmieniano chronionego katalogu ani nie obchodzono konektora bezpośrednim API.
- Do kontynuacji potrzebna jest sesja udostępniająca działające narzędzia Hostinger Connector albo staging utworzony w hPanel i dostępne połączenie do jego wdrożenia. Dopiero odczyt konta pozwoli ustalić konkretny hosting i adres stagingu. Nie utworzono zdalnej witryny, nie zmieniono produkcji, OAuth ani płatności. Kod pozostaje na zweryfikowanym Core 0.7.1 / motywie 0.9.0.

## Private format validation — Core 0.7.1

- Właściciel potwierdził, że HTTPS staging nie jest jeszcze utworzony. Prawdziwe testy Discord OAuth i konfiguracja hostingu pozostają niewykonane; OAuth pozostaje wyłączony.
- Odtworzono testem rozbieżność polityki uploadów: tekstowe bajty z rozszerzeniem ZNBK/XIMMR przechodziły bez inspektora. Oba formaty wymagają teraz jawnego wyniku `true` z istniejącego filtra inspekcji niezależnie od kodowania. GPC/JSON/TXT zachowują dotychczasowe sprawdzanie. Doprecyzowano komunikat administratora.
- Kontrola dotyczy również rozwiązywania istniejących plików przed pobraniem. Wcześniej zapisane tekstowe ZNBK/XIMMR bez inspektora nie będą pobierane; dane nie są usuwane. Nie dodano fikcyjnego skanera ani potwierdzenia bezpieczeństwa pliku.
- Przywrócono lokalne prawa odczytu publicznych źródeł motywu/wtyczki (644), zgodnie z istniejącym setup helperem; odtworzone pliki 600 uniemożliwiały uruchomienie WordPressa przez użytkownika PHP. Prywatnego magazynu ani sekretów nie zmieniano.
- Testy: prywatne pliki 65, pobieranie 59, OAuth 60 — 184 asercje zaliczone (Discord mock). Rzeczywisty test multipart zaliczył 34 asercje, w tym tekstowe ZNBK/XIMMR; fixture usunięto. Nowy ZIP Core 0.7.1 wraz z istniejącym motywem 0.9.0 przeszedł 68 asercji świeżej instalacji WordPress 6.8.3/PHP 8.2. PHP lint, JS syntax, diff check i lokalny Home HTTP 200 poprawne. Paczka zgodna bajtowo ze źródłami; manifest `artifacts/private-format-release-manifest.json`. To wąska poprawka polityki formatów, nie pełny audyt bezpieczeństwa ani dowód działania realnego OAuth.

## Member login and dashboard — theme 0.9.0 / Core 0.7.0

- Zrealizowano etap UI z `docs/ASTRA_MEMBER_UX_HANDOFF.md` na istniejącym module: Member login w nagłówku, centralna karta Discord z logo i linkami powrotu, dashboard Welcome back z prawdziwym statusem oraz kafelkami Configs/My Downloads/Profile/Support. Biblioteka pobrań jest przed ofertą członkostwa. Zachowano edycję treści, grafikę profilu i dostępny dla czytników tytuł strony.
- Przejrzano rzeczywiste zrzuty lokalnego WordPressa. Odświeżono screenshot motywu 1200×900; nowe widoki członka są w ignorowanych artifacts. Wygląd wykorzystuje istniejący branding TimonConfigs.
- Testy tego etapu: 37 nowych asercji renderowania z rzeczywistą usługą ról (mock transportu Discord), 60 OAuth, 59 pobierania; 12 przypadków HTTP panelu, 4 szerokości nagłówka, 18 widoków stanów testowych i 35 kontroli storefront. Wszystkie przeszły, podobnie PHP lint, JS/static oraz git diff --check. Sprawdzono długie nazwy, brak avatara, niedostępność API, fokus i brak wycieku prywatnych danych.
- ZIP motywu 0.9.0 (82 pliki) i Core 0.7.0 (12 plików) są zgodne bajtowo ze źródłami. Instalacja tych paczek na oddzielnym WordPressie 6.8.3/PHP 8.2 przeszła **68 asercji**, w tym nową kartę logowania i domyślnie wyłączony OAuth; środowisko testowe usunięto. Manifest: `artifacts/dashboard-release-manifest.json`; aktualne kopie ZIP odświeżone. Nieznane pozostają rzeczywiste wyniki na Hostingerze i Discordzie.
- OAuth pozostaje OFF do konfiguracji/testów prawdziwej aplikacji. Nie zmieniono usług autoryzacji, płatności ani nadawania ról; produkcyjny hosting pozostaje bez zmian. Raport: `docs/MEMBER_DASHBOARD_0.9.md`. Następny etap integracji: izolowany HTTPS staging i realne konta/role.

## Wymaganie następnej sesji Astra Medium — panel członka

- Na polecenie właściciela kod 0.8.0 / Core 0.6.0 wypchnięto na `main` (`4138b9b`), zachowując historię. Hosting i działająca baza WordPress nie zostały wdrożone ani zmienione przez ten push.
- Właściciel wskazał zrzuty coldz.pro jako wzorzec późniejszej zmiany UX: Member login → centralna karta → autoryzacja Discord → dashboard z avatarem/nazwą, aktualnym statusem i szybkimi akcjami.
- Zapisano konkretny handoff i kryteria odbioru w `docs/ASTRA_MEMBER_UX_HANDOFF.md`. Należy rozszerzyć istniejący moduł, użyć stylistyki TimonConfigs i zachować faktyczne plany Free/Monthly/Lifetime oraz integracje. Nie dodano nowego UI, nie aktywowano OAuth ani nie zmieniono płatności.
- Bezpośrednie odczyty publicznych coldz.pro i /login odrzuciło proxy (`403` przy zestawianiu tunelu). Analiza dotyczy dostarczonych zrzutów, nie zweryfikowanego działania obcego zaplecza. Zmiana obejmuje wyłącznie dokumentację; sprawdzenie: linki lokalne i `git diff --check`.

## Member library implementation checkpoint — theme 0.8.0 / Core 0.6.0

- Continued PR #1 on `feature/member-library-oauth-handoff`, preserving the 0.7.1 storefront and original private-upload work. The PR head was read from Git; GitHub's GraphQL API was unavailable. Review findings are in `docs/PR_1_REVIEW.md`.
- Hardened the existing private-file module: multipart management, 0/1/10-file handling, actual byte/type/content checks, archive rejection, quotas, storage locks, confirmation, order/version/notes, checksum and audit history. Local Docker has a separate private volume outside the public web root. Database/source backups are ignored under `artifacts/backups/member-library-*`.
- Added disabled-by-default Discord OAuth2 with encrypted tokens, single-use state, optional explicitly verified PKCE, unique Discord identity binding, read-only guild-role checks, a 60-second role cache and fail-closed errors. Upgrade.chat remains the source of paid roles. Existing WordPress accounts are not linked by email/name or implicitly attached to another Discord identity.
- Added session-bound, signed, one-use downloads, short expiry, replay/IDOR checks, fresh bounded server authorization and descriptor checksum verification. Public filenames are separate from opaque storage names. Legacy checksumless files must be revalidated.
- Extended catalogue cards/metadata/sorting and member Profile/My Downloads pages, Browse Memberships, role refresh/reconnect/logout, noindex/cache protections and owner-only data. Draft releases remain drafts; no real licensed script was invented.
- Real browser screenshot is saved at `wp-content/themes/timonconfigs/screenshot.png`, exactly 1200x900. It captures actual WordPress DOM with documented browser-only compact Home styling to include Header/Hero/Pricing/Footer; untouched full-page 1440/390 screenshots are retained in ignored artifacts.
- Zabezpieczono pracę checkpointem `9d242ef` na gałęzi PR #1. Dokończono test rzeczywistego dostarczania bajtów, ochrony przed replay i podmianą deskryptora; nie zmieniano produkcji.
- Testy PHP: OAuth 60 asercji w trybie produkcyjnym i stagingowym (mock HTTP), prywatne pliki 61, prywatne pobieranie 59, prezentacja katalogu 24 oraz istniejące moduły 220 — łącznie 424 różne asercje. Rzeczywiste multipart uploady: 30 asercji; panel członka: 8 przypadków; storefront: 35; pełne URL/SEO/Gutenberg: 55. Wszystkie zakończone sukcesem. Poprawiono testowy licznik audytu na dokładną liczbę rzeczywistych operacji i przywracanie reguł permalinków po izolowanym teście bez wtyczki.
- OAuth nie ujawnia Discord ID w loginie/author slug WordPressa. Dodano walidację błędnych odpowiedzi token endpointu, prawidłowy dokumentowany endpoint OAuth i możliwość testowania na jawnie oznaczonym HTTPS stagingu przed potwierdzeniem produkcyjnym. Funkcja nadal domyślnie wyłączona.
- Ostateczne ZIP-y 0.8.0 / 0.6.0 zawierają odpowiednio 82 i 12 plików, zgodnych bajtowo ze źródłami; integralność archiwów potwierdzona. Instalacja i aktywacja tych paczek na osobnym WordPressie 6.8.3/PHP 8.2 przeszła 66 asercji, zachowując treści i statusy przy ponownym setupie. Środowisko testowe zostało usunięte, istniejący lokalny WordPress działa. Lokalny helper przeszedł kontrolę 27 tras; PHP lint, JS/static checks i git diff --check są zielone. Manifest SHA-256 i raporty znajdują się w ignorowanym `artifacts/`.
- Końcowe poprawki obejmują sprzątanie replay claims przez API WordPressa (także persistent object cache), komunikat o brakujących stronach członka po aktualizacji aktywnej wtyczki oraz zgodność nagłówka ze starszym/nieaktywnym Core. Dodatkowy przegląd samej końcowej delty nie wykazał regresji; nie zastępuje to audytu rzeczywistego środowiska produkcyjnego.
- Wymagane przed aktywacją: prawdziwe client/guild/role IDs i sekret, test realnego Discord consent/refresh/logout i odebrania ról, inspektor binarnych formatów, ustawienia hostingu/cache, prawdziwe linki produktów Upgrade.chat, przegląd prawny i zgoda na produkcję. Szczegóły: `docs/MEMBER_LIBRARY_0.8.md`.

## Homepage correction — theme 0.7.1 / Core 0.5.0

- Investigated the supplied Hostinger screenshot showing only the default WordPress welcome post between the themed header/footer. WordPress uses `front-page.php` for both static Home and latest-posts mode; the old loop incorrectly used blog post content as the Home body.
- Latest-posts mode now renders the existing ten premium Home sections. Authored static Home content remains authoritative, empty static Home keeps its default fallback, and a separate Blog page still shows posts. Schema section visibility follows the content actually rendered in latest-posts mode.
- Added an administrator-only setup notice for inactive/outdated Core, missing pages, invalid static Home, and missing/stale navigation assignments. The notice links to the available Plugins/Tools/Reading/Menus screens and performs no writes.
- Added `tests/front-page-browser.cjs`: ten cases across desktop/mobile passed, covering latest posts, authored Home, empty Home, separate Blog, and latest posts with Core inactive. Reading/plugin settings were restored and owned fixtures removed. Screenshots are in ignored `artifacts/front-page-posts-{1440,390}-after.png`.
- Added `tests/setup-notice-integration.php`: 29 assertions passed across active, inactive, and outdated Core. No Reading settings, theme modifications, checkout settings, or publication states were changed by the notice.
- Gutenberg visibility integration passes 14 assertions, including stale static-page metadata in latest-posts mode. PHP lint, JavaScript syntax, and static checks pass. Rebuilt theme 0.7.1 ZIP contains 80 files matching the tested source; fresh package installation passes 62 assertions with Core 0.5.0 unchanged. Refreshed current-theme ZIP copies.
- See `docs/HOME_FIX_0.7.1.md` for the precise Hostinger admin steps. The preview host could not be inspected directly because its request was rejected at the environment proxy. No remote settings, files, database, payments, or roles were changed.

## Interrupted-session recovery — theme 0.7.0 / Core 0.5.0

- Recovered the uncommitted 0.7.0 work in the existing checkout without resetting, cleaning, or replacing earlier implementation. The previous committed baseline was `1b71a32` (theme 0.6.0 / Core 0.4.0).
- The recovered implementation extends the existing catalogue with seven game collections, platform taxonomy, release metadata, combined filters, and three unpublished Apex draft structures. It adds ten native Home section blocks, editable media and SEO controls, explicit non-destructive site provisioning, and Cookie/Lifetime legal pages.
- `docs/COMPLETION_REPORT_0.7.md`, `docs/CMS_GUIDE.md`, `docs/INSTALL_UPDATE.md`, and the 25-route `docs/URL_STATUS.md` preserve the implementation and prior observed checks. These are evidence of local verification, not a production deployment.
- Local WordPress 6.8.3/PHP 8.2 and MariaDB 11.4 remain running. Recovery checks confirmed Home HTTP 200, static validation, JavaScript syntax, and native PHP lint for all 57 theme/plugin PHP files.
- Private local database/source backups remain in ignored `artifacts/backups/`; they are excluded from commits and release archives. No production database, billing state, or Discord configuration was accessed or changed.
- Saved the recovered source, tests, and documentation in checkpoint `9374ce6` after a targeted secret/private-data check. Generated archives and private backups were excluded.
- Fixed review metadata registration: rating, config label, and demo status now work through the authenticated WordPress REST API. Added 17 create/update/read/delete, sanitization, malformed-input, and authorization assertions.
- Fixed platform collection forms to display and retain the current platform, matching game/device behavior. Added query and browser regression checks for all three collection contexts.
- Fresh WordPress integration verification passed 217 assertions: catalogue 42, SEO 21, media 24, installer 87, blocks 12, editorial 17, and checkout validation 14. Checkout tests do not contact a payment provider or change billing state.
- Fresh browser verification passed 55 route/viewport checks across all 25 required URLs, the existing 35 storefront checks, and focused inline/optional-modal/YouTube playback tests. All 25 local URLs return HTTP 200; the unconfigured Monthly and Lifetime handoffs intentionally return HTTP 503.
- Rebuilt theme 0.7.0 (79 files) and Core 0.5.0 (8 files) ZIPs. Archive integrity and byte-for-byte equality with the tested sources pass; both current ZIP copies were refreshed.
- Repeated read-only public-site and invitation probes on 2026-10-22. Both remain blocked at the environment proxy. Public HTTP status, actual deployment, and invite validity are unknown; no purchase or production write was performed.
- Added `tests/release-installation.sh`; actual ZIP installation and activation pass 62 additional assertions on a fresh, isolated WordPress 6.8.3/PHP 8.2. The test uses an internal network and its own database/filesystem, preserves the default draft Privacy Policy, and verifies repeated installation without content loss. All temporary resources were removed and the existing local stack remains healthy.
- Logical checkpoints: `9374ce6` preserves the interrupted implementation; `6e7ddcb` records the review REST/platform filter fixes; `eb380d6` records ZIP-installation verification and handoff documentation. The setup-helper correction is saved separately after these checkpoints.
- Fixed one additional onboarding mismatch: setup's HTTP smoke checks now report and skip intentionally unpublished known editorial pages instead of failing on WordPress's default draft Privacy Policy. Published and missing routes still fail on unexpected HTTP errors. The real local setup passes 25 routes; isolated script regressions verify draft-policy skipping and failure for unexpected HTTP 500/404 responses. No page status was changed for these checks.

## Repository audit

The selected `Timoon3k/timonconfig` repository and its GitHub remote contained no files, commits, branches, previous theme, plugin, tests, mockup files, or project documentation. No earlier implementation was available to preserve. Development was started inside the existing checkout at `/workspace/timonconfig`; no parallel project was created.

The supplied visual reference is used only for layout and art direction. Its fictional metrics, reviews, character artwork, and unsupported claims were not copied. The supplied three MP4 files are real vertical Apex Legends gameplay clips. They contain no reliable device/config metadata, so their public labels remain neutral until the owner confirms the correct Cronus/XIM mapping.

## Completed storefront foundation

- Custom WordPress theme foundation with dark obsidian/gunmetal surfaces, orange CTA styling, blue accents, responsive layout, keyboard focus styles, and reduced-motion support.
- Home page with premium hero, device overview, Apex Legends showcase, pricing preview, and final CTA.
- Pricing page with Free (€0), VIP Monthly (€40/month), VIP Lifetime (€100 one-time), honest renewal language, comparison table, and checkout FAQ.
- Three supplied Apex videos added with generated posters, lazy images, `preload=none`, and an accessible native-dialog video player. Titles, device labels, and uploads are editable in the Customizer.
- Custom business plugin with admin-managed Upgrade.chat product URLs, strict HTTPS/host validation, plan allowlist, non-cached 302 handoff, and a safe unavailable state. No payment completion or Discord role is inferred from browser redirects.
- WordPress title support, basic meta description, canonical, Open Graph, Organization schema, and WordPress core sitemap compatibility.
- Static validation script covering required files, checkout safeguards, unsupported marketing claims, media presence, reduced-motion support, and JavaScript syntax.

## Completed catalogue and sales pages (0.2.0)

- Editable `Config` custom post type with Gutenberg/REST support, revisions, featured images, Game and Device taxonomies, and structured compatibility fields.
- Server-rendered catalogue at `/configs/` with allowlisted search, game, device, and access-tier filters, pagination, responsive cards, empty state, and individual config pages.
- Stable SEO landing URLs for Apex Legends, Cronus Zen, and XIM Matrix. Core term links and paginated rewrites resolve to the short public URLs.
- Games overview using only the currently supported Apex Legends collection; no fictional games or availability claims were added.
- Dedicated VIP Monthly and VIP Lifetime landing pages with clear recurring/one-time language and CTAs routed through the existing safe Upgrade.chat handoff.
- Page-specific canonical URLs and descriptions for catalogue/taxonomy views, visible breadcrumbs, and BreadcrumbList structured data.
- Catalogue date values now reject invalid calendar dates in addition to malformed input.
- Local setup now creates Games and both VIP plan pages idempotently and adds Games to the primary navigation.
- Standalone FAQ and Contact pages with honest compatibility, checkout, Discord, and support guidance. Contact details remain hidden until a real Discord URL and production admin email are configured.
- Terms of Service, Privacy Policy, and Refund Policy baseline pages with editable page content and footer navigation. These drafts require owner/legal review for the operating entity and target markets before production.
- Dedicated Video Showcase page using the three supplied first-party clips, lazy posters, on-demand MP4 loading, and the accessible dialog player. Device labels remain explicitly pending verification.
- Two Gutenberg block patterns provide an editable premium CTA and FAQ starter for future page editing.

## Reference-led visual revision (theme 0.3.0)

- Reworked the Home composition against the supplied mockup: compact wordmark header, wide cinematic hero, orange/blue lighting, clipped four-column information strip, working search and quick filters, compact collection tiles, three pricing cards with Monthly emphasis, five benefit columns, first-party video cards, FAQ, landscape CTA, and compact footer.
- Recreated decorative hero and landscape artwork from the visual reference as optimized WebP assets. These are illustrations, not gameplay evidence. The supplied screenshot is not a source design package: the fallback wordmark is typographic, and the new hero is recreated rather than the original artwork.
- Preserved the actual €0 / €40 per month / €100 one-time offer and existing Upgrade.chat endpoints. Unsupported game releases, invented statistics, and testimonial quotes from the reference were not added; first-party gameplay occupies the testimonial row.
- Replaced the modal player on BOTH Home and Video Showcase with a shared native inline video card. MP4 sources are attached only on interaction, playback stays in the card, other videos pause when one starts, and native controls remain available. Failed media offers an in-card retry; no-JavaScript users receive direct video links.
- Added Customizer image controls for the hero and all three video posters. Existing video/title/device settings remain reusable. Updated the local menu to Home / Configs / Games / Pricing / FAQ / Contact.
- Fixed nested logo links by rendering the uploaded logo image within the existing single brand link.
- Validation: PHP lint passed for all theme PHP files; static checks, JavaScript syntax, and Git whitespace checks passed. The new `tests/storefront-browser.cjs` passed 12 route/viewport combinations at 1440, 768, and 390 px, real inline media playback, pause coordination, keyboard menu/FAQ behavior, homepage search, and media-failure retry UI. No console errors or horizontal overflow were detected in the normal cases.
- Theme ZIP: `artifacts/timonconfigs-theme-0.3.0.zip`; Core remains 0.2.0. Both current ZIP copies are refreshed and archive-validated. No production deployment was performed.

## Editable reference content (theme 0.4.0 / Core 0.3.0)

- Added a replaceable TimonConfigs image wordmark and seven premium game covers based on the supplied visual direction: Apex Legends, Call of Duty, Fortnite, Rainbow Six Siege, Rust, EA Sports FC, and NBA 2K. The Finals was removed as explicitly requested.
- Home and Games share one responsive game strip. Apex is the only active collection; future games are labelled “Coming soon” and have no misleading product links. Every cover can be replaced in the Customizer.
- Added Gutenberg-enabled, non-public `Reviews` and `FAQs` content types. Approved entries can be ordered and published by an editor. Until a real review is approved, the site displays an honest empty state instead of fabricated testimonials.
- Home now includes the reference Reviews row, while retaining the supplied first-party Apex video showcase with inline playback. FAQ content is shared by Home and the FAQ page and emits matching FAQPage structured data.
- Added the Reviews page and exact primary-menu order: Home, Configs, Games, Pricing, Reviews, FAQ, Contact.
- Added global Customizer fields for Discord, X, YouTube, Instagram, and the creator URL. The footer now carries the requested 2026 TimonConfigs / majewskitomasz.pl credit.
- Static checks, Docker PHP lint, and Playwright passed across Home, Games, Pricing, Reviews, FAQ, Video Showcase, and Configs at 1440, 768, and 390 px. The run also verified seven game covers, honest availability states, reviews empty state, search, keyboard controls, deferred inline video playback, pause coordination, and media-error retry.

## Reference correction pass (theme 0.5.0 / Core 0.4.0)

- Replaced the large review empty state with a compact mockup-style horizontal carousel containing six clearly marked `Sample Review · Demo Content` cards. They are presentation samples, not customer claims, and are excluded from Review/AggregateRating schema.
- Extended the Reviews editor with a secured metadata panel for rating, config/device label, and demo status. Gutenberg content, featured image, publication state, order, and revisions remain available.
- Added working previous/next controls, keyboard-scrollable review content, responsive three/two/one-card layouts, and an honest demo label inside every fallback card.
- Every planned game cover now links to its matching Games roadmap anchor. It still says `Coming soon`, does not link to a fictional product, and cannot return a missing collection page.
- Added editable pricing names, prices, suffixes, descriptions, feature lists, and CTA labels to the site Customizer. Upgrade.chat checkout targets remain owned by the validated Core settings.
- Added editable hero tagline, CTA label, benefit labels, and final CTA copy. Added native Gutenberg patterns for the premium hero and three-plan pricing layout alongside the existing CTA and FAQ patterns.
- Verified in live WordPress that the Review/FAQ post types support Gutenberg and that all four TimonConfigs patterns register successfully.
- Full desktop comparison was refreshed at `artifacts/redesign-1440.png`; responsive browser tests and Docker PHP lint pass.
- The repository contains only a flattened reference screenshot, not the original layered hero, original logo file, or brand favicon source. The current hero and fallback wordmark remain replaceable reconstructions and cannot honestly be called the original assets.

## Measured layout correction (theme 0.6.0 / Core 0.4.0)

- Replaced the accumulating premium CSS overrides with section rules based on measured reference proportions. At 1440 px, the content grid now spans 1360 px instead of 1240 px; Hero retains a separate 110 px text inset.
- Supplied Inter variable and Barlow Condensed WOFF2 fonts locally with their upstream licenses. The previous Inter declaration silently fell back to Arial.
- Added a new 2.65:1 Hero reconstruction with both hands/devices visible on desktop. Existing administrator-selected imagery remains intact. The original brand source files are still missing.
- Restored large central game-title treatments, compact icon/name/tagline pricing composition, Most Popular, and a filtered Free CTA. Added editable plan taglines and kept validated checkout routing unchanged.
- Reduced Games, Pricing, Reviews, FAQ, and video presentation spacing. Kept all three first-party videos inline, deferred, and functional. Restored visible community actions and primary/footer navigation with configured social icons.
- Added `tools/capture-storefront.cjs` for repeatable screenshots and geometry at 1440, 1024, 768, 390, and 320 px. Font loading and zero horizontal overflow were observed at all five widths.
- Read-only access to timonconfigs.pl was blocked by the environment outbound proxy (`403 CONNECT`), including an escalated retry. This does not establish a fault in the public site. Added only timonconfigs.pl/www.timonconfigs.pl to the network configuration draft; live-site comparison remains pending configuration publication.
- See `docs/DESIGN_CORRECTION_0.6.md` for measured section sizes, limitations, and the exact production-access blocker. No production files, database, payments, or roles were modified.
- Validation: PHP lint and static checks passed; Playwright passed 35 route/viewport combinations (7 routes × 5 widths), including local font/art loading, wide desktop grid, mobile keyboard controls, carousel, search, and real inline video playback/retry. Read-only link QA checked 26 unique local links and found a legacy Pricing FAQ anchor. Added the anchor and fixed setup's WP-CLI option from `--url` (global request context) to `--link` (menu destination), then confirmed the rendered FAQ navigation uses `/faq/`.
- Packaged `artifacts/timonconfigs-theme-0.6.0.zip`; archive integrity passed and all 61 payload files match the source tested in local WordPress. Refreshed current-theme ZIP copies. Core remains 0.4.0 and was not modified.

## Validation results

- JavaScript syntax: passed with `node --check`.
- MP4 inspection: passed; all three are H.264/AAC Apex clips, 28–37 seconds, vertical 480×854 or 576×1024.
- Static structure/security/content assertions: passed.
- PHP source parsing: all 12 PHP files parsed without syntax errors using Prettier's PHP parser. Its format check reported style differences because the source follows WordPress conventions rather than Prettier formatting.
- Host PHP CLI is absent, but native `php -l` and live WordPress smoke tests now run successfully inside the pinned Docker environment.

## Local WordPress environment

- WordPress 6.8.3, PHP 8.2.29, Apache, and MariaDB 11.4 run in Docker.
- Theme and plugin activation passed.
- Native `php -l` passed for all 31 PHP files inside the WordPress container.
- Home, Pricing, Games, both VIP pages, the catalogue, three primary taxonomy landings, and config detail returned HTTP 200.
- The unconfigured Monthly checkout returned the intentional HTTP 503 unavailable state instead of redirecting to an invented URL.
- Playwright rendered Home at 1440 px and 390 px and Pricing at 1440 px without browser console errors.
- Browser interaction checks passed for the mobile menu, video dialog, Escape close behavior, on-demand video source, and 390 px horizontal overflow.
- Catalogue and sales browser checks passed at desktop and mobile widths without console errors or horizontal overflow. The Monthly filter returned only the matching local fixture.
- Canonical and BreadcrumbList checks passed for Games, catalogue, Apex/Cronus/XIM landings, and config detail.
- FAQ, Contact, Video Showcase, Terms, Privacy, and Refund pages return HTTP 200 with unique descriptions. The showcase player opens on demand and closes with Escape in browser tests.
- Home, Pricing, and catalogue passed the 768 px tablet rendering pass without console errors or horizontal overflow.
- Reusable setup is available through `bash tools/setup-local-wordpress.sh`.

The three catalogue entries currently visible in the Docker database are local-only preview fixtures. They are not stored in the distributable plugin/theme and must not be treated as production product data.

## Required before production

- Confirm which supplied video corresponds to Cronus Zen S30, XIM Matrix Controller, and XIM Matrix MnK; then enter accurate device/config version/platform/recording metadata.
- Supply the real VIP Monthly and VIP Lifetime Upgrade.chat checkout URLs.
- Test theme and plugin activation on a WordPress staging installation with PHP 8.1+.
- Complete Discord OAuth2, current guild-role verification, protected premium delivery, consent analytics, and final legal/security review on an isolated staging WordPress. These are the agreed Astra Medium tasks.
- Do not deploy to production or modify current payments/roles without explicit approval.

## Hostinger deployment preparation

- Deployment archives are rebuilt after each completed release and validated with `unzip -t`. Version 0.2.0 includes the catalogue and VIP landing work.
- The Hostinger Connector skills are available, but the current session exposes no callable Hostinger hosting or WordPress tools. No account state could be read and no Hostinger resource was changed.


## 2026-10-24 — Script-first Owner upload UI (PR #5)

- Existing configurations present upload of protected scripts before general edit fields, and public custom artwork is folded into optional details. Added clear Owner-facing diagnostic when TIMONCONFIGS_PRIVATE_STORAGE_DIR is absent; no fallback to public media or disabling file checks.
- Version bump: Core 0.8.3, theme 0.10.3. Disposable WordPress integration 40048011266 succeeded including Owner assertions, catalog and protected downloads; PHP lint 40048011263 passed. Hostinger folder/root not configured by source changes; production download still unverified.
- Guide: docs/SCRIPT_FIRST_OWNER_UPLOAD_0.8.3.md.

## 2026-10-24 — ZNBK ZIP preflight without bypassing proprietary inspection (PR #6)

- User saw `Archive, executable, and shell payloads are not accepted` while uploading `.znbk`. In Core 0.8.3, blanket ZIP signature rejection ran before the proprietary inspector. Updated Core 0.8.4: strictly bound ZIP envelopes are considered ONLY for `.znbk` and parsed without extraction. Ordinary ZIP/executables, traversals, dangerous nested entries and decompression attacks remain rejected. External trusted `timonconfigs_private_file_scan` approval remains mandatory even for valid ZIP-backed ZNBK; upload/download both inspect.
- Test source expanded: `tests/private-files-integration.php`. GitHub Actions run `40050567738` passed (private-files: 73 assertions, protected downloads: 69, catalog: 45, Owner editor: 223); PHP lint run `40050567733` passed. Fixtures are synthetic, no real user's `.znbk` was inspected or uploaded and no production Hostinger scanner was configured.
- Guide: `docs/ZNBK_ZIP_CONTAINER_INSPECTOR_0.8.4.md`. GitHub source change alone does NOT make this format downloadable until safe inspector is installed and real sample passes.

## 2026-10-24 — GPC legacy text/plain upload fix (PR #7)

- Root cause from Owner screenshot: file classified `text/plain` but strict Unicode/control predicate treated a legitimate-looking `.gpc` as invalid. Core 0.8.7 accepts source-style text/plain `.gpc` with control bytes VT/FF/ESC and legacy undefined CP1252 bytes while retaining original bytes and SHA-256. TXT/JSON policies, private storage, MIME active code/ZIP/ELF blocking and scanner veto remain. No actual user script bytes were available for verification.
- Theme 0.10.6 corrects the stretched upload CTA by moving asynchronous status messages to their own grid row. Owner UI wording updated.
- GitHub Actions **40131505392 PHP lint** and **40131505429 WordPress integration** completed successfully. Integration: text uploads 85 assertions, private files 73, downloads 69, catalog 45, Owner 223, further suites passed. Mocked Discord used; Hostinger production not tested.
- Deployment guide: docs/GPC_LEGACY_UPLOAD_FIX_0.8.7.md. Update theme/Core together after backup; check real `.gpc` via logged-in Owner and restricted access. No actual production deployment or account/billing change.

## 2026-10-24 — Core 0.8.8 raw private configuration uploads (PR #8)

- User wanted to stop `text/plain (binary or unsupported encoding)` GPC rejection and upload original files without UTF/MIME parsing. `TC_Private_Files::inspect` now validates approved filename/extension, size and SHA-256, with optional scanner veto only; it stores GPC/ZNBK/XIMMR/TXT/CFG/INI/JSON bytes byte-for-byte, including binary. Removed unused UTF and ZIP parser helpers; Owner UI updated. **Do not advertise as unrestricted public uploads**: extension allowlist, private root, Owner auth/nonce/capability, storage quotas, signed attachment downloads and Discord tier authorization remain.
- Trusted private vault no longer inspects file content for malware; only trusted authorized Owner should publish vetted files. Risk accepted only within this restricted workflow. Source change not a Hostinger update.
- Disposable WP integration run 40132642307 passed: 170 assertions raw config, 79 private-file storage, 69 private downloads, 45 catalog, 223 Owner; PHP lint 40132642942 passed. Follow-up lint/integration rerun after dead helper removal pending; see PR #8. Real uploaded user GPC not provided.
- Documentation: docs/RAW_OWNER_PRIVATE_CONFIG_FILES_0.8.8.md. Upgrade plugin version and validate real Owner upload after backup; production and provider Discord/Stripe untouched.

## 2026-10-25 — Discord Owner OAuth reliability and pilot diagnostics (PR #9)

- Detected two distinct conditions: (1) forced Owner role checks per mutation cause 10-second throttling and a transient API failure used to remove a still-valid 60-second read cache; (2) production Restricted Pilot intentionally denies OAuth2 for non-allowlisted users, and existing WP sessions with conflicting Discord bindings are rejected.
- Core 0.8.9 preserves only valid, unexpired cached role data for a read-only Owner UI after a forced API failure, while mutations still call fresh verification and deny on outage or role removal. Expired/mismatched cache is purged. Discord HTTP 429 Retry-After is respected (bounded backoff) and Owner receives an accurate retry countdown. OAuth error pages now distinguish beta allowlist exclusion and account-binding conflict. No role or payment automation changed.
- PHP syntax run `40137679376` succeeded. Disposable WordPress run `40137679440` succeeded: Discord integration 127 assertions, Owner verification 23, Owner editor 223, protected downloads 69, private files 79, raw files 170 and other suites. These are simulated Discord HTTP calls, not production OAuth2 or real Hostinger response.
- Guide: `docs/DISCORD_OAUTH_OWNER_RELIABILITY_0.8.9.md`. Before disabling pilot, perform real login and access-denial checks on canonical HTTPS `timonconfigs.com`, check identical OAuth2 redirect URI, WP Site/Home URL, and LiteSpeed cache exclusions. GitHub merge alone is not Hostinger deployment.


## 2026-10-25 — Owner identity allowlist without Discord role polling (PR #10)
- TC_Owner_Editor and member dashboard use TC_Discord::can_manage_configs_local(). It checks authenticated WordPress session, immutable Discord ID binding, approved Owner IDs, Restricted Pilot if active, and encrypted OAuth token, but never requests guild roles. Back-to-back Owner Save/Upload/Publish no longer suffers Discord API cooldown.
- Unrelated Free/Monthly/Lifetime download entitlements continue existing Discord API checks and signed downloads. No broad admin bypass, no guest/Staff/VIP management; nonces and scoped capabilities remain.
- Core 0.8.10. PHP lint run 40139402751 passed. Disposable WordPress run 40139402756 passed: Owner editor 169 assertions, Owner identity 20, Discord 127, protected downloads 69, private files 79, raw config uploads 170, shared VIP 24. Live Hostinger not tested.
- Guide: docs/OWNER_LOCAL_AUTHORIZATION_0.8.10.md. Before deployment review Approved Owner user IDs, ideally sole publisher; confirm Free Member cannot manage.
