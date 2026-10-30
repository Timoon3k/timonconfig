# Bieżący etap — Core 1.1.12 / theme 0.15.0, 2026-10-30

Przeczytaj `docs/COLLECTION_URLS_1.1.12.md` i początek PROGRESS. Kontynuuj gałąź `feature/device-game-collections-20261030`, checkpoint `0a2be44`; końcowy commit sprawdź w Git. Nie przywracaj starych kotwic jako głównych docelowych adresów fraz.

Gotowe 14 rzeczywistych stron gra + urządzenie i `/free-configs/`, frazy/aliasy wyszukiwarki, rzeczywiste przecięcia kart, paginacja, canonical/robots/XML, renderowana migracja znanych lokalnych linków. Edytowalne treści i autorskie meta zachowane; prywatne pobrania i płatności bez zmian. Nowe strony powstają po wejściu administratora do kokpitu; konflikt z autorską stroną o tym samym slugu jest pomijany, nie nadpisywany.

Zaliczone 254 PHP + 12 import Rank Math, 534 browser / 32 widoki / 6 przekierowań / 7 XML, 24 dodatkowe widoki, lint123/static/JS/YAML/diff, 98 fresh ZIP. Finalne paczki Core1.1.12/theme0.15.0 gotowe w artifacts, manifest `collections-release-manifest-20261030.json`. Testowe 18 konfiguracji usunięte, ustawienia Rank Math przywrócone i zależność nieaktywna. Nie deklaruj wdrożenia tego wydania na Hostingerze ani aktualizacji main.

Najbliższy krok: po autoryzowanej instalacji obu paczek otworzyć kokpit, sprawdzić nowe strony i karty, zapisać ustawienia sitemap i wyczyścić cache. Bez ponownego importu poprzedniego JSON-a. W GSC sprawdzić mapę, a po zwolnieniu limitu ręcznych próśb priorytetowo cztery nowe adresy Apex/Call of Duty × XIM/Cronus, jeśli kwalifikują się do indeksowania. Opublikowana, ale pusta lub wykluczona kombinacja celowo pozostaje noindex. Home można opisać szerzej, precyzyjne frazy rozwijać na nowych kolekcjach; nie nadpisuj autorskiego tytułu automatycznie. Nie obiecuj daty indeksowania lub TOP1.

---

# Bieżący etap — SEO, Core 1.1.11 / theme 0.14.4, 2026-10-30

Przeczytaj `docs/SEO_AUDIT_2026-10-30.md`, przegląd eksportu w `docs/seo/` i początek PROGRESS. Właściciel podał wynik Home „Adres URL jest w Google”. Nie twierdź, że witryna nie jest zaindeksowana: nie zmierzono pozycji na konkretne zapytania, CTR ani CWV. Odczyt produkcji i Google zatrzymał proxy CONNECT 403, nie serwer strony.

Gotowe: dwa eksporty Rank Math, adapter metadanych zachowujący autora, Home → XIM Matrix Apex Legends, Contact/#community → Cronus Zen Discord, treści/linkowanie i poprawka map XML dla częściowych `stdClass` oraz stron członka i użytkowych Woo. Fragment Contact nie jest nową podstroną. Nie zniesiono noindex prywatnych kont, filtrowanych lub pustych kolekcji.

Zaliczone: 85 PHP SEO, 12 natywnego importu, 158 browser / 9 widoków / 7 XML, 118 lint PHP, static/JSON/YAML/JS/diff i 98 fresh ZIP. Ustawienia lokalnego fixture Rank Math przywrócone, testowa wtyczka nieaktywna. Paczki Core 1.1.11 i theme 0.14.4 gotowe w artifacts; manifest `seo-20261030-release-manifest.json`. Checkpoint `6dc5e47`; końcowy commit/ref odczytaj z Git. Brak potwierdzonego wdrożenia na Hostingerze i importu produkcyjnego.

Po autoryzowanej aktualizacji motywu/Core: zachowaj świeży eksport i kopię bazy, porównaj ustawienia, zaimportuj SEO-only (zastępuje całe trzy sekcje). Indywidualne metadane Rank Math nadal mają pierwszeństwo. Zapisz Sitemap Settings, wyczyść cache witryny i sprawdź rzeczywiste meta/canonical/robots Home/Contact oraz mapy XML. Następnie w GSC porównaj zapytania, docelowe URL-e, kraje i urządzenia w okresach 14–28 dni. Rozwijaj własny publiczny poradnik XIM Matrix/Apex i opisy dostępnych wydań; nie obiecuj TOP 1. Zachowaj działające płatności, role, prywatne pliki i autorskie metadane.

---

# Bieżący etap — Core 1.1.10 / theme 0.14.3, 2026-10-30

Kontynuuj poprawkę anulowania i spójności ról, kod `66fe347` (baza main `7ea5e2b`); przeczytaj początek PROGRESS i `docs/MEMBERSHIP_SYNC_1.1.10.md`. Właściciel potwierdził **natychmiastowe anulowanie w Stripe**. Zwykłe zatrzymanie przyszłych odnowień nadal zachowuje opłacony okres.

Nowe: blokada jednego pracownika na członka, sprawdzanie aktualnego dostępu i właściciela blokady przy zdalnych zapisach, Free przed usunięciem wygasłego VIP, unieważnienie cache po moderacji oraz kolejka po zdarzeniach subskrypcji/świeżym odczycie Discorda. Wcześniej opłacony klient między rolami widzi Membership update pending; nie zmieniano download allowlists i nie nadawano fikcyjnego Free. reCAPTCHA pozostaje usunięta.

