# SEO katalogu konfiguracji — 2026-11-05

Plan opracowano na podstawie bieżącego kodu repozytorium. Nie odczytano aktualnej witryny produkcyjnej, Search Console ani treści prywatnego kanału Discord. Wskazane angielskie teksty są propozycjami redakcyjnymi, a nie potwierdzeniem funkcji lub kompatybilności konkretnego wydania.

## Właściwe adresy

| Rola | Adres |
| --- | --- |
| Główny katalog | `/configs/` |
| Publiczny opis konkretnej konfiguracji | `/config/{slug}/` |
| Kolekcja urządzenie + gra | `/xim-matrix-apex-legends/`, `/cronus-zen-apex-legends/` i istniejące odpowiedniki |
| Ogólna kolekcja urządzenia | `/xim-matrix-configs/`, `/cronus-zen-configs/` |
| Ogólna kolekcja gry | `/apex-legends-configs/` i istniejące kolekcje gier |

Core rejestruje archiwum jako `configs`, a bazę pojedynczych wpisów jako `config`. Samo `/config/` nie ma w kodzie osobnej trasy archiwum. Nie zmieniaj istniejących adresów `/config/{slug}/`. Jeżeli goły adres `/config/` został już użyty w linkach i na produkcji nie należy do autorskiej strony, można dodać dokładne przekierowanie 301 wyłącznie `/config/` → `/configs/`. Nie wolno stosować przekierowania całego prefiksu `/config/*`, ponieważ usunęłoby dostęp do publicznych opisów wydań.

## Priorytet: dostępny publiczny opis każdej konfiguracji

Obecna karta w `template-parts/config-card.php` pokazuje tytuł jako tekst, a jej główny overlay prowadzi do logowania, członkostwa lub chronionego pobrania. W tym wydaniu dodano osobny widoczny link **Release details** do `get_permalink()` na każdej karcie, także dla Free, VIP i wydań oczekujących. Link powinien być osiągalny klawiaturą i znajdować się nad warstwą overlay. Dotychczasowy przycisk pobierania i kontrola dostępu pozostają zachowane.

Takie linkowanie umożliwia przejście z katalogu do publicznych opisów bez logowania. Mapa XML nadal pomaga w odkrywaniu adresów, lecz nie zastępuje użytecznej nawigacji wewnętrznej.

## `/configs/` jako katalog nadrzędny

Zadaniem katalogu jest przedstawienie oferty i ułatwienie doboru konfiguracji. Konkretna fraza urządzenie + gra powinna prowadzić do istniejącej odpowiedniej kolekcji. Nie twórz kolejnych niemal identycznych stron dla odmiany tego samego zapytania.

Propozycje dla głównego katalogu:

- **H1:** `XIM Matrix & Cronus Zen Config Library`
- **SEO title:** `XIM Matrix & Cronus Zen Configs | TimonConfigs`
- **Meta description:** `Browse TimonConfigs releases for XIM Matrix and Cronus Zen. Compare game, device, input, platform and Free or VIP access before choosing a config.`
- **Wstęp:** `Find a configuration that matches your game and device. Compare the input method, published version, platform notes and access requirement on each release page. Free and VIP files use Discord-linked access checks. A game or device label alone does not confirm compatibility with every platform or update.`

Poniżej katalogu wystarczą konkretne sekcje:

1. **Choose your device** — różnice pomiędzy katalogiem Cronus Zen i XIM Matrix oraz bezpośrednie linki do obu kolekcji.
2. **Browse by game** — linki do rzeczywiście dostępnych kolekcji gier; status roadmapy powinien pozostać jednoznaczny.
3. **Read a release page before downloading** — wymagane urządzenie, input, wersja gry i pliku, potwierdzone platformy oraz instrukcja instalacji.
4. **Free and VIP access** — jasny opis aktualnego modelu dostępu i link do cennika.
5. **Setup help** — linki do własnych poradników i materiałów, gdy są faktycznie opublikowane.

Zapisane indywidualne metadane Rank Math pozostają nadrzędne wobec domyślnych tekstów motywu. Tytuły i opisy warto zmieniać świadomie w panelu tej wtyczki; ponowny import starego JSON-a nie jest potrzebny.

## Wzorzec opisu pojedynczego wydania

Każda publiczna strona `/config/{slug}/` powinna zawierać własne, konkretne informacje:

