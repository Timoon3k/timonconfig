# Poprawki SEO z PDF — Core 1.3.1 / motyw 0.17.2, 2026-11-10

Wydanie przygotowano na podstawie aktualnego GitHub main `27c8ab8` i przesłanego `TimonConfigs_SEO_Updates.pdf`. Dokument potraktowano jako propozycję treści do weryfikacji. Zakres obejmuje cztery adresy, tytuły/opisy/frazy Rank Math i wstępy dwóch kolekcji. Samo wdrożenie nie gwarantuje natychmiastowej indeksacji ani określonej pozycji w Google.

## Zaktualizowane metadane

| Adres | SEO title | Główna fraza |
| --- | --- | --- |
| `/` | TimonConfigs \| XIM Matrix & Cronus Zen Configs for Apex Legends | TimonConfigs |
| `/apex-legends-configs/` | Apex Legends Configs – Cronus Zen & XIM Matrix \| TimonConfigs | Apex Legends configs |
| `/xim-matrix-apex-legends/` | XIM Matrix Apex Legends Configs & Settings \| TimonConfigs | XIM Matrix Apex Legends configs |
| `/cronus-zen-apex-legends/` | Cronus Zen Apex Legends Configs & Scripts \| TimonConfigs | Cronus Zen Apex Legends configs |

Opisy dla poszczególnych adresów:

- `/`: Explore TimonConfigs XIM Matrix and Cronus Zen configs for Apex Legends. Compare release details, setup guidance and Free or VIP access for your device.
- `/apex-legends-configs/`: Browse Apex Legends configs for Cronus Zen and XIM Matrix. Compare published releases, supported input, platform details and Free or VIP access.
- `/xim-matrix-apex-legends/`: Explore XIM Matrix Apex Legends configs and settings. Check controller or mouse-and-keyboard input, release details, platform notes and access requirements.
- `/cronus-zen-apex-legends/`: Browse Cronus Zen Apex Legends configs and scripts. Compare release details, supported inputs, platform notes and Free or VIP access before choosing a setup.

Pełny bieżący zestaw 46 profili: [CSV](seo/timonconfigs-page-metadata-2026-11-10.csv) i [JSON](seo/timonconfigs-page-metadata-2026-11-10.json). JSON zawiera dane pojedynczych obiektów; nie należy importować go jako globalnych ustawień Rank Math. Pozostałe 42 profile mają niezmienione teksty.

## Treść kolekcji

Wstępy XIM Matrix Apex Legends i Cronus Zen Apex Legends zawierają naturalną główną frazę, różnice wejścia/formatu urządzenia, sprawdzenie wersji i platformy, odnośniki do FAQ/cennika oraz wskazanie publicznego Release details. Zachowano istniejące H1. Miejsce pełnego wstępu jest nad listą konfiguracji, dlatego nie dodano drugiego wstępu. Test z aktywnym Rank Math wykrył błąd tego miejsca: nagłówek WordPressa nadpisywał zarezerwowaną globalną zmienną `$page`, w której szablon przechowywał obiekt strony. W efekcie wybierano skrócony opis zamiast zapisanego `post_content`. Motyw 0.17.2 zachowuje oddzielny obiekt `$collection_post`, dzięki czemu pełny zapisany wstęp trafia do HTML. Wersja zasobów motywu została także wyrównana z numerem paczki. Nazwy rzeczywiście opublikowanych wydań pozostają prezentowane przez istniejące karty katalogu i ich publiczne linki.

Usunięto z propozycji niepotwierdzone deklaracje „Best”, „Most Popular”, aktualnego sezonu 30/31, dostępności konkretnych skryptów i regularnych aktualizacji wszystkich plików. Nazwa lub porada z PDF nie zastępuje rzeczywistych danych wydania.

Nowe wstępy powstają na świeżych kolekcjach. Aktualizacja dotychczasowej wersji kolekcji dotyczy wyłącznie istniejących stron Apex i nie odtwarza innych usuniętych kolekcji. W istniejącej instalacji migracja rozpoznaje dokładną wcześniejszą treść startową, pusty wstęp albo niezmienioną treść zarządzaną. Przed zmianą zapisuje `_tc_apex_intro_backup`, a następnie weryfikuje zapis i znacznik `_tc_apex_intro_managed`. Własne teksty, szkice, kosz i strony z hasłem są zachowane. Zmiana wstępu nie zmienia tytułu strony, slugu, statusu publikacji ani ustawień SEO właściciela.