Zaliczone353PHP,117lintPHP,27widokówprofilu i98freshZIP; paczka Core1.1.10 gotowa. Końcowy commit/push odczytaj z Git ref; wyniki szczegółowe w PROGRESS. Następny krok: aktualizacja Core, kontrola Action Scheduler/WP-Cron i potwierdzenie prawdziwych webhooków oraz stanu ArraySubs po anulowaniu. Nie twierdź, że lokalny cutoff zatrzymał obciążenia. Brak potwierdzonej produkcyjnej instalacji lub rzeczywistego ArraySubs2.0.9 acceptance; dokumentacja/source zablokowane proxy403. Zachowaj zamówienia, klientów, Ownera, bieżącą konfigurację operatorów i motyw0.14.3.

---

# Aktualizacja checkoutu — theme 0.14.3 / Core 1.1.9, 2026-10-30

Przy podsumowaniu płatności jest angielska informacja o 1–10 minutach na weryfikację i automatyczne nadanie rangi Discord. Checkout nie obiecuje już Instant access. Zmiana dotyczy treści motywu; instalacja na Hostingerze pozostaje niepotwierdzona.

---

# Bieżący etap — Core 1.1.9, 2026-10-30

Właściciel zlecił usunięcie reCAPTCHA i push main. Moduł TC_Woo_Captcha i jego hooki zostały usunięte; wcześniejsze zalecenia obowiązkowej CAPTCHA są historyczne i nie mają zastosowania od1.1.9. Nie przywracaj ich. Obecna architektura WooCommerce/ArraySubs, Discord i motyw0.14.2 pozostają. Wyniki testów i stan publikacji: początek PROGRESS.md. Push GitHub nie potwierdza instalacji na Hostingerze.

---

# Następna sesja — Core 1.1.8 critical-error hotfix, 2026-10-29

Kontynuuj **Core 1.1.8 / theme 0.14.2**, kod `528c12d` już na GitHub main (zdalny ref potwierdzony), gałąź `fix/core-117-critical-error`. Przeczytaj początek PROGRESS i `docs/HOTFIX_CORE_1.1.8.md`; paczka Core1.1.8 gotowa i sprawdzona, instalacja na Hostingerze pozostaje niepotwierdzona. Nie przywracaj wycofanych klas TC_Billing: od 1.1.3 jedynym backendem płatności jest WooCommerce/ArraySubs. Poprzednie niezakończone prace są bezpieczne w checkpointach `e894246` i `f60f228`.

Naprawiono niezdefiniowane REVOKED/ROLE_GRANTED_AT (fatal odtworzony lokalnie), obsługę wyjątków ArraySubs, anulowanie Monthly mimo Lifetime, ochronę nowego zakupu przed starszym audytem i zachowanie wymaganych pól checkoutu. Obowiązkowa reCAPTCHA pozostaje. Zaliczone354asercjePHP,118plikówlint,64browser/12widoków,98freshZIP; szczegóły na początkuPROGRESS i w raporcie hotfixu. Astra Medium bez blokera wąskiego diffu; rzeczywisty ArraySubs/Stripe/PayPal acceptance pozostaje niepotwierdzony. Zachowaj wszystkie zamówienia, klientów, rolę Owner i działające integracje.

Najbliższy priorytet: bezpieczna aktualizacja rzeczywistego Core i odczyt prywatnego PHP logu; potem kontrolowane sprawdzenie profilu, kolejki, webhooków, zakup → VIP/Free, anulowanie → koniec okresu → Free, audyt Staff/Owner i ponowny zakup. Nie deklaruj skutecznego zatrzymania odnowień wyłącznie z lokalnego cutoffu. Błąd helpera anulowania wymaga przeglądu; ta poprawka nie wprowadza automatycznego ponawiania takiego anulowania.

Nie ma aktywnego połączenia Hostinger/Stripe/PayPal; nie potwierdzono wdrożenia produkcyjnego ani rzeczywistego odnowienia/anulowania. Dla WooCommerce Stripe użyj webhooka pokazanego przez zainstalowaną bramkę (oficjalny `/?wc-api=wc_stripe`), bez przywracania natywnych endpointów. reCAPTCHA v2 site/secret tylko w prywatnej konfiguracji. PayPal Sandbox testuj na odizolowanej konfiguracji, nie przełączaj aktywnych klientów Live.

---

## Historyczne przekazania — bieżący etap powyżej ma pierwszeństwo

# Następna sesja — Lifetime i PayPal pending, 2026-10-27

Kontynuuj **Core0.9.8/theme0.11.3**, najnowszy wpis PROGRESS i `docs/LIVE_WEBHOOKS_AND_SEARCH_2026-10-27.md`. Zdalny main8c15edd (Stripe orphan recovery) scalono z legalcontact7e1b602, checkpointc56ada3. Nie nadpisuj żadnej z tych prac; różne0.9.7zostały zastąpione wspólną0.9.8.

Gotowe: Lifetime€80 MostPopular; obaVIPedytowalne250uniqueVIPscripts/owner valuation€10,000; Monthly€29.99bez zmiany billingamounts. JawnyClose unpaid checkout użytkownika/admina próbuje provider-verified cancel nieopłaconej PayPalMonthlyAPPROVAL_PENDING/APPROVED. Błąd/paidrace/brakID zachowuje pending; nie stosuj forcecancel ani lokalnegoresetu. LifetimeCAPTUREnie ma wymyślonego voidorderAPI, nadal wymaga kanonicznejweryfikacji lub wznowienia.

