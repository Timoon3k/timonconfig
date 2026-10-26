# Niedokończone checkouty i odnowienia — Core 0.9.5

Właściciel potrzebuje obu operacji: zakończenia nieopłaconej próby zakupu i zatrzymania kolejnych obciążeń subskrypcji klienta. Publiczne zakupy, Discord OAuth i chronione pobrania pozostają zachowane.

## Obsługa klienta w WordPressie

1. Zaloguj się do WordPressa jako administrator z `manage_options`. Otwórz **Ustawienia → TimonConfigs billing → Customer orders**. Dostępny jest też link **Manage customer orders** w panelu płatności administratora.
2. Wyszukaj **WordPress user ID**, **Discord user ID** albo **order ID (UUID)**. Panel pokazuje do 20 zamówień z aktualnie skonfigurowanego środowiska Test lub Live.
3. Wybierz właściwą operację:

| Przycisk | Działanie |
| --- | --- |
| **Refresh payment status** | Zleca ponowną weryfikację u operatora. Wymaga działającego cron i identyfikatora płatności/checkoutu/subskrypcji. |
| **Close unpaid checkout** | Zamyka wyłącznie checkout potwierdzony przez operatora jako nieopłacony i zakończony. Zaznacz potwierdzenie przy tej operacji. |
| **Cancel auto-renewal** | Zatrzymuje przyszłe odnowienia wskazanej subskrypcji Monthly. Zaznacz potwierdzenie; zweryfikowany opłacony dostęp pozostaje do końca okresu. |

Rola Discord **Owner** daje zatwierdzone uprawnienia publikowania konfiguracji; nie nadaje administracji rozliczeniami. Klient może zarządzać własnym zamówieniem. Operacje na cudzym zamówieniu wymagają administratora WordPressa, nonce i właściwego środowiska. Panel nie pozwala ręcznie oznaczyć płatności jako opłaconej ani wykonać zwrotu. Zamknięcie nieopłaconego checkoutu nie jest zwrotem.

## Zmiana operatora przez klienta

Checkout i historia konta pokazują niedokończoną próbę oraz **Close unpaid checkout**. Klient może wznowić pierwotny checkout. Przy wyborze drugiego operatora kod najpierw próbuje bezpiecznie zamknąć potwierdzony nieopłacony checkout; nową próbę tworzy dopiero po potwierdzeniu zakończenia poprzedniej.

- **Stripe:** otwarty nieopłacony checkout jest wygaszany u Stripe, a końcowy odczyt musi potwierdzić `expired`. Już wygasła sesja może zostać rozpoznana podczas ponownej weryfikacji. Opłacona lub niepewna sesja pozostaje do wyjaśnienia; jej historii i niezależnego dostępu nie kasuje się.
- **PayPal Monthly:** zamknięcie wymaga już potwierdzonego `CANCELLED` albo `EXPIRED` i kompletnej pustej historii finansowej. Przy otwartej zgodzie najpierw anuluj ją/subskrypcję właściwą operacją zarządzania, następnie odśwież status. Sama próba zmiany operatora nie anuluje automatycznie otwartej zgody PayPal.
- **PayPal Lifetime:** PayPal Orders nie udostępnia operacji wygaszenia zwykłego checkoutu. Kod akceptuje zakończenie wyłącznie dla potwierdzonego `VOIDED`, bez referencji płatności. Otwarte `CREATED`/`APPROVED` wymaga obsługi u operatora.

Stara próba bez identyfikatora u operatora nie dowodzi braku płatności. Wymaga ręcznej weryfikacji w Stripe/PayPal; nie odblokowuj jej przez usuwanie rekordu lub ręczne ustawienie statusu. Nowy błąd konfiguracji udowodniony przed wysłaniem żądania utworzenia checkoutu może bezpiecznie zakończyć nową lokalną próbę jako `failed`. Błąd sieci lub odpowiedź niepewna po POST pozostaje `pending`.

## Diagnostyka i stan wydania

Stripe pokazuje bezpieczny kod przyczyny, HTTP i etap zamiast jednego ogólnego komunikatu. Nie zawiera tokenów, parametrów ani treści odpowiedzi operatora. Ze starego zrzutu ekranu nie można ustalić rzeczywistej przyczyny Stripe; użyj kodu po aktualizacji i zweryfikuj ustawienia w panelu operatora.

Kod `4f051d4` jest na GitHub main. Zaliczone:857 asercji PHP, 32 widoki responsywne, 2 lokalne checkouty, 6 stron publicznych, lint73 PHP/JS oraz97 kontroli świeżej instalacji ZIP na WP6.8.3/PHP8.2. Ukierunkowany przegląd Astra Medium doprowadził do poprawienia dwóch regresji: ponownego rozliczenia zamkniętego Stripe i przycisku anulowania odnowień przy expired. Testy używają symulowanego transportu operatorów. Nie wykonano rzeczywistego zakupu, anulowania subskrypcji, zmiany sekretów, ról ani wdrożenia na Hostingerze. Push GitHub nie potwierdza instalacji na działającym WordPressie.
