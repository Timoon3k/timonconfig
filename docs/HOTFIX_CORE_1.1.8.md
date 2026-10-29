# TimonConfigs Core 1.1.8 — naprawa błędu krytycznego

## Przyczyna i zakres

Baza: GitHub `main` z commitem `1b8af17`, Core 1.1.7; motyw pozostaje **0.14.2**. W lokalnym WordPressie 6.8.3 z WooCommerce 10.3.5 odtworzono `Error: Undefined constant TC_Woo_Bridge::REVOKED`. Kod odwoływał się także do niezdefiniowanej `ROLE_GRANTED_AT`. Odczyt płatnego dostępu, profil lub synchronizacja ról mogły zakończyć się błędem krytycznym. Nie odczytano produkcyjnego logu PHP, więc nie wyklucza to dodatkowego problemu na hostingu.

Core **1.1.8**:

- Definiuje oba klucze historii uprawnień i nadania ról.
- Obsługuje wyjątki zależności ArraySubs w profilu, anulowaniu i moderacji. Błąd pokazuje bezpieczny komunikat lub wymaga przeglądu; nie oznacza potwierdzonego zatrzymania obciążeń.
- Zachowuje przycisk anulowania Monthly również u klienta posiadającego Lifetime. Zakup Lifetime sam nie anuluje osobnej subskrypcji Monthly.
- Odrzuca wpis audytu usunięcia rangi starszy niż jej ostatnie nadanie, chroniąc nowy zakup przed dawną moderacją.
- Zachowuje dodatkowe wymagane pola bramek i klasy odświeżania kraju/regionu w checkout; usuwa ostrzeżenie `reset()` i obsługuje nieprawidłowy parametr AJAX bez ostrzeżeń.

Nie usuwa zamówień, historii płatności ani danych klientów i nie przywraca wycofanego systemu `TC_Billing_*`. Nie wyłącza reCAPTCHA. Błąd zewnętrznego API nie daje klientowi dostępu VIP i nie jest przedstawiany jako skuteczne anulowanie u operatora. Wymagające ręcznego przeglądu anulowanie po moderacji nie jest automatycznie ponawiane przez tę poprawkę.

## Przywrócenie działania

1. Wykonaj kopię plików i bazy. Jeśli błąd uniemożliwia wejście do kokpitu, w menedżerze plików Hostinger tymczasowo zmień nazwę katalogu `wp-content/plugins/timonconfigs-core`; wyłączy to wtyczkę, nie usunie danych.
2. Zastąp wyłącznie katalog wtyczki paczką `artifacts/timonconfigs-core-1.1.8.zip`. Przy dostępnych Ustawieniach WordPressa można użyć **Wtyczki → Dodaj → Wyślij wtyczkę → Zastąp obecną wersję**. Przywróć właściwą nazwę katalogu i aktywuj Core. Motyw 0.14.2 nie wymaga ponownej instalacji dla tej poprawki.
3. Sprawdź Home, `/account/`, katalog, kokpit i profil zalogowanego członka. W razie kolejnego błędu zachowaj dokładny komunikat z prywatnego logu PHP; nie publikuj sekretów ani pełnych danych klienta.
4. W **Ustawienia → TimonConfigs Woo + Discord** zachowaj aktualne mapowanie produktów i Discorda. Nie uruchamiaj ponownie instalatora całego projektu ani migracji starego ledgeru.
5. Sprawdź kolejkę WooCommerce Action Scheduler / WP-Cron oraz webhooki w używanej bramce. Dla oficjalnej WooCommerce Stripe Gateway endpoint ma postać `https://timonconfigs.com/?wc-api=wc_stripe`; użyj adresu pokazanego przez faktycznie zainstalowaną bramkę. Stare `/wp-json/timonconfigs/v1/billing/...` nie obsługują bieżących zakupów. Signing secret musi odpowiadać właściwemu endpointowi i trybowi Test/Live.
6. Sprawdź kontrolowany zakup, powrót do profilu, potwierdzenie płatności przez operatora, nadanie VIP i usunięcie Free. Następnie sprawdź anulowanie przyszłych odnowień, zachowanie opłaconego okresu i przywrócenie Free po jego końcu. Nie traktuj samego powrotu z checkoutu jako dowodu zapłaty.

## Konfiguracja, która nadal musi być prawdziwa

W prywatnym `wp-config.php` potrzebne są istniejące poświadczenia Discord oraz para reCAPTCHA **v2 checkbox**: `TIMONCONFIGS_RECAPTCHA_V2_SITE_KEY` i `TIMONCONFIGS_RECAPTCHA_V2_SECRET_KEY`. Hostnames muszą obejmować rzeczywisty checkout. Brak poprawnej konfiguracji lub rozwiązania reCAPTCHA blokuje checkout; sam znaczek v3 nie zastępuje weryfikacji. Sekretów nie wpisuj do GitHub ani rozmowy.

Bot potrzebuje Manage Roles, pozycji nad Monthly/Lifetime/Free oraz View Audit Log do potwierdzania moderacji Staff/Owner. Rzeczywiste potwierdzenie zatrzymania odnowień sprawdzaj w ArraySubs i Stripe/PayPal; samo odebranie rangi lub lokalny cutoff nie jest takim potwierdzeniem.

Instrukcja PayPal Sandbox pozostaje w [przekazaniu 1.1.7](VIP_DISCORD_CHECKOUT_ROLES_CAPTCHA_1.1.7.md#paypal-sandbox): Monthly i jednorazowy Lifetime korzystają z osobnych konfiguracji odpowiednich bramek. Nie przełączaj globalnie produkcyjnej bramki na Sandbox dla aktywnych klientów.

## Walidacja i stan wdrożenia

Zaliczono lokalnie **354 asercje PHP**: rzeczywisty WooCommerce75, profil56, cykl ról35, kontrakt checkoutu28, prywatne pobrania69, powrót OAuth53 oraz prezentacja/CAPTCHA/audyt38. Lint118 plików PHP, JS, YAML i diff check poprawne; bez ostrzeżeń PHP. Przegląd Astra Medium nie zgłosił blokera w zakresie hotfixu. **64 asercje przeglądarkowe /12 widoków** Home, Pricing, Account i Configs przy1440/390/320 bez błędów PHP/JS i overflow. **98 kontroli świeżej instalacji ZIP** na osobnym offline WordPressie6.8.3/PHP8.2 zaliczonych. ZIP-y Core21/theme96plików są bajtowo zgodne ze źródłami; manifest `artifacts/core118-release-manifest.json`. Zewnętrzny ArraySubs2.0.9 nie jest zainstalowany lokalnie: testy jego API są symulowane i nie potwierdzają rzeczywistych podpisów lub działania operatora. Usługi płatności i Discord w testach są symulowane; nie wykonano rzeczywistej płatności, odnowienia ani anulowania u operatora.

Brak aktywnego połączenia do Hostinger/Stripe/PayPal uniemożliwia potwierdzenie zdalnej instalacji i konfiguracji. Push GitHub oraz lokalne ZIP-y nie są dowodem wdrożenia produkcji. Dalsza akceptacja wymaga odczytu produkcyjnego logu i kontrolowanego sprawdzenia aktualnej instalacji.