Najbliżej po aktualizacji WP: sprawdź prawdziwy komunikat po Close (userpodał tylko początkowybannerPaypalpending). Poprawny PayPalLivewebhook jest widoczny na screenshotach; WebhookIDwWP musi odpowiadaćaplikacji. Stripeużytkownikpokazałstagingdestination — sprawdzićprodukcyjnyendpointiSigningsecret. SekretywpisujwyłącznieprywatniewWordPressie. Administrator:paymentsetupwskazujebraki; nie omijajreadinessżebypokazaćprzyciski. Nie zmieniono realnychumów/ról aniHostingera.

Zaliczone:850PHP,242browser/24widoki/2redirecty,112PHPlint+JS/static,100freshZIP; paczki0.9.8/0.11.3(manifestoffer-098). AstraMediumwykonaławąskireview/implementację; sukcesPayPalcancelpotwierdzonytylkomockami, bezLive. Nie deklarujfaktycznieanulowanegozamówieniaklienta ani wdrożeniahosta.

SEO: proxyCONNECT403uniemożliwiłodczytprodukcji/Google; nienazywajtegoawariąstrony. Niepotwierdzonoindeksacji/pozycji. WłaścicielpowiniensprawdzićGSCURLInspection/Home/XIM/Apex, sitemap, obaadresyWP/Reading/noindex. Kodnoindexujepustekolekcje ihostingersite.com. DalszySEOcontentpodXIMApex ma byćunikalnyiprawdziwy; nikt nie gwarantujeTop1.

---

## Historyczne przekazania — bieżący etap powyżej ma pierwszeństwo

# Następna sesja — kontakt prawny, 2026-10-27

Core0.9.7 uzupełnia podany kontakt: contact@timonconfigs.com, TimonConfigs, ul. Dąbrowica 21, 05-326 Poświetne, Poland. Zachowaj pisownię użytkownika i działalność nierejestrowaną; wcześniejszy brak adresu/e-maila jest historyczny. Patrz `docs/LEGAL_CONTACT_UPDATE_0.9.7.md`. Migracja admin_init legal-v2 obsługuje stare zapisane puste pola i v1; po aktualizacji wtyczki otwórz kokpitadministrator, potem sprawdź publiczne treści/cache. Lokalnie wszystkie trzy strony publish mają dane, Hostinger bez potwierdzonej instalacji.

Zaliczone: 61PHPpublikator, 161browser/12widoków/2redirecty,100freshZIP, lintzmienionychplików. Nie powtarzaj szerokiego audytu dla zmiany kontaktu. Zachowaj płatności/OAuth/role i dane klientów. Bieżące dalsze zadanie użytkownika: Lifetime€80 jako MostPopular, benefity obuVIP250unikatowychskryptów o deklarowanejwartości€10,000, diagnostyka niewidocznychStripe/PayPal oraz sprawdzenie publicznegoSEO. ZrzutStripepokazujeuciętyURLstaging.timon..., dokładnyerrorcheckoutpending; nie zakładajprzyczyny bezkodu.

---

## Historyczne przekazania — aktualne dane powyżej mają pierwszeństwo

# Następna sesja — opublikowane regulaminy, 2026-10-26

Kontynuuj Core0.9.6/theme0.11.2, kod `2109f39` już wypchnięty na GitHub main (potwierdzony zdalny ref); przeczytaj początek PROGRESS oraz `docs/LEGAL_PUBLICATION_0.9.6.md`. Właściciel wyraźnie zlecił published + push, więc nie przywracaj historycznej blokady draft-only. Trzy regulaminy są faktycznie opublikowane lokalnie. Kod aktualizuje je jednorazowo po wejściu administratora do kokpitu na zaktualizowanej instalacji, z archiwum oryginałów i 301 starych Cookie/Lifetime stron. Ustawienia → Legal documents edytuje dane i ponownie publikuje treści.

Najbliżej: sprawdzić faktycznie zainstalowane wersje na Hostingerze, zaktualizować paczki po zwykłej kopii bazy i odświeżyć cache, następnie potwierdzić publiczne dokumenty/stopkę/przekierowania. Push nie oznacza instalacji WordPressa. Obecne połączenie Hostinger MCP jest niedostępne; nie deklaruj zdalnego wdrożenia. Nie zmieniaj działających płatności/OAuth/umów klientów.

Dane podane: Tomasz Majewski, TimonConfigs, działalność nierejestrowana w Polsce, końcowe EUR29.99/80. Brak adresu (odmowa właściciela) i bezpośredniego e-maila pozostaje nierozwiązanym obowiązkiem ujawnienia, a nie powodem do wymyślania danych lub oznaczania gotowych tekstów Draft. Jeśli właściciel poda kontakt, uzupełnij rzeczywistą konfigurację i opublikuj ponownie; zachowaj późniejsze autorskie edycje. Prawo odstąpienia jest zachowane; brak osobnego waivera/durable-medium flow. Teksty nie stanowią poświadczenia pełnej zgodności prawnej.

Zaliczone: 283 PHP, 139 browser/12widoków/2redirecty, lint111 PHP+JS, 97 instalacji ZIP. Aktualne ZIP-y0.9.6/0.11.2 i manifest `artifacts/legal-096-release-manifest.json`. Wcześniejsze wyniki billingowe są historyczne; rzeczywiste testy Live nadal wymagają weryfikacji operatora/Discorda. Nie powtarzaj szerokiego audytu przy samej zmianie danych kontaktowych.

