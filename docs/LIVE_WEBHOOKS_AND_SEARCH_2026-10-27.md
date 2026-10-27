# Produkcyjne płatności i widoczność Google — 2026-10-27

Dotyczy istniejącego TimonConfigs, Core 0.9.8 / motyw 0.11.3. Wydanie zachowuje najnowszy zdalny kod `8c15edd`, w tym odzyskiwanie osieroconego checkoutu Stripe przez administratora, oraz uzupełniony kontakt prawny. Nie zmieniano rzeczywistych poświadczeń, subskrypcji ani ról na produkcji. Wąski przegląd Astra Medium nie znalazł blockera w nowych poprawkach Stripe; nie zastępuje to rzeczywistego testu operatora.

## Brak przycisków na checkout

Ostatnie poprawki `cc1d6df..8c15edd` dotyczą panelu zamówień administratora. Nie zmieniają warunków wyświetlania przycisków ani loginu. Użytkownik podał komunikat o niedokończonym PayPal pending; to blokada ponownego zakupu, a nie sam dowód braku konfiguracji przycisków. Odpowiedź po dotychczasowej próbie zamknięcia pozostaje niepodana, więc nie ustalono jeszcze konkretnego stanu tego zamówienia u PayPal.

1. Otwórz `https://timonconfigs.com/checkout/monthly/` lub `/checkout/lifetime/`, zalogowany jako administrator WordPressa. Rozwiń **Administrator: payment setup** — lista wskazuje brakujące warunki. Sam Discord Owner nie zastępuje uprawnień administratora WordPressa do konfiguracji finansowej.
2. **Ustawienia → TimonConfigs billing:** Mode **Live**, Routing **Public website checkout**, public Live payments włączone. Zapisuj przez HTTPS.
3. Sprawdź sekcję **Save server credentials → Live**. Stripe potrzebuje Secret key oraz Signing secret aktualnego webhook destination; obie ceny i produkty muszą być z Live. Monthly €29.99/miesiąc, Lifetime €80 jednorazowo. Nie wklejaj kluczy do czatu.
4. Jeśli przeniesiono bazę ze stagingu lub zmieniono adres witryny/salty WordPressa, stan sekretów może pokazywać **Cannot decrypt — enter again**. Wpisz poświadczenia ponownie prywatnie w docelowym WordPressie. Puste pole zachowuje wcześniejszy zapis i samo go nie naprawia. Stała serwerowa, jeśli skonfigurowana, ma pierwszeństwo.
5. W Live oba operatory wymagają konfiguracji synchronizacji VIP: role sync, token bota, Guild ID i dwa właściwe VIP role IDs. Nie dopisuj do istniejących procesów drugiego automatu nadającego role.
6. PayPal wymaga własnego włączenia, potwierdzenia faktycznie zakończonego Stripe acceptance, Live Client ID/secret i Webhook ID. Monthly wymaga ponadto aktywnego Plan ID i Product ID. Nie zaznaczaj fikcyjnego potwierdzenia testów.

Samo istnienie albo błędny docelowy URL webhooka nie ustala widoczności przycisków. Brak lub nieczytelny sekret/ID, routing i inne warunki konfiguracji mogą je ukryć. Nie usuwaj tych kontroli, aby tylko wymusić pokazanie przycisku.

## Zamknięcie niedokończonego PayPal Monthly

W tym wydaniu **Close unpaid checkout** na checkout/koncie użytkownika oraz zamknięcie zamówienia w **Customer orders** administratora wysyła jawną próbę anulowania nieopłaconej zgody Monthly. Działa na zapisanej, przypisanej do klienta subskrypcji APPROVAL_PENDING/APPROVED: sprawdza powiązanie i pustą historię, prosi PayPal o cancellation, następnie ponownie odczytuje stan i historię. Dopiero potwierdzone CANCELLED/EXPIRED bez płatności pozwala lokalnie zamknąć próbę i wybrać inną metodę. To oddzielna operacja od **Cancel Auto-Renewal** aktywnej subskrypcji.

PayPal może odmówić anulowania w danym pending state; sukces potwierdzono lokalnie na symulowanym transporcie, nie na Live. Po błędzie API, braku referencji lub śladzie płatności program zachowuje blokadę i historię, zamiast wymuszać nowy zakup. Sama zmiana operatora nie anuluje zgody. Dla jednorazowego Lifetime CAPTURE nie wymyślono endpointu void/cancel order: bez potwierdzonego zamknięcia należy wznowić ten sam checkout albo sprawdzić rzeczywisty stan u operatora. Nie kasuj wierszy zamówień w bazie.

