# Opinie Discord, filmy i SEO — Core 1.2.0 / motyw 0.17.0

Wydanie przygotowano na podstawie GitHub main `4d302d4`. Zachowano poprawkę układu checkoutu 0.16.1, płatności, subskrypcje, role Discorda, prywatne pliki, adresy kolekcji i autorskie ustawienia SEO.

## Prawdziwe opinie z kanału vouches

Źródło wskazane przez właściciela: https://discord.com/channels/1557854161805115402/1560781856931315832 . Importer wykonuje wyłącznie serwerowe żądania GET do Discord API. Używa prywatnego tokena istniejącego bota, nie sesji przeglądarki użytkownika.

- Zwykła wiadomość tekstowa członka daje jedną opinię z jego rzeczywistą nazwą wyświetlaną, tekstem, datą wiadomości i odnośnikiem do oryginału. Nierozpoznane wzmianki i emoji Discorda mają tekstowe odpowiedniki; identyfikatory prywatnych wzmianek nie są rozwiązywane.
- Nie dopisuje się gwiazdek, informacji o zweryfikowanym zakupie, urządzeniu ani wyniku gry. Wiadomości botów/webhooków/systemowe, same załączniki i puste treści nie są importowane. Specjalny format vouch-bota lub opinii w embedach wymaga osobnego mapowania rzeczywistego autora i treści.
- Znikają publiczne przykładowe opinie Nova/Vector/Orbit/Pulse/Cipher/Echo i wpisy oznaczone `tc_review_demo`. Zachowano istniejące autorskie wpisy w bazie. Przy pustym zbiorze jest odnośnik do rzeczywistego kanału, bez fikcyjnych cytatów.
- Ponowny import nie duplikuje wiadomości. Edycje u źródła aktualizują cytat. Tylko potwierdzony błąd Discord `10008 — Unknown Message` ukrywa usuniętą wiadomość; błędy dostępu, kanału, sieci i limity zapytań zachowują wcześniejsze opinie.
- Do ręcznego ukrycia użyj Draft lub Kosza w **Reviews**. Synchronizacja zachowuje ten wybór. Lokalnie zmieniony cytat nie jest prezentowany jako oryginalny tekst Discorda.
- Żądania i zapisy chroni odnawiana blokada pracownika; pojedynczy przebieg ma ograniczony czas i liczbę stron. Historia jest pobierana stopniowo. Kontrola starszych usunięć jest rotacyjna, więc nie każda stara wiadomość zostanie sprawdzona w tym samym przebiegu.
- Cytaty są danymi tekstowymi: HTML i shortcode’y WordPressa nie wykonują się. Token nie pojawia się w HTML, komunikatach, repozytorium ani polach ustawień.

## Instalacja i uruchomienie

1. Zachowaj kopię bazy, bieżącego motywu i Core. Najpierw wgraj `timonconfigs-theme-0.17.0.zip`, następnie `timonconfigs-core-1.2.0.zip`, zastępując dotychczasowe pakiety. Motyw z tego wydania poprawnie obsługuje opinie bez ocen gwiazdkowych.
2. Wyczyść cache LiteSpeed/hostingu/CDN. Nie uruchamiaj ponownie instalatora witryny ani nie importuj starego JSON-a Rank Math.
3. Otwórz **Reviews → Discord vouches**. Domyślne ID serwera i kanału są już ustawione z podanego linku. Sprawdź status **Bot credentials**. Moduł korzysta kolejno z `TIMONCONFIGS_VOUCHES_DISCORD_BOT_TOKEN`, `TIMONCONFIGS_WOO_DISCORD_BOT_TOKEN` lub zachowanej starszej stałej bota. Sekret pozostaje wyłącznie w prywatnej konfiguracji serwera.
4. Właściciel potwierdził rolę Administrator bota. Oddzielnie sprawdź **Discord Developer Portal → aplikacja bota → Bot → Privileged Gateway Intents → Message Content Intent**. Bot potrzebuje odczytu kanału i historii; nie wysyła tam wiadomości. Panel zgłasza sytuację, w której Discord zwraca wiadomości bez treści.
5. Wybierz **Synchronize now**. Sprawdź licznik i komunikat synchronizacji, potem Home i `/reviews/`. Jeżeli brakuje tokena lub dostęp jest odrzucony, skonfiguruj istniejącego bota prywatnie na serwerze — nie wklejaj tokena do czatu lub GitHub.
6. Automatyczne pobieranie jest domyślnie włączone, gdy token jest dostępny. Pierwsze zdarzenie jest planowane po około minucie, następne co 15 minut przez WP-Cron. Przy małym ruchu ustaw w hostingu regularne uruchamianie WordPress cron. Komunikaty błędów i backoff są widoczne w panelu. Po zmianach opinii moduł wysyła żądanie odświeżenia cache Home i Reviews do LiteSpeed.

