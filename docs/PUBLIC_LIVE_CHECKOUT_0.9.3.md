# Publiczne zakupy Live — Core 0.9.3

Właściciel polecił otworzyć zakupy dla wszystkich klientów. To aktualizuje wcześniejsze ograniczenie do jego konta Discord. Core 0.9.3 udostępnia publiczny tryb sprzedaży; instalacja aktualizacji sama go nie włącza. Nie zmieniono zdalnie ustawień Hostinger, sekretów, istniejących umów ani ról.

## Włączenie w WordPressie

1. Zaktualizuj **TimonConfigs Core do 0.9.3**, a następnie otwórz przez HTTPS **Ustawienia → TimonConfigs billing**.
2. Ustaw **Mode: Live** i **Routing: Public website checkout**. Zaznacz **Enable public Live payments for all verified Discord members** i zapisz ustawienia. Invited Discord User ID dotyczy wyłącznie pilota; publiczny klient nie potrzebuje tego wpisu ani praw Ownera.
3. Zachowaj skonfigurowane klucze Stripe, dwa zestawy Price/Product IDs, podpisany webhook oraz bota Discord z Manage Roles ponad rolami VIP. Puste Live guild/VIP IDs wykorzystują istniejące ustawienia Discord membership. Jeśli metoda płatności nie pojawia się, administrator może rozwinąć diagnostykę na checkoutcie. Sekrety wpisuj wyłącznie prywatnie w WordPressie.
4. Sprawdź zakup ze zwykłego konta członka Discorda. Po logowaniu wraca ono automatycznie do wybranego checkoutu Monthly/Lifetime i widzi skonfigurowane przyciski operatorów bez checkboxów. Cena pozostaje **€29.99 miesięcznie** lub **€80 jednorazowo**.

Publiczny tryb obejmuje również logowanie Discord: dawna lista pilota nie ogranicza klientów, gdy zatwierdzono publiczny Live. OAuth nadal wymaga włączenia i poprawnych poświadczeń. Nowe konto WordPress otrzymuje rolę subscriber. Zakup nadal wymaga aktualnej weryfikacji członkostwa Discord; Free Member uzyskuje się przez istniejącą weryfikację serwera. Publiczne logowanie nie nadaje automatycznie Free, VIP ani Owner i nie udostępnia płatnych plików. VIP wynika dopiero ze zweryfikowanej płatności i synchronizacji ról.

PayPal wymaga własnych Live Client ID/secret, webhooka i włączenia po rzeczywistym Stripe acceptance; Monthly wymaga także zgodnego planu i produktu PayPal. Włączenie publicznego Stripe nie zaznacza automatycznie zakończonych testów, przeglądu migracji lub przeglądu prawnego. Starsza metoda zatwierdzenia stałą serwerową pozostaje zgodna.

## Zatrzymanie nowych zakupów

Wybierz **Checkout unavailable**, zachowując Live, publiczny dostęp kont, poświadczenia i działający cron. Klienci muszą nadal móc się zalogować i zarządzać istniejącymi umowami. Nie przełączaj opłaconych umów do Test. Zachowaj wykluczenia cache dla `/checkout/`, `/account/`, OAuth, billing REST, `admin-post.php` i chronionych pobrań oraz cron co pięć minut.

Przed ogłoszeniem sprzedaży potwierdź rzeczywisty przepływ płatność → podpisany webhook → właściwa rola VIP → chronione pobranie oraz anulowanie i zwrot. Sam powrót z checkoutu nie potwierdza zapłaty. Szczegóły operatorów: [DIRECT_BILLING_SETUP.md](DIRECT_BILLING_SETUP.md).

## Stan weryfikacji

Kod `fb2142e` opublikowano na GitHub `main`. Zaliczone: 595 asercji PHP, 24 widoki przeglądarkowe, 2 rzeczywiste lokalne checkouty, 6 stron publicznych i 97 kontroli instalacji ZIP. Lint73 plików PHP oraz składnia JS poprawne. Skupiony przegląd Astra Medium nie zgłosił dalszych blokujących problemów po poprawce obsługi istniejących klientów przy przejściu z publicznego trybu do pilota. Testy operatorów i Discorda używają symulowanego transportu. Nie wykonano rzeczywistego obciążenia ani aktywacji na hostingu; publikacja GitHub nie potwierdza instalacji WordPressa.