## Stripe Workbench

Na przesłanym zrzucie nazwa destination jest „TimonConfigs Tested”, a widoczny URL zaczyna się od `https://staging.timon…`. Pełny URL jest ucięty, więc nie uznano tego za odczyt całej konfiguracji. Dla płatności produkcyjnych właściwy endpoint to:

```text
https://timonconfigs.com/wp-json/timonconfigs/v1/billing/stripe/live
```

W trybie Live przejdź **Workbench → Webhooks → Add destination** albo otwórz właściwy istniejący destination. Wybierz webhook endpoint, zdarzenia konta i zgodne zdarzenia **snapshot**. Adapter używa API `2024-06-20` i standardowego `data.object`; nie zakładaj obsługi thin events. Sprawdź zgodność wersji zdarzeń w koncie Stripe przed zastosowaniem nowszej struktury.

Obsługiwane istotne zdarzenia:

```text
checkout.session.completed
invoice.paid
invoice.payment_failed
customer.subscription.updated
customer.subscription.deleted
charge.refunded
charge.dispute.created
charge.dispute.updated
charge.dispute.closed
```

Skopiuj **Signing secret używanego destination** do pola **Stripe webhook Signing secret** w sekcji Live WordPressa. Nowy destination ma własny sekret; sama edycja URL istniejącego destination nie musi zmieniać sekretu. Nie myl `whsec_…` z API Secret key. Nie usuwaj starego staging destination, jeśli nadal obsługuje testy albo stare zdarzenia — dla produkcji skonfiguruj poprawny oddzielny cel.

W destination sprawdź **Event deliveries**, status HTTP i odpowiedź endpointu. Nie odtwarzaj masowo starych zdarzeń produkcyjnych ani nie twórz prawdziwego zakupu bez świadomej decyzji. `200 received` oznacza przyjęcie do kolejki; rola VIP pojawia się dopiero po serwerowej weryfikacji i synchronizacji. `400 invalid_notification` wskazuje podpis/payload; `503 provider_disabled` konfigurację; `503 queue_unavailable` błąd zapisu kolejki. GET w przeglądarce nie jest testem podpisanego webhooka POST.

## Gdzie sprawdzić PayPal

**PayPal Developer Dashboard → Live → Apps & Credentials → wybrana aplikacja → Webhooks.** Właściwy endpoint produkcyjny:

```text
https://timonconfigs.com/wp-json/timonconfigs/v1/billing/paypal/live
```

Dodatkowy zrzut użytkownika pokazuje ten poprawny adres zarejestrowany w sekcji Live Webhooks oraz zgodną listę zdarzeń. W API Calls jest udany GET `/v1/billing/plans/P-…` z HTTP200: potwierdza odczyt planu w tym wywołaniu, nie płatność, capture ani dostarczenie webhooka. Nie potrzeba tworzyć drugiego identycznego webhooka. Funkcja Payouts dotyczy wypłat do odbiorców i nie jest wymagana do tego checkoutu.

Client ID i secret w WordPressie muszą pochodzić z tej samej aplikacji Live. Wpisz do **PayPal webhook ID** identyfikator rzeczywistego webhooka tej aplikacji. Nowy webhook ma nowy ID; edycja URL istniejącego zwykle go zachowuje. To nie jest adres e-mail PayPal ani Client ID. Dla Monthly plan musi być aktywny i zgodny z produktem/kwotą EUR29.99/miesiąc; Lifetime używa jednorazowego Checkout v2 order EUR80.

Zdarzenia używane przez integrację (wybierz dostępne dla faktycznie używanych usług konta):

