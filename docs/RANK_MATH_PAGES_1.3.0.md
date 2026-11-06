# Tytuły, frazy i opisy podstron — Core 1.3.0

Przygotowano 46 angielskich profili odpowiadających treści i trasom witryny: 27 głównych stron i kolekcji gra + urządzenie, 7 kolekcji gier, 2 urządzenia, 3 platformy, katalog `/configs/` oraz 6 ról stron konta/WooCommerce. Nazwy stron, adresy i wpisy produkcyjne nie były odczytywane z aktualnego hostingu; mapowanie wykorzystuje rzeczywiste obiekty WordPressa po zainstalowaniu aktualizacji.

Gotowe teksty:

- `docs/seo/timonconfigs-page-metadata-2026-11-06.csv` — arkusz z tytułem, jedną główną frazą i opisem dla każdego profilu.
- `docs/seo/timonconfigs-page-metadata-2026-11-06.json` — ta sama mapa w formacie danych; to nie jest plik globalnego importu ustawień Rank Math.

## Co zostanie zapisane

Aktualizacja zapisuje natywne pola `rank_math_title`, `rank_math_description` i `rank_math_focus_keyword` na stronach, publicznych wpisach, produktach i konfiguracjach oraz metadane kolekcji. W odróżnieniu od samego adaptera frontendu wartości pojawią się także w edytorze Rank Math.

- Home: marka i oba urządzenia; główna fraza `TimonConfigs`.
- Dedykowana kolekcja: np. `XIM Matrix Apex Legends configs`; osobne frazy dla pozostałych gier i urządzeń.
- Katalog `/configs/`: tytuł i opis w natywnych ustawieniach archiwum `tc_config`, z zachowaniem `%page%`. Rank Math nie oferuje głównej frazy dla archiwum CPT — fraza w arkuszu jest jego celem redakcyjnym, bez wymyślonego pola wtyczki.
- Kolejne konfiguracje `/config/{slug}/`: rzeczywisty tytuł wydania, przypisane gra/urządzenie i opis zakresu publicznych informacji. Nie kopiuje się prywatnych Values, plików, pełnej treści ani zawartości shortcode’ów.
- Pozostałe autorskie strony i produkty: uzupełniane są brakujące pola na podstawie tytułu i jawnego Excerpt opublikowanej strony bez hasła. Istniejące autorskie pola pozostają zachowane. WordPressowe `sample-page` i `hello-world` są pomijane; nie zastępują wartościowej treści witryny.

Dla znanych profili zestaw zastępuje wcześniejsze trzy pola raz, zgodnie ze zleceniem właściciela. Ich poprzednie wartości są zapisywane przed zmianą. Następne wejścia do kokpitu nie nadpisują późniejszych korekt autora. Wiele stron przetwarza się w ograniczonych porcjach; równoległe przebiegi chroni blokada.

## Instalacja i kontrola

1. Wgraj `timonconfigs-core-1.3.0.zip`, zastępując Core. Bieżący motyw 0.17.1 może pozostać bez zmian. Rank Math powinien być aktywny, aby wyświetlać i renderować swoje pola.
2. Otwórz kokpit jako administrator. Przygotowanie pól uruchamia się wyłącznie podczas zwykłych wejść administratora; nie podczas wizyt gości, checkoutu, przesyłania plików, AJAX ani crona.
3. Otwórz **Narzędzia → Page SEO**. Tabela pokazuje aktualnie zapisane tytuły, frazy i opisy. Przy dużym katalogu wybierz **Continue metadata preparation** do zakończenia kolejnych porcji albo przejdź ponownie do kokpitu.
4. W edycji strony wybierz **Rank Math → Edit Snippet** oraz **Focus Keyword**. Kolekcje gier/urządzeń są edytowane jako właściwe terminy taksonomii. Strony Woo są rozpoznawane po identyfikatorach skonfigurowanych w WooCommerce, więc polski slug koszyka lub płatności nie powoduje błędnego przypisania.
5. Wyczyść cache LiteSpeed/hostingu/CDN i skontroluj źródło strony. Moduł wysyła także purge URL zmienionych stron do LiteSpeed. Nie uruchamiaj ponownie instalatora witryny i nie importuj starego globalnego JSON-a Rank Math: nie służy on do metadanych pojedynczych stron.

W tabeli **Page SEO** można przywrócić poprzednie pola dla konkretnego obiektu lub katalogu. Przywracanie dotyczy tylko wartości, które nadal odpowiadają zapisowi modułu; późniejsze ręczne korekty pozostają zachowane. Cofnięta wartość nie jest automatycznie stosowana ponownie.

## Indeksowanie i ograniczenia

Nie zmieniono URL-i, canonical, schema, statusów publikacji ani dyrektyw robots. Konto, koszyk, checkout, prywatne pobrania, filtrowane widoki i puste kolekcje zachowują wcześniejsze reguły noindex. Sam nowy opis nie udostępnia plików ani nie czyni pustej kolekcji gotową do indeksowania. Strony z przygotowanymi tytułami zachowują rozróżnienie numerów stron katalogu; późniejsze ręczne tytuły pozostają własnością edytora.

Focus Keyword jest wskazówką dla analizy Rank Math, nie znacznikiem zwiększającym pozycję w Google. Analizator może nie widzieć tekstu generowanego przez PHP motywu. Brak 300 słów lub niektóre czerwone kontrolki nie oznaczają automatycznie blokady indeksowania. Nie dodawaj sztucznego tekstu ani powtarzanych fraz tylko dla wyniku punktowego.

Zaliczone lokalnie: 197 nowych asercji integracyjnych, 142 asercje HTTP/przeglądarkowe z aktywnym Rank Math w 10 widokach, 85 regresji SEO i 68 kolekcji, lint 133 PHP oraz 116 kontroli świeżej instalacji ZIP-a. Dane QA i zmieniane ustawienia przywrócono. Wyniki lokalnych testów są zapisane w `PROGRESS.md` i `artifacts/`. Aktualizacja GitHub nie potwierdza instalacji na hostingu ani zaindeksowania nowych metadanych przez Google.