---

## Historyczne przekazania — bieżące polecenie powyżej ma pierwszeństwo

# Następna sesja — odzyskiwanie checkoutu, 2026-10-26

Kontynuuj aktualny GitHub main z kodem `4f051d4`, Core0.9.5/theme0.11.1; wyniki są na początku `PROGRESS.md`. Przeczytaj `docs/CHECKOUT_RECOVERY_0.9.5.md`. Zachowaj publiczne zakupy, granicę administratora WordPressa oraz dotychczasowe uprawnienia Ownera.

Po aktualizacji faktycznego WordPressa sprawdź **TimonConfigs billing → Customer orders**: odświeżenie, zamknięcie nieopłaconej próby i anulowanie odnowień z zachowaniem opłaconego dostępu. Następnie ustal bezpieczny kod Stripe/HTTP/etap z ponownej próby. Stare brakujące referencje i otwarte PayPal wymagają weryfikacji u operatora, bez wymuszonego resetowania statusu. Nie wykonano realnego anulowania ani instalacji na Hostingerze; zaliczono857 asercji PHP, 32 widoki, 2 checkouty, 6 stron, lint73 i 97 kontroli instalacji ZIP. Kod jest na GitHub main, lecz instalacja na Hostingerze pozostaje niepotwierdzona.

---

# Następna sesja — Discord checkout diagnostics, 2026-10-26

Aktualny kod `5c4e510`, Core0.9.4/theme0.11.1 jest na GitHub main. Zaliczone 263 asercje PHP i 97 kontroli instalacji ZIP; pozostałe wyniki 0.9.3 są historyczne i nie były powtarzane dla samej diagnostyki. Nie zmieniaj działającej logiki publicznych zakupów ani Ownera.

Przeczytaj `docs/DISCORD_CHECKOUT_DIAGNOSTICS_0.9.4.md`. Następny krok to aktualizacja faktycznego WordPressa i ustalenie HTTP/Reference przy ponownej próbie. Token wpisuje właściciel wyłącznie w Live billing, bot musi być członkiem właściwego serwera z Manage Roles i pozycją ponad VIP. Bez statusu nie zakładaj, że chodzi konkretnie o token lub uprawnienia. Nie wykonano realnej płatności, testu bota ani instalacji na Hostingerze.

---

# Następna sesja — publiczne zakupy Live, 2026-10-26

1. Kontynuuj obecny projekt, aktualny GitHub `main` z kodem `fb2142e` i etap Core 0.9.3 / motyw 0.11.1. Sprawdź końcowy commit oraz wyniki na początku `PROGRESS.md`; nie nadpisuj istniejącej pracy.
2. Najnowsze polecenie właściciela dopuszcza zakupy dla wszystkich klientów. Przeczytaj `docs/PUBLIC_LIVE_CHECKOUT_0.9.3.md`: Mode Live + Public website checkout + Enable public Live payments for all verified Discord members. Wcześniejsze ograniczenie do jednego Discord ID jest historyczne; nie przywracaj go publicznym klientom.
3. Zweryfikuj zwykłego klienta: OAuth → wybrany checkout → przycisk Stripe/PayPal → podpisany webhook → właściwa rola → chronione pobranie. Klient nie wymaga Ownera; Free Member nadal wynika z istniejącej weryfikacji Discord. PayPal wymaga ukończonego Stripe acceptance oraz własnych poświadczeń, webhooka i planu Monthly.
4. Gdy wstrzymujesz nowe zakupy, zachowaj Live/publiczny dostęp kont i działającą obsługę umów. Nie kasuj praw, nie anuluj starych subskrypcji i nie wyłączaj cron/cache exclusions. Publiczne zatwierdzenie nie jest automatycznym potwierdzeniem testów lub przeglądu prawnego.
5. Zaliczone: 595 asercji PHP, 24 widoki, 2 checkouty, 6 stron, 97 kontroli instalacji ZIP i lint73. Kod `fb2142e` jest na GitHub main; repozytorium zachowano bez reset/clean. Nie wykonano rzeczywistej płatności ani aktywacji na Hostingerze; push GitHub nie jest dowodem aktualizacji WordPressa.

---

## Historyczne przekazania poniżej — aktualne polecenie powyżej ma pierwszeństwo

# Następna sesja — prosty checkout / ograniczony Live, 2026-10-26