Natywne metadane przechodzą z wersji v1 do v2: aktualizowane są wartości nadal zgodne z poprzednim zapisem modułu. Późniejsze ręczne poprawki i pola wcześniej przywrócone przez administratora zachowują pierwszeństwo. Najstarsza kopia poprzednich metadanych pozostaje dostępna w Tools → Page SEO.

## Aktualizacja WordPressa

1. Zachowaj kopię bazy i bieżących pakietów. Wgraj najpierw `timonconfigs-theme-0.17.2.zip`, następnie `timonconfigs-core-1.3.1.zip`, zastępując dotychczasowe pakiety. Motyw zawiera niezbędną poprawkę wyświetlania pełnego wstępu.
2. Otwórz kokpit jako administrator. Migracja kolekcji i metadanych wykonuje się podczas zwykłych żądań administratora; nie podczas odwiedzin gości, checkoutu, uploadu, AJAX lub crona.
3. Otwórz **Narzędzia → Page SEO**. Przy większym katalogu użyj **Continue metadata preparation** do zakończenia porcji. Sprawdź cztery adresy w polach Rank Math.
4. Otwórz oba wstępy w edytorze stron. Jeśli wcześniej napisano własną treść, aktualizacja jej nie nadpisze — w razie potrzeby dopasuj ją do przygotowanej treści po przeczytaniu oryginału.
5. Wyczyść cache LiteSpeed/hostingu/CDN. Moduły wysyłają także purge URL po zmianie treści lub metadanych. Nie uruchamiaj ponownie instalatora witryny i nie importuj starego globalnego JSON-a Rank Math.

## Kontrola po wdrożeniu i Google Search Console

Dla każdego z czterech adresów sprawdź rzeczywistą stronę i źródło HTML: HTTP 200, jeden H1, jeden tytuł i opis, właściwy canonical oraz oczekiwane robots. Pusta kolekcja pozostaje noindex do czasu rzeczywistego opublikowania pasujących wydań i spełnienia istniejących kryteriów indeksowania. Konto, koszyk, checkout i prywatne pobrania zachowują swoje reguły.

W GSC wybierz **Sprawdzenie adresu URL → Test wersji opublikowanej**. Po udanym teście kwalifikującego się adresu użyj **Poproś o zindeksowanie**, jeśli limit jest dostępny. Weryfikuj końcowy adres po przekierowaniach. Mapa witryny powinna wskazywać aktualne, kanoniczne i kwalifikujące się strony; nie trzeba wielokrotnie przesyłać tej samej mapy. Dla zwykłych stron nie dodano Google Indexing API.

Porównuj wyświetlenia i zapytania w raporcie Skuteczność po ponownym przetworzeniu stron przez Google. Wynik Rank Math ani samo żądanie indeksowania nie potwierdzają pozycji.

## Plan treści z PDF — kolejny etap redakcyjny

Plan artykułów wymaga własnych, sprawdzonych informacji:

- Dobór Cronus Zen do Apex Legends: realne różnice opublikowanych konfiguracji, input i platforma; link do właściwej kolekcji.
- XIM Matrix Apex Legends: osobne wskazówki dla controller i mouse & keyboard, z potwierdzonymi wymaganiami wydań.
- Poradnik konkretnego dostępnego wydania: instalacja, rzeczywista wersja, zmiany i ograniczenia; żadnej wymyślonej recenzji lub wyników testów.
- Import skryptu lub backupu Cronus Zen: sprawdzona instrukcja odpowiadająca rzeczywistemu formatowi pliku.
- XIM Matrix i Cronus Zen: porównanie potwierdzonych zastosowań i wymagań, z linkami do obu kolekcji.

Tych propozycji nie opublikowano automatycznie jako pustych stron, recenzji ani niezweryfikowanych poradników.

## Weryfikacja wydania

Zaliczone: 463 asercje integracyjne PHP, 268 asercji z aktywnym Rank Math w 14 widokach przeglądarki, lint 136 PHP oraz 120 kontroli świeżej instalacji finalnych ZIP-ów. Fixture i zmieniane opcje przywrócono; paczki są zgodne bajtowo ze źródłami. Wyniki testów, manifest paczki i końcowy ref Git są zapisane w PROGRESS.md oraz artifacts/. Testy korzystają z lokalnego WordPressa i oznaczonych, odwracalnych danych QA. Nie odczytano aktualnej produkcji ani GSC; sieć środowiska nie udostępnia timonconfigs.com i brak uwierzytelnionego dostępu do hostingu. Push na GitHub nie potwierdza instalacji WordPressa.
