# Prosty checkout i ograniczony pilot Live — Core 0.9.2

Aktualizacja z 2026-10-26 kontynuuje istniejący WordPress. Właściciel wybrał rzeczywiste płatności Live, początkowo wyłącznie dla swojego konta Discord. To zastępuje wcześniejszy wymóg wykonania pierwszej próby wyłącznie na stagingu w zakresie tego pilota. Nie oznacza uruchomienia sprzedaży publicznej ani potwierdzenia ukończonych testów operatorów.

Klient wybiera Monthly lub Lifetime, loguje się przez Discord i automatycznie wraca do wybranego checkoutu. Skonfigurowane metody **Pay with Stripe** i **Pay with PayPal** pojawiają się od razu, bez dodatkowego checkboxa. Kliknięcie przycisku jest świadomym POST z nonce i zapisem przedstawionej ceny oraz warunków; samo logowanie nie tworzy płatności. Adres powrotu jest ograniczony do dwóch checkoutów i zapisany w jednorazowym stanie OAuth. Powrót od operatora nie przyznaje VIP.

## Konfiguracja w WordPressie

1. Po instalacji Core 0.9.2 otwórz **Ustawienia → TimonConfigs billing** przez HTTPS. W sekcji **Save server credentials → Live** wpisz Stripe Secret key, Stripe webhook Signing secret i Discord Bot token. Zapisane sekrety są szyfrowane i nie są wyświetlane ponownie; puste pole zachowuje poprzednią wartość. Nie wklejaj sekretów do czatu ani repozytorium. Zmiana domeny lub kluczy bezpieczeństwa WordPressa wymaga ponownego zapisania poświadczeń.
2. W Stripe Live utwórz ceny **€29.99 co miesiąc** i **€80 jednorazowo**. W **Verify two Stripe prices** wybierz Live i wklej dwa identyfikatory `price_…`. Weryfikacja odczytuje ceny i uzupełnia Product IDs, bez tworzenia obciążenia. Webhook Stripe Live musi wskazywać adres pokazany w panelu; jego Signing secret musi należeć do tego endpointu.
3. Wybierz **Mode: Live** i **Routing: Restricted Live pilot — one account**. Wpisz swój rzeczywisty **Discord User ID**, zaznacz włączenie pilota i świadome potwierdzenie rzeczywistych obciążeń. Włącz synchronizację ról dopiero po dodaniu bota z **Manage Roles** i ustawieniu jego roli ponad obiema rolami VIP. Puste Live billing guild/VIP IDs korzystają z istniejących ustawień **Discord membership**; nie zmieniaj identyfikatorów istniejących ról. Zapisz ustawienia. Potwierdzenia zakończonych testów lub przeglądu prawnego zaznaczaj wyłącznie zgodnie z rzeczywistym stanem.
4. Otwórz `https://timonconfigs.com/checkout/monthly/` lub `/checkout/lifetime/`, zaloguj zaproszone konto Discord i wybierz operatora. Inne konto pozostaje zablokowane po stronie serwera. Jeśli nie ma dostępnej metody, zobaczysz komunikat; zalogowany administrator może rozwinąć **Administrator: payment setup**, aby poznać brakujące ustawienia. Przed utworzeniem checkoutu Live serwer odczytuje konfigurację i uprawnienia bota Discord. Błąd tego sprawdzenia nie tworzy nowego zamówienia ani płatności.

PayPal włącz po rzeczywiście zakończonych kontrolach Stripe: zapisz Live Client ID i Client secret, ustaw PayPal webhook ID oraz zaznacz **Enable PayPal after verified Stripe acceptance**. Monthly wymaga ponadto identyfikatorów planu i produktu PayPal zgodnych z ofertą; Lifetime jest zakupem jednorazowym. Samo posiadanie kont Stripe i PayPal nie konfiguruje tych danych automatycznie.

## Sprawdzenie i zatrzymanie pilota

Przed rzeczywistym kliknięciem sprawdź kwotę i odnawialność; operator może naliczyć prowizje. Potwierdź podpisany webhook, opłacony status, synchronizację właściwej roli i pobranie chronionego pliku. Następnie sprawdź anulowanie odnowienia Monthly i obsługę zwrotu. Nie uznawaj adresu powrotu ani zrzutu płatności za dowód zapłaty. Istniejące niezależne zakupy, role i umowy nie są automatycznie anulowane przez nowy zakup.

Wyklucz z cache `/checkout/`, `/account/`, OAuth, billing REST, `admin-post.php` i chronione pobrania. Utrzymaj serwerowy cron co pięć minut według [DIRECT_BILLING_SETUP.md](DIRECT_BILLING_SETUP.md). Aby zatrzymać nowe zakupy, wybierz **Checkout unavailable**, zachowując Live, poświadczenia i cron dla istniejących umów. Nie przełączaj aktywnych umów do Test.

Przycisk akceptujący cenę i warunki nie zastępuje przeglądu zasad odstąpienia od umowy, dostarczania treści cyfrowych, VAT ani faktur. Publiczny checkout Live nadal wymaga odrębnego zatwierdzenia serwerowego, zakończonych kontroli oraz przeglądu migracji i warunków prawnych.

## Stan wydania

Kod `9527129` jest na GitHub `main`. Zaliczone: 817 asercji PHP, 16 widoków responsywnych, 2 lokalne adresy checkoutu, 6 stron publicznych i 97 kontroli świeżej instalacji ZIP. Lint 73 plików PHP oraz składnia JS poprawne. Ukierunkowany przegląd Astra Medium nie znalazł blokujących błędów. Ruch Stripe/PayPal/Discord w testach jest symulowany. Nie wpisano rzeczywistych sekretów, nie wykonano rzeczywistego zakupu ani nie włączono Live na Hostingerze. Publikacja na GitHubie sama w sobie nie potwierdza aktualizacji działającego WordPressa.