1. Kontynuuj aktualny GitHub `main`: kod `9527129`, checkpoint panelu `5acad52`, Core 0.9.2 / motyw 0.11.1. Push fast-forward potwierdzono zdalnym refem; nie nadpisuj istniejącej pracy. Pełne wyniki są na początku `PROGRESS.md`.
2. Przeczytaj `docs/SIMPLER_LIVE_CHECKOUT_0.9.2.md`. Właściciel wyraźnie dopuścił rzeczywiste płatności Live wyłącznie dla swojego zaproszonego Discord ID. To aktualizuje historyczne zalecenie pierwszych testów wyłącznie na stagingu. Publiczny Live nadal wymaga odrębnego zatwierdzenia; nie zaznaczaj fikcyjnie ukończonych testów, migracji lub przeglądu prawnego.
3. Gotowe w kodzie: szyfrowany panel sekretów, odczyt dwóch Stripe Price IDs, ograniczenie pilota po stronie serwera, bezpieczny powrót OAuth prosto do wybranego checkoutu, natychmiastowe przyciski operatorów bez checkboxów oraz diagnostyka brakujących ustawień. GET-only preflight sprawdza możliwość nadania VIP przed stworzeniem zamówienia. Własność serwera Discord nie blokuje już sama w sobie zakupu.
4. Uzupełnij rzeczywiste poświadczenia wyłącznie prywatnie w WordPressie, skonfiguruj webhooki i bota ponad rolami VIP, a następnie przeprowadź kontrolowany pilot: płatność → podpisany webhook → właściwa rola → chronione pobranie, potem anulowanie/zwrot. PayPal dopiero po rzeczywistym Stripe acceptance. Nie wykonano tych czynności zdalnie w bieżącej sesji. Testy lokalne używają symulowanego transportu; zaliczono 817 asercji PHP, 16 widoków przeglądarkowych, 2 checkouty, 6 stron i 97 kontroli instalacji ZIP.
5. Zachowaj cache exclusions i cron co pięć minut. Wstrzymuj nowe płatności przez routing disabled, bez zmiany Live dla istniejących umów. Nie ruszaj starych subskrypcji, niezależnych praw, uploadów Ownera ani obecnych botów. Instalacja na Hostingerze wymaga osobnego potwierdzenia stanu; push GitHub nie oznacza automatycznie wdrożenia WordPressa.

---

## Historyczne przekazania poniżej — bieżące polecenie i wersja powyżej mają pierwszeństwo

# Następna sesja — checkout na stronie / Ticket Tool, 2026-10-25

Na wyraźne polecenie właściciela przetestowany kod `93eafde` z `feature/site-only-checkout` opublikowano na GitHub `main` przez fast-forward z `664333f`. Kontynuuj od aktualnego `main` (Core 0.9.1/theme 0.11.1). Nie przywracaj Upgrade.chat do nowych zakupów ani poprzedniego fallbacku. Historia i ważne dostępy pozostają zachowane. Nie potwierdzono aktualizacji WordPressa na Hostingerze; płatności Live nie zostały włączone.

1. Przeczytaj najnowszy PROGRESS, `docs/WEBSITE_CHECKOUT_TICKET_TOOL.md` i aktualny `docs/DIRECT_BILLING_SETUP.md`. Routing tylko disabled/direct; stare legacy oznacza disabled, ceny zawsze29.99/80. Stare URL options nie są kasowane, ale nie mogą przekierowywać. Nie włączaj Live ani role sync bez konfiguracji i zgody.
2. Sprawdzone: 553 asercje PHP, 5 porównań rzeczywistego poprzedniego stock source, 16 layoutów +2 HTTP checkout +6 publicznych stron, lint 72/static/JS. ZIP-y0.11.1 /0.9.1 (89/20plików) zgodne ze źródłami, świeża instalacja 96 asercji. Browser/raporty/manifest w artifacts. Operatorzy i Discord są mockowane — nie zastępuje to realnego staging acceptance.
3. Do Ticket Tool właściciel wkleja link https://timonconfigs.com/pricing/ albo lokalne checkout monthly/lifetime. Gotowa English wiadomość w dokumencie, adresy dla aktualnej domeny w Ustawienia→TimonConfigs. Kod nie konfiguruje zdalnie Ticket Tool, nie nadaje VIP na podstawie ticketa ani zrzutu płatności.
4. Po aktualizacji Core pierwsza sesja admina automatycznie aktualizuje tylko całe rozpoznane stock treści; backup/CAS/status/daty/legalreview zachowane. Tools→TimonConfigs setup pokazuje autorskie teksty do ręcznego przeglądu i retry. Nie publikuj ani nie zatwierdzaj automatycznie polityk prawnych.
5. Najbliższy etap to aktualizacja na izolowanym HTTPS stagingu, rzeczywiste Stripe Test Product/Price IDs, sekrety serwerowe i webhook. Następnie realna macierz Monthly/Lifetime/renew/cancel/refund/dispute/retry. PayPal Sandbox dopiero po Stripe acceptance; bot wyłącznie dwie role VIP, serwer cron co 5 min, cache exclusions. Wypłaty EUR/VAT/faktury dla polskiego sprzedawcy i taryfy wymagają faktycznego ustalenia; aktualnych prowizji nie potwierdzono przez blokadę sieci403.
6. Przed Live potrzebny wskazany wcześniej przegląd rozliczeń/bezpieczeństwa (Astra Medium), prawdziwe testy i jawna zgoda. Nie anuluj starych umów ani nie usuwaj źródeł/grantów. Push źródeł nie jest dowodem aktualizacji Hostinger.

---

## Historyczne przekazania (nie wyznaczają bieżącej wersji)

## Aktualizacja: poprawki są już na GitHub main

Na wyraźne polecenie właściciela opublikowano fast-forward kod `b5d4de2` na `main` i zweryfikowano zdalny ref. Nie trzeba ponownie scalać `fix/owner-upload-recovery`. Core 0.8.6 / theme 0.10.5; kod i paczki pozostają zgodne z wcześniejszymi testami. Nie potwierdzono aktualizacji WordPressa na Hostingerze; push nie jest dowodem instalacji paczek. Następny priorytet to rzeczywista wersja hostingu, purge cache i próba oryginalnego pliku właściciela, bez zmian ról/płatności.

## Najnowszy etap: Owner upload recovery — Core 0.8.6 / theme 0.10.5

