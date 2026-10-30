# TimonConfigs Core 1.1.10 — anulowanie i spójność ról

## Zgłoszenie i ustalenia

Właściciel potwierdził **natychmiastowe anulowanie w Stripe**, po którym profil przechodził przez oczekiwanie na weryfikację, a rola Monthly znikała lub wracała. Po ręcznym odebraniu roli przez Ownera końcowy stan Free pojawiał się po około pięciu minutach.

Kod poprzedniej wersji miał cache Discorda na 60 sekund, okresową rekoncyliację co pięć minut i brak blokady jednoczesnych zadań jednego członka. Poprawny odczyt członka bez skonfigurowanej roli był przedstawiany jako brak weryfikacji. Te mechanizmy pasują do zgłoszonych objawów; produkcyjnego logu i zdarzeń operatora nie odczytano.

## Zmiany

- Jeden członek ma jednego aktywnego pracownika synchronizacji. Atomowa blokada, sprawdzanie jej właściciela przy zapisach i bezpieczne zwalnianie ograniczają nadpisywanie ról przez starsze zadania. Dostęp jest ponownie sprawdzany po zleceniu nadania rangi.
- Czas nadania zapisywany jest od rozpoczęcia żądania do Discorda. Ręczne odebranie rangi przez Ownera lub Staff nie jest maskowane przez opóźnioną odpowiedź bota. Zapis cofnięcia dostępu unieważnia też cache profilu.
- Przy znanym końcu dostępu bot próbuje nadać Free przed odebraniem własnej rangi VIP. Błąd nadania Free nie przedłuża nieuprawnionego VIP; pozostaje diagnostyka i możliwość ponowienia synchronizacji. Nieznany stan płatności nie jest traktowany jako potwierdzone wygaśnięcie.
- Zmiany statusu/dat subskrypcji i świeży, poprawny odczyt członka Discorda kolejkują sprawdzenie. Odczyt profilu nie nadaje ról bezpośrednio. Kolejkowanie z odczytu jest ograniczone do raz na 60 sekund; okresowe pięć minut pozostaje mechanizmem zapasowym.
- Wcześniej opłacony, śledzony członek przejściowo bez rangi widzi **Membership update pending** i informację o połączonym Discordzie. Nie jest kierowany do ponownego OAuth. To zmiana prezentacji: nie nadaje syntetycznej roli Free ani dostępu do plików.

Core **1.1.10**, motyw **0.14.3**. reCAPTCHA pozostaje usunięta zgodnie z poleceniem właściciela. Historia płatności i zamówienia są zachowane.

## Dwa różne rodzaje anulowania

**Natychmiastowe zakończenie:** po otrzymaniu i zapisaniu przez właściwą bramkę potwierdzonego stanu końcowego WooCommerce/ArraySubs bot powinien odebrać Monthly i zastosować Free. Stara przyszła data płatności nie przywraca zakończonej subskrypcji.

**Anulowanie przyszłych odnowień:** potwierdzona aktywna subskrypcja z zaplanowanym anulowaniem zachowuje Monthly do końca opłaconego okresu. Samo wyłączenie auto-renew nie oznacza natychmiastowego odebrania dostępu.

Kliknięcie Cancel w Stripe nie dowodzi jeszcze aktualizacji ArraySubs. TimonConfigs czyta zweryfikowany stan WooCommerce/ArraySubs, nie sam ekran operatora. Poprawka nie dodaje drugiego systemu rozliczeń ani alternatywnego webhooka i nie gwarantuje natychmiastowej dostawy.

## Kontrola po aktualizacji

1. Zainstaluj Core 1.1.10 po kopii bazy i plików, zachowując mapowanie produktów oraz konfigurację Discorda. Nie uruchamiaj ponownie instalatora całego projektu.
2. Przy kontrolowanym anulowaniu sprawdź ten sam numer zamówienia/subskrypcji w Stripe, WooCommerce i ArraySubs. Zweryfikuj dostarczenie zdarzenia do endpointu pokazanego przez faktycznie zainstalowaną bramkę; nie używaj wycofanych endpointów `TC_Billing_*`.
3. Sprawdź **WooCommerce → Status → Scheduled Actions**: zadania `tc_woo_sync_one`, błędy i zaległości. Action Scheduler oraz WP-Cron/cron serwera muszą działać. Brak ruchu lub błędy kolejki nadal mogą opóźniać aktualizację.
4. Bot musi mieć Manage Roles i pozycję ponad Monthly/Lifetime/Free. Potwierdzenie moderacji Owner/Staff wymaga View Audit Log. Błąd zatrzymania odnowień po moderacji wymaga sprawdzenia ArraySubs/operatora; lokalne cofnięcie dostępu nie dowodzi zakończenia obciążeń.
5. Sprawdź natychmiastowe anulowanie → Free, anulowanie na koniec okresu → zachowany Monthly, Owner/Staff removal → brak przywracania starego VIP oraz osobny nowy zakup → nowy dostęp. W czasie zmiany profil ma pokazać synchronizację, bez fikcyjnej prośby o ponowną weryfikację.

## Walidacja i ograniczenia

Zaliczone **353 asercje PHP**: role71, audyt4, blokada na rzeczywistej bazie WordPressa8, profil74, OAuth127, pobrania69. Lint117PHP, static/JS/YAML/diff bez ostrzeżeń. **27 widoków profilu** w przeglądarce (9stanów×3szerokości), bez błędów JS i overflow. **98 kontroli świeżej instalacji ZIP** na oddzielnym offline WordPressie6.8.3/PHP8.2 zaliczonych. ZIPCore20plików zgodny ze źródłami: `artifacts/timonconfigs-core-1.1.10.zip`; manifest `artifacts/sync1110-release-manifest.json`. Kod etapu `66fe347`. Operatorzy i Discord są symulowani; testy nie stanowią akceptacji rzeczywistych webhooków i płatności.

Nie wykonano rzeczywistego zakupu, anulowania, zmiany ról ani konfiguracji na produkcji. Nie potwierdzono zdalnej instalacji. Dokumentacja/paczka ArraySubs 2.0.9 była niedostępna z tego środowiska (proxy CONNECT403); rzeczywiste mapowanie statusów/dat i obsługa webhooków zainstalowanej bramki wymagają weryfikacji na instalacji. Nie rozszerzano uprawnień o niepotwierdzone statusy subskrypcji. Push GitHub nie stanowi dowodu wdrożenia na Hostingerze.