- **About this config:** nazwa wydania i jego faktyczne przeznaczenie, bez niepotwierdzonych obietnic wyników gry.
- **Supported setup:** urządzenie, input, platformy i wersja gry potwierdzone dla tego wydania.
- **Installation:** własna instrukcja lub link do rzeczywistego poradnika; jawne wymagania i znane ograniczenia.
- **Version and changelog:** rzeczywista wersja pliku, data wydania/aktualizacji i zakres zmian.
- **Access:** Free, Monthly/Lifetime lub Lifetime zgodnie z przypisaną ofertą.
- **Related releases:** właściwa kolekcja gra + urządzenie i inne faktycznie powiązane wydania.

Przykład szablonu SEO title: `[Release name] — [Device] [Game] Config | TimonConfigs`. Wypełnij pola faktycznymi danymi. Nie wpisuj roku, sezonu, potwierdzenia zgodności, autora lub wyniku gracza, jeśli nie zostały ustalone.

Publiczny opis może być dostępny Google, podczas gdy same pliki, Guide/Values wymagające członkostwa i sesyjne URL-e pobrań pozostają chronione. Nie dodawaj do indeksu prywatnych linków ani nie udostępniaj plików VIP w celu poprawy SEO.

## Indeksowanie i filtry

Bieżący kod celowo wyłącza z indeksowania wyszukiwanie i warianty katalogu z filtrami `config_search`, `game`, `device`, `tier`, `input`, `platform` lub `sort`. Służą one użytkownikowi, a docelowymi stronami dla stałych tematów są czyste kolekcje.

Kolekcje gry/urządzenia oraz przecięcia muszą zawierać wydaną konfigurację i użyteczne opisy terminów, bez ręcznego wykluczenia `tc_noindex`. W kodzie ocena długości opisu korzysta z `strlen()` i progu 120 bajtów; najlepiej przygotować pełny opis, który rzeczywiście objaśnia kolekcję, zamiast uzupełniać go do granicy licznika. Opublikowanie pustej strony nie zmienia jej automatycznie w stronę przeznaczoną do indeksowania.

Każda strona paginacji powinna zachować prawidłowy, własny canonical. Nie kieruj wszystkich stron katalogu i pojedynczych wydań do canonical Home. Aktualny motyw respektuje własność metadanych aktywnej wtyczki SEO i nie powinien emitować drugiego zestawu OG/JSON-LD.

## Obrazy i materiały

Obejrzano lokalne obrazy `game-apex-legends.webp` oraz `game-call-of-duty.webp`. Są to ilustracyjne okładki gier, a nie dowód wyglądu konkretnej konfiguracji lub zrzuty jej działania. W powtarzanych kartach, stripie gier i tle hero ich pusty `alt` może pozostać prawidłowy: nazwa gry i status są już dostępne jako tekst linku lub tekst dla czytnika ekranu.

Nie należy automatycznie zastępować wszystkich pustych altów frazą „xim matrix apex legends”. Własny, informacyjny obraz wydania powinien dostać opis tego, co faktycznie pokazuje, np. `XIM Matrix Manager showing the input settings for [actual release]`, tylko jeśli taki ekran znajduje się na obrazie. Wyróżniony obraz wybrany w Bibliotece mediów powinien zachować autorski tekst alternatywny.

Dla materiałów wideo używaj opisów rzeczywistego nagrania, własnych instrukcji i opisów widocznych scen. Nie określaj urządzenia, wyniku meczu, zgodności, daty nagrania lub długości filmu na podstawie samej nazwy pliku. Powtarzana miniatura przy opisanym przycisku Play może być dekoracyjna; uzupełniony publiczny opis nagrania pomaga zrozumieć materiał także bez odtwarzania.

Prawdziwe opinie Discorda mogą dostarczać użytkownikom kontekstu i zaufania. Powinny zachować rzeczywistą treść i źródło. Nie stanowią same w sobie gwarancji rankingu ani uprawnienia do gwiazdek Google dla własnej organizacji; nie dodawaj wymyślonych ocen lub AggregateRating.

## Pomiar po aktualizacji

Po rzeczywistej instalacji Core i motywu wyczyść cache. Sprawdź anonimowo katalog i kilka publicznych stron wydań, działające linki szczegółów, pojedynczy H1 oraz rzeczywiste title/canonical/robots. Zweryfikuj odpowiednie adresy w GSC i odczyt map XML. Porównuj wyświetlenia, zapytania, pozycje i docelowe URL-e w kolejnych okresach; nie oceniaj skuteczności wyłącznie na podstawie punktacji Rank Math.