Przeczytaj `docs/OWNER_UPLOAD_RECOVERY_0.8.6.md` i najnowszy wpis PROGRESS.md. Gałąź `fix/owner-upload-recovery`, checkpointy `1deb95a`, `abc2287`; baza main `6d1e20e`. Nie przywracaj wymogu zewnętrznej zgody dla ZNBK. Windows GPC/TXT obsługuje UTF-8, BOM UTF-16 i ANSI z zachowaniem bajtów. Formularz zachowuje dane i wybrany plik po tymczasowym błędzie Discorda, nadal wymaga świeżego Owner + approved ID i nonce. Błąd usługi nie jest przedstawiany jako odebranie roli.

726 PHP / 39 Owner browser / 24 member layout zaliczone lokalnie; testy nie potwierdzają rzeczywistej usługi Discord ani oryginalnego niezałączonego skryptu. Paczki 0.8.6/0.10.5 są przygotowane, zgodne ze źródłami i przeszły 76 asercji świeżej izolowanej instalacji. Następny krok to zatwierdzona aktualizacja obu paczek na staging/hostingu, purge cache i próba oryginalnego pliku. Nie zmieniaj istniejących płatności, ról ani baz produkcyjnych bez zgody. Nie deklaruj wdrożenia Hostinger na podstawie samego commita lub push.

# Następna sesja — TimonConfigs (2026-10-24)

1. Sprawdź git status i gałąź `main`. Na wyraźne polecenie właściciela wydanie scalono fast-forward i wypchnięto do GitHub main (kod `17441cb`); gałąź feature zachowano. Wersje bieżącej poprawki: motyw 0.10.4 / Core 0.8.5. Kontynuacja dokumentów `CODEX_NEXT_FEATURES_AND_SEO_2026-10-23.md` i `CODEX_VISUAL_REFERENCES_2026-10-24.md`; nie twórz projektu ponownie. Przeczytaj `PROGRESS.md`, `docs/FEATURE_RELEASE_0.10.md` i aktualny koniec `docs/CMS_GUIDE.md`.
2. Zaimplementowane: zweryfikowane odznaki Staff/Owner, osobna allowlista Owner ID i świeża autoryzacja zapisu, frontend Manage Configs, własne capabilities CPT bez promocji Subscriber do admina, bezpieczne publiczne obrazy i prywatne wieloplikowe wydania, konfigurowalne formaty CFG/INI oraz dotychczasowe formaty z inspektorem.
3. Katalog ma realny licznik zakończonych dostaw/sortowanie, karty 3/2/1, Guide/Values z osobnym dostępem, rewizjami i dialogami. Pobrania pozostają przy konfiguracjach. Dashboard nie ma My Downloads/Recent downloads; stary prywatny URL działa z wyjaśnieniem. Nie usuwaj danych audytu ani bezpiecznych endpointów.
4. Home/SEO: nowe domyślne H1/copy/linki, rozwinięte istniejące kolekcje Apex/CoD zamiast cienkich stron. CTA bez grafiki, FAQ z grafiką. Hostinger preview noindex/sitemap protection; nie zmieniono faktycznej domeny ani provider-owned SEO. Nadpisania właściciela pozostają ważne. A–E opisane tekstowo, brak binarnych obrazów do porównania pikselowego.
5. Wyniki lokalne: 891 asercji integracyjnych, 76 instalacji paczek; przeglądarka i źródła z dokładnym zakresem w raporcie. Discord transport jest symulowany. Użytkownik potwierdził rzeczywiste logowanie Free; Free po PONOWNYM logowaniu jest poprawne. Rzeczywiste VIP/Owner/Staff i cache hostingu nadal wymagają testów. Nie deklaruj ukończonego audytu produkcji.
6. Paczki: `artifacts/timonconfigs-theme-0.10.0.zip`, `artifacts/timonconfigs-core-0.8.0.zip`; manifest `artifacts/feature-release-manifest.json`. Aktualne kopie w artifacts i /workspace. Motyw screenshot.png to prawdziwy WP 1200×900, kompaktowany wyłącznie w przeglądarce; pełne zrzuty są w artifacts.
7. Następny etap: izolowany HTTPS staging i konfiguracja PRAWDZIWYCH Owner/Staff role IDs + Approved Owner Discord user IDs, zachowując istniejący pilot/sekrety/Free/VIP IDs. Role Owner/Staff nie nadają VIP. Manage Configs: `/account/?tc_manage_configs=1`; zapis wymaga świeżej weryfikacji i odstępu 10 s. Nie proś o sekrety na czacie.
8. Hostinger Connector nadal nie był dostępny; próba rejestracji MCP wcześniej trafiła na read-only `/run/codex-environment/codex-home`. Nie powtarzaj jej bez zmiany środowiska. Push do main został zatwierdzony i wykonany. Nie wykonano bezpośredniego wdrożenia WordPressa na Hostingerze ani nie potwierdzono automatycznego wdrożenia. Przy kolejnym etapie najpierw zweryfikuj faktyczną wersję motywu/wtyczki na hostingu; nie utożsamiaj pushu z aktualizacją działającej witryny.
9. Weryfikacja hostingu: prywatny katalog poza WSZYSTKIMI publicznymi rootami, automatyczna walidacja ZNBK (od 0.8.5 bez zewnętrznej zgody), inspektor XIMMR, realne testy pobrań/utraty ról/session replay, wykluczenia cache dla account/zarządzania/OAuth/admin-post/Guide/Values/download, kopie bazy i prywatnych plików. Nie omijaj Security Bot, Ticket Tool, Upgrade.chat, Stripe ani PayPal.
10. Pozostałe materiały właściciela: A–E, realne wydania i kompatybilność, prawidłowe przypisanie trzech Apex filmów, zatwierdzone treści prawne oraz działające istniejące Checkout/support URLs. Search Console/field CWV i zdalne canonicale nie zostały zmierzone; nie gwarantuj pozycji Google.