```text
CHECKOUT.ORDER.APPROVED
CHECKOUT.ORDER.COMPLETED
PAYMENT.CAPTURE.COMPLETED
PAYMENT.CAPTURE.DENIED
PAYMENT.CAPTURE.PENDING
PAYMENT.CAPTURE.REFUNDED
PAYMENT.CAPTURE.REVERSED
PAYMENT.SALE.COMPLETED
PAYMENT.SALE.PENDING
PAYMENT.SALE.DENIED
PAYMENT.SALE.REFUNDED
PAYMENT.SALE.REVERSED
BILLING.SUBSCRIPTION.CREATED
BILLING.SUBSCRIPTION.ACTIVATED
BILLING.SUBSCRIPTION.UPDATED
BILLING.SUBSCRIPTION.CANCELLED
BILLING.SUBSCRIPTION.SUSPENDED
BILLING.SUBSCRIPTION.EXPIRED
BILLING.SUBSCRIPTION.PAYMENT.FAILED
CUSTOMER.DISPUTE.CREATED
CUSTOMER.DISPUTE.UPDATED
CUSTOMER.DISPUTE.RESOLVED
```

**Event Logs → API Calls / Error Logs / Webhook Events** pokazuje wywołania i dostarczenia aplikacji. Wybierz właściwą aplikację Live i zakres czasu. Rzeczywiste obciążenia sprawdzisz również w **Activity** konta biznesowego PayPal. „No data for this selection” nie potwierdza połączenia ani awarii: może oznaczać brak zdarzeń, inną aplikację lub filtr dat. Nie uznawaj samego ACTIVE subskrypcji, udanego powrotu przeglądarki ani przykładowego zdarzenia za zapłatę klienta.

## Czy strona jest widoczna w Google

W tej sesji próby odczytu Home, robots.txt, sitemap, landingów XIM/Apex i wyszukiwań Google zatrzymał proxy środowiska: **Tunnel connection failed: 403 Forbidden**. To nie status HTTP witryny. Nie potwierdzono aktualnej indeksacji, pozycji ani Search Console. Strona uruchomiona kilka dni wcześniej może jeszcze czekać na odkrycie/indeksowanie; brak wyniku `site:` sam nie rozstrzyga problemu.

1. **WordPress → Ustawienia → Ogólne:** oba adresy ustawione na rzeczywiste `https://timonconfigs.com`. Kod celowo ustawia noindex i blokuje sitemap na `*.hostingersite.com`; dotyczy to również zapisanego adresu home, nie tylko paska przeglądarki.
2. **Ustawienia → Czytanie:** wyłączona prośba o nieindeksowanie. Sprawdź również noindex w używanej wtyczce SEO i na konkretnych stronach.
3. **Google Search Console → Sprawdzenie adresu URL:** Home i istniejące `/xim-matrix-configs/`, `/apex-legends-configs/`. Wykonaj **Test aktywnego URL**, sprawdź dostępność, indeksowalność i canonical wybrany przez Google, potem poproś o indeksowanie.
4. **Mapy witryn:** zgłoś faktyczną mapę wskazaną przez aktywną wtyczkę SEO/robots. Dla WordPress core zwykle `/wp-sitemap.xml`, dla części wtyczek `/sitemap.xml`. Nie twórz drugiej konkurencyjnej mapy i drugiego dostawcy meta.
5. **Skuteczność → Wyniki wyszukiwania:** sprawdź wyświetlenia i zapytanie `xim matrix apex legends`. Search Console może mieć opóźnienie; średnia pozycja zależy od kraju, urządzenia i zapytań.

W obecnym kodzie pusta kolekcja jest celowo noindex: landing taksonomii potrzebuje opisowej treści (minimum120znaków), przynajmniej jednej opublikowanej dostępnej konfiguracji i braku ustawionego noindex. Minimum techniczne nie zastępuje użytecznej treści. Nie zgłaszaj do Google filtrowanego URL `/configs/?...` — takie filtry/wyszukiwanie są celowo noindex.

## Droga do frazy „XIM Matrix Apex Legends”

Top1 nie można zagwarantować. Przygotuj jedną publiczną, unikalną stronę na ten konkretny temat: Controller i MnK, rzeczywista kompatybilność/wersja, własne demonstracje, instrukcja instalacji, aktualizacje, odpowiedzi na problemy i rzetelne informacje o licencji. Pełne pliki premium pozostają chronione. Podlinkuj ją z Home oraz landingów Apex i XIM; daj naturalny tytuł/H1 i opis. Nie powielaj setek podobnych stron ani nazw konkurentów. Potem mierz zapytania i kliki w Search Console, poprawiaj treści na podstawie danych i zdobywaj tematyczne odnośniki bez kupowania spamu. Nie deklarujemy pierwszej pozycji ani obecności w indeksie bez danych Google.
