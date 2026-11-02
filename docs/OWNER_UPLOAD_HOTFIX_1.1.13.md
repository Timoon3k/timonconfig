# Panel Ownera — upload, Core 1.1.13 / motyw 0.15.1

Zgłoszenie: plik `.znbk` około 5 KB, brak nowej konfiguracji nawet jako draft i komunikat „The connection was interrupted”. Taki rozmiar nie uzasadnia podnoszenia limitów hostingu w pierwszej kolejności. Dotychczasowy JavaScript wyświetlał ten sam komunikat również dla odpowiedzi HTML, odmowy HTTP i przekierowania do logowania. Sam zrzut nie potwierdzał utraty uprawnień ani przyczyny konkretnej odpowiedzi produkcyjnej; nie odczytano logów produkcji.

## Poprawka

- Formularz domyślnie wysyła do adresu witryny `/?tc_owner_submit=1`, zamiast wymagać dostępu do `wp-admin/admin-post.php`. Zapis nadal wymaga aktualnej sesji, nonce, przypisanego konta Discord i lokalnie zatwierdzonego Owner ID. Staff i zwykły członek nie otrzymują dostępu. Stary endpoint pozostaje zgodny ze starszymi formularzami.
- Obsługa następuje przed głównym zapytaniem WordPressa. Pola `tc_game[]` i `tc_device[]` nie są dzięki temu interpretowane jako zapytanie archiwum, które mogłoby ustawić HTTP 404 przed zwróceniem poprawnego JSON-a. Sukces zapisu jawnie zwraca HTTP 200.
- JS dodaje parametr JSON do żądania; zwykły formularz bez JS zachowuje dotychczasowe przekierowanie po zapisie. Błędy HTTP, przekierowanie do logowania, nieprawidłowy JSON i prawdziwe zerwanie połączenia są rozróżniane. Pola i wybrane pliki pozostają w formularzu, bez automatycznego ponawiania zapisu.
- Widoczne limity i sprawdzenie rozmiaru/liczby plików korzystają z rzeczywistych ustawień PHP oraz istniejących limitów aplikacji. Przekroczenie `post_max_size`, przy którym PHP usuwa całe POST/FILES, daje jednoznaczną odpowiedź 413 przed zapisem. Nie jest to potwierdzona przyczyna zgłoszenia 5 KB.
- Automatyczne tworzenie kolekcji nie uruchamia się podczas POST, AJAX, cron ani `admin-post.php`. Zwykłe wejście administratora do kokpitu i jawny instalator nadal działają.

Nie zmieniono ról, płatności, listy zatwierdzonych Ownerów ani reguł pobierania. `.gpc`, `.znbk` i `.txt` nadal są zapisywane jako surowe bajty, bez dekodowania UTF/MIME lub parsowania skryptów. Zachowano ochronę rozszerzeń, prywatnego katalogu i limity miejsca.

## Aktualizacja

1. Zachowaj standardową kopię bazy oraz motywu i Core. Zainstaluj **Core 1.1.13** i **motyw 0.15.1**. Sam stary motyw lub sama zmiana kluczy Discord nie zapewni pełnej poprawki transportu.
2. Wyczyść cache LiteSpeed/Hostinger i cache zasobów, następnie ponownie otwórz panel Ownera, aby otrzymać świeży formularz i nonce. Nie importuj ponownie JSON-a Rank Math ani nie uruchamiaj pełnego setupu witryny.
3. Wyślij plik ZNBK 5 KB przez **Publish config**. Sprawdź stan `publish`, przypisanie gry/urządzenia i obecność pliku. Pliki nadal nie trafiają do publicznej biblioteki Mediów.
4. Jeśli żądanie zostanie odrzucone na produkcji, zachowaj dokładny komunikat z kodem HTTP. Odpowiedź 401/403 wskazuje potrzebę sprawdzenia sesji lub reguł dostępu hostingu; 5xx wymaga korelacji z prywatnym logiem PHP/proxy. Nie wyłączaj globalnie zabezpieczeń i nie nadawaj Ownerowi WordPress Administrator tylko dla uploadu. Po niepewnym wyniku sprawdź Existing configurations przed ponowieniem.

## Wyniki

- 169 asercji dotychczasowego modułu Ownera, 24 kontraktu transportu i 68 regresji kolekcji.
- 42 asercje rzeczywistego lokalnego HTTP: publikacja nieprzetwarzanego ZNBK **5 KiB**, integralność pobrań ZNBK/GPC/TXT, zapis bez nadania roli administratora, odmowa dla Staff, rzeczywisty multipart 9 MiB odrzucony przez PHP z pustymi POST/FILES i odpowiedzią JSON/413 bez utworzenia konfiguracji. Dane testowe usunięto; Discord był symulowany tylko w odizolowanym teście.
- 48 przypadków transportu w Chromium, 516 asercji, brak błędów JS. Scenariusze HTTP były symulowane na loopback; nie są pomiarem serwera produkcyjnego.
- Lint 124 PHP, JS/static/YAML/diff oraz 98 kontroli świeżej instalacji finalnych ZIP-ów w oddzielnym WordPressie offline.

Paczki: `artifacts/timonconfigs-core-1.1.13.zip` (21 plików), `artifacts/timonconfigs-theme-0.15.1.zip` (97 plików). CRC, SHA-256 i zgodność źródeł: `artifacts/owner-upload-release-manifest-20261102.json`. Kod na `fix/owner-upload-20261102`; checkpoint `111660d`, końcowy commit w historii Git. Właściciel zlecił publikację GitHub main 2026-11-02; wynik potwierdza zdalny ref. Push repozytorium nie potwierdza instalacji na Hostingerze.