Gałąź poprawki jest na GitHub. PR nie został utworzony: oba endpointy API zwróciły `Forbidden`. Main/Hostinger bez aktualizacji. Nie przedstawiaj publikacji gałęzi jako wdrożenia produkcyjnego.

## 2026-10-24 — poprawka niewidocznych publikacji i prostszego edytora Owner (PR #2, Draft)

Przejrzyj `docs/CONFIG_CATALOG_VISIBILITY_AND_OWNER_WORKFLOW.md` i Pull Request #2: `fix/config-visibility-and-owner-ux`. Źródłowa przyczyna: `filter_archive()` ukrywał `publish` + `coming-soon`. Poprawka wyświetla takie karty jako niedostępne do pobrania zapowiedzi, nadal wyklucza Archived i nie narusza `TC_Downloads`. Nowy formularz Owner ma pojedynczą grę/urządzenie, wymaga obu przy publikacji, domyślną wspólną grafikę gry, opcję prywatnych plików już przy tworzeniu (najpierw draft i walidacja, potem ewentualna publikacja). Lista Owner i administratora pokazuje stan oraz przyczynę niedostępności. Dodano źródłowe testy regresji i GitHub Actions php-lint, ale nie twierdź, że testy rzeczywiście przeszły: środowisko tego czatu nie ma uruchomionego WordPressa ani dostępu do hostingu. Następne zadanie: sprawdź PR i CI, wykonaj `php -l` + testy integracyjne na jednorazowym WP, testy browser Owner/Downloads, scenariusze negatywne bezpieczeństwa, wykonaj backup, porównaj wersje hosta i zatwierdź wdrożenie osobno. Nie scalaj PR na ślepo ani nie ruszaj płatności/ról.


## 2026-10-24 — prosty katalog z natychmiastowym pobraniem, XIM TXT (PR #3)

Klik całej karty = POST do istniejącego signed/one-use downloadera; Matrix pobiera TXT (preferuje prywatny .txt, alternatywnie eksportuje wartości Values z autoryzacją, podpisanym tokenem i hash). Nie konwertowano binarnych XIMMR do TXT; bez Values/TXT pobieranie pozostaje niedostępne. Nie usuwano danych użytkownika.

Wersje: theme 0.10.1 / Core 0.8.1. Testy WordPress Actions run 40043541966 przeszły (catalogue, downloads/Matrix, member-catalog), a PHP lint run 40043541971 zakończył się sukcesem. Discord był symulowany, bez dostępu do Hostinger. Przed produkcją sprawdzić backup bazy/motywu/wtyczki/private files, wersje hosta, role, cache, realny test TXT/XIM Values. Dokument: docs/CLICK_TO_DOWNLOAD_CARDS_AND_XIM_TXT.md. Nie deklaruj produkcyjnego wdrożenia bez obserwacji.


## 2026-10-24 — Owner panel: skrypty zamiast zdjęcia (PR #5)

Na zrzucie dla config_id=116 Owner widział tylko 'Public preview image', bo sekcja skryptów była zablokowana przez brak TIMONCONFIGS_PRIVATE_STORAGE_DIR. UI przeniesiono: teraz prywatne pliki są pierwszą akcją, a obrazek opcjonalnie w zwijanym panelu. Jeśli magazyn jest nieskonfigurowany, wyświetla się wyraźna instrukcja Hostinger; żadna płatna konfiguracja nie trafia do Media Library. Theme 0.10.3 i Core 0.8.3. Test WordPress integration run 40048011266 oraz PHP lint run 40048011263 przeszły. Szczegóły: docs/SCRIPT_FIRST_OWNER_UPLOAD_0.8.3.md. Faktyczny katalog na Hostingerze musi skonfigurować właściciel lub uprawniony administrator, nadal brak dowodu produkcyjnego uploadu.


## 2026-10-24 — ZNBK spakowany jako ZIP, stan bezpieczny (PR #6)

Core 0.8.4 dopuszcza **wyłącznie etap wstępnej kontroli** strukturalnej ZIP dla rozszerzenia `.znbk`, nie rozpakowuje pliku, zachowuje limit rozpakowania i blokuje ścieżki/wykonywalne payloady. Zwykłe archiwa nadal blokowane. Wymagany jest **realny zaufany** inspektor `timonconfigs_private_file_scan` zatwierdzający format przed dodaniem i każdym pobraniem. Nie stosować `return true` w produkcji! GitHub Actions `40050567738` (private-files 73 asercje, downloads 69, catalog 45, Owner 223), PHP lint `40050567733` przeszły; to sztuczne próbki, nie rzeczywisty ZNBK użytkownika. Sprawdzić konfigurację PHP ZipArchive na Hostinger i legalnie posiadany plik wzorcowy przed wdrożeniem. Dokument `docs/ZNBK_ZIP_CONTAINER_INSPECTOR_0.8.4.md`.

## Aktualne polecenie: upload i wspólny VIP — 0.8.5