W tej sesji nie było uwierzytelnionego dostępu do prywatnego kanału ani produkcyjnego WordPressa. Nie pobrano tu prawdziwych opinii i nie zastąpiono ich wymyślonymi wpisami. Testy używają wyraźnie oznaczonych danych LOCAL QA, które są następnie usuwane. Rzeczywisty import nastąpi na serwerze po zainstalowaniu wydania i udanym połączeniu bota.

## Opisy filmów i obrazów

Opisy odnoszą się do obejrzanych kadrów trzech plików dostarczonych w motywie. Nie przypisują im niepotwierdzonych urządzeń, firmware, skuteczności lub wyników. Własne niepuste tytuły i opisy pozostają zachowane; obcy adres filmu nie otrzymuje opisu innego nagrania.

| Plik / materiał | Opis alternatywny obrazu |
| --- | --- |
| `apex-demo-01.jpg`, scoped practice | A scoped weapon aimed at a training dummy in the Apex Legends firing range |
| `apex-demo-02.jpg`, target practice and combat | Scoped target practice in Apex Legends, framed by a blurred gameplay background |
| `apex-demo-03.jpg`, indoor/outdoor combat | Scoped Apex Legends combat inside a building beneath the caption getting better each day |
| `apex-legends-crimson-hero.webp` | A Wraith-inspired operative in a dark sci-fi arena beneath red lightning |

Plakaty mają rzeczywiste wymiary 576×1024. Tytuły i opisy są publicznie widoczne na Home i Video Showcase. Dla MP4 opis plakatu jest dostępny przez `aria-describedby`; element `<video>` nie otrzymuje nieprawidłowego atrybutu `alt`. Dla plakatu YouTube używany jest alt obrazu. Powtarzane dekoracyjne okładki gier i emblemat obok tekstowej nazwy marki mogą mieć pusty alt, aby nie dublować tekstu czytnikom ekranu.

Edycja: **Apex videos → wybrany film** — tytuł, treść/Excerpt, Poster alternative text, URL plakatu lub obraz wyróżniający. Gdy nie opublikowano wpisów wideo, ustawienia trzech domyślnych kart są w **Wygląd → Dostosuj → TimonConfigs site content**. Własny obraz z Biblioteki mediów zachowuje swój opis i wymiary.

VideoObject powstaje dopiero dla faktycznie wyświetlanego filmu z potwierdzonymi wymaganymi danymi, w tym datą publikacji i czasem trwania. Nie wymyślono dat filmów. Przy Rank Math dane są dodawane przez jego filtr JSON-LD, bez drugiego skryptu metadanych; własne istniejące VideoObject zachowuje pierwszeństwo. Opinie o własnej organizacji nie otrzymują sztucznego AggregateRating ani obietnicy gwiazdek w Google.

## Pomysł na SEO katalogu

Pełny plan: `docs/CONFIG_CATALOG_SEO_2026-11-05.md`. Główny katalog jest pod `/configs/`, a `/config/{slug}/` to publiczny opis pojedynczego wydania. Dodano na kartach osobny link **Release details**, osiągalny klawiaturą i działający nad warstwą pobierania. Prywatne pliki i Values zachowują dotychczasową kontrolę dostępu.

Rozwijaj własny opis każdego wydania: urządzenie, gra, input, potwierdzone platformy, wersja, instrukcja instalacji, lista zmian i rozwiązania typowych problemów. Katalog prowadzi do tych opisów i istniejących kolekcji gra + urządzenie. Nie zmieniano obecnych URL-i, canonical, noindex pustych kolekcji lub reguł prywatnych stron. Własne metadane Rank Math są zachowane. Nowe domyślne meta Reviews/Video Showcase działają przez adapter, gdy strona nie ma autorskich nadpisań.

## Walidacja i zakres

Końcowe wyniki testów, gałąź/commit i manifesty ZIP są zapisane w `PROGRESS.md` i `artifacts/`. Publikacja do GitHub main nie potwierdza instalacji na hostingu ani rzeczywistego odczytu kanału. Nie zmieniano konfiguracji operatorów, zamówień, cen, subskrypcji ani ról Discorda. Unikalna publiczna treść i lepsze linkowanie wspierają odkrywanie strony; nie gwarantują pozycji w Google.