Przeczytaj `docs/UPLOAD_AND_SHARED_VIP_0.8.5.md`. Właściciel wyraźnie polecił usunąć obowiązek zewnętrznej zgody dla ZNBK — nie przywracaj go na podstawie starszych dokumentów 0.8.4. Zachowane automatyczne kontrole ZIP/plików, limit, prywatny magazyn i opcjonalny veto skanera. XIMMR bez zmian. TXT UTF-8/UTF-16 jest sprawdzany bez zmiany bajtów. Etykieta **VIP — Monthly & Lifetime** używa dotychczasowego `monthly`, oba pakiety dzielą konfigurację. Prosty formularz ma plik w pierwszym kroku i zwinięte opcje zaawansowane; Owner bez VIP widzi Owner tools enabled. Soft cache reguł adresów odświeża się jednorazowo. Starsze lokalne prace zachowano w checkpoint `f376b8e`, bez nadpisania nowszego main `b10f360`. Najnowsze paczki mają 0.10.4 / 0.8.5, nie starsze 0.10.0 / 0.8.0 z historycznych punktów. Nie zadeklarowano wdrożenia Hostinger.

Wynik bieżącego etapu 0.8.5: 671 PHP + 76 instalacji ZIP, 29 rzeczywistych kontroli przeglądarki Owner i 24 layouty panelu zaliczone; transport Discord jest mockowany. Ostatni browser test używa normalnego cooldownu, bez kasowania `_tc_discord_attempt`. Paczki `artifacts/timonconfigs-theme-0.10.4.zip`, `artifacts/timonconfigs-core-0.8.5.zip`, manifest `artifacts/upload-085-release-manifest.json`. Gałąź `fix/upload-text-and-shared-vip`; główny katalog działa po jednorazowej soft migracji routingu. Hosting produkcyjny nie był wdrażany.

## Publikacja zatwierdzona przez właściciela

Core 0.8.5 / motyw 0.10.4 są na GitHub `main` (kod `19ae648`, fast-forward bez konfliktów). Nie trzeba ponownie scalać gałęzi fix. Nie potwierdzono instalacji na Hostingerze, ponieważ brak callable narzędzi; przed deklarowaniem działania produkcyjnego sprawdzić rzeczywistą wersję witryny i upload. Paczki pozostają zgodne ze źródłami.


## 2026-10-24 — Real GPC upload and Owner button sizing (PR #7)

Latest Core 0.8.7 relaxes **only** text/plain `.gpc` encoding/control character checks while preserving original bytes and blocking dangerous file signatures. Theme 0.10.6 fixes upload button stretched alongside AJAX error. GitHub Actions 40131505392 PHP lint and 40131505429 WordPress integration passed, including `tests/text-upload-integration.php` 85 assertions. Need verify actual user `PREDATOR SCRIPT S29_PIN_350305.gpc` on Hostinger after approved release; fixture tests cannot prove this particular file matches. Do not disable file scanning / MIME checks wholesale. See docs/GPC_LEGACY_UPLOAD_FIX_0.8.7.md. No Hostinger deployment.


## 2026-10-24 — trusted Owner raw config vault 0.8.8 (PR #8)

Final solution replaces content UTF/MIME/ZIP/JSON validation with opaque bytes on enabled config extensions; original bytes and SHA256 retained. Do **not** allow arbitrary executable extensions/public upload paths. Authenticated Owner/administrator, nonce, quotas, safe filename, private directory outside document roots, optional scanner veto and role-gated signed download remain enforced. Optional malware/content inspection is no longer automatic; inspect trustworthiness of files before distributing. Source-only GitHub change, Hostinger still must be updated to Core 0.8.8. Tests first run 40132642307 and PHP lint 40132642942 passed; later rerun after stale helper removal must be checked. Docs: docs/RAW_OWNER_PRIVATE_CONFIG_FILES_0.8.8.md. No real proprietary GPC available to validate exact file.


## 2026-10-25 — Owner Discord transient failures and new user login (PR #9)

Core 0.8.9 improves Owner UI reliability without weakening fresh role checks for writes: unexpired cache remains for view (60 seconds max) while failed forced refresh denies publishing. HTTP 429 Retry-After blocks repeated API traffic. A blocked user sees explicit `Limited beta access` if not on Restricted Pilot allowlist or `Account linking conflict` if another WP session/binding exists. Real other-user sign-in was NOT tested, only simulated OAuth identity. Guide `docs/DISCORD_OAUTH_OWNER_RELIABILITY_0.8.9.md`. CI PHP lint `40137679376` and disposable WordPress `40137679440` passed (127 Discord, 23 Owner verification, 223 Owner editor, 69 protected download assertions). Need validate canonical timonconfigs.com vs hostingersite.com, Site URL, exact OAuth callback, portal cache, and real user sign-in before public rollout. Preserve user data and existing customer roles; no automatic removal of Pilot, no Hostinger deployment.


## 2026-10-25 — Owner local authorization (PR #10)
Core 0.8.10: frontend Owner UI/upload/save/publish no longer polls Discord Owner role or applies ten-second cooldown. Uses authenticated WordPress session, decrypted OAuth token, explicit allowlisted Discord Owner ID, one-to-one binding, Restricted Pilot when active. No broader upload. Premium member downloads still verify Discord role.
CI PHP lint 40139402751 and disposable WP integration 40139402756 passed (Owner editor 169, local identity tests 20, Discord tests 127, protected downloads 69). Source change only — update plugin manually on Hostinger after backup; test real Owner and unrelated Free Member. Details: docs/OWNER_LOCAL_AUTHORIZATION_0.8.10.md.
